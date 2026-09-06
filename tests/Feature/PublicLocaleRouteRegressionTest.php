<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageTagModule;
use App\Models\Role;
use App\Models\Tag;
use App\Models\TranslationLocale;
use App\Models\TranslationString;
use App\Services\PageBlockContentResolver;
use App\Services\TranslationCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicLocaleRouteRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_specialized_legacy_redirects_keep_the_explicit_bangla_locale(): void
    {
        $this->enableBangla();

        foreach ([
            '/page/home?lang=bn' => '/?lang=bn',
            '/page/about-us?lang=bn' => '/about-us?lang=bn',
            '/page/zakat?lang=bn' => '/zakat?lang=bn',
            '/page/sponsor-a-child?lang=bn' => '/sponsor-child?lang=bn',
        ] as $legacy => $canonical) {
            $this->get($legacy)
                ->assertStatus(301)
                ->assertRedirect(url($canonical));
        }
    }

    public function test_bangla_project_archives_read_group_assignment_from_the_logical_page(): void
    {
        $this->enableBangla();
        $group = $this->makeTag('Education', 'education');
        $uuid = (string) Str::uuid();
        $english = $this->makePage($uuid, 'en', 'English project', 'english-project');
        $bangla = $this->makePage($uuid, 'bn', 'বাংলা প্রকল্প', 'bangla-project');

        PageTagModule::create([
            'uuid' => (string) Str::uuid(),
            'page_id' => $english->id,
            'tag_id' => $group->id,
        ]);
        foreach ([
            'name' => 'শিক্ষা',
            'description' => 'কমিউনিটির সঙ্গে পরিচালিত শিক্ষা প্রকল্প।',
        ] as $field => $value) {
            TranslationString::create([
                'key' => 'content.project_group.' . $group->uuid . '.' . $field,
                'locale' => 'bn',
                'value' => $value,
                'source_hash' => hash('sha256', 'en|' . $group->{$field}),
                'status' => 'translated',
            ]);
        }

        $translationRows = app(TranslationCenterService::class)->rows('en', 'bn');
        $this->assertTrue($translationRows->contains(fn (array $row): bool =>
            ($row['identity']['model'] ?? null) === 'project_group'
                && ($row['identity']['source_id'] ?? null) === $group->id
                && ($row['identity']['field'] ?? null) === 'name'
        ));

        $this->get('/projects?lang=bn')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('project')
                ->where('locale', 'bn')
                ->where('properties.total_count', 1)
                ->where('data.items.0.uuid', $bangla->uuid)
                ->where('data.items.0.name', 'বাংলা প্রকল্প')
            );

        $this->get('/projects/education?lang=bn')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('properties.total_count', 1)
                ->where('data.tag.slug', 'education')
                ->where('data.tag.name', 'শিক্ষা')
                ->where('data.tag.description', 'কমিউনিটির সঙ্গে পরিচালিত শিক্ষা প্রকল্প।')
                ->where('title', 'শিক্ষা')
                ->where('data.items.0.name', 'বাংলা প্রকল্প')
            );

        app()->setLocale('bn');
        $block = PageBlock::create([
            'page_id' => $bangla->id,
            'uuid' => (string) Str::uuid(),
            'type' => 'cards',
            'label' => 'Managed Bangla projects',
            'content' => [
                'content_source' => 'projects',
                'tag_slug' => 'education',
                'selection_mode' => 'automatic',
                'limit' => 3,
            ],
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ]);
        $managedItems = app(PageBlockContentResolver::class)->resolve($block)['items'];
        $this->assertSame('বাংলা প্রকল্প', $managedItems[0]['heading']);
        $this->assertSame('শিক্ষা', $managedItems[0]['status']);
    }

    public function test_page_builder_group_save_synchronizes_every_translation(): void
    {
        $admin = $this->makeOwner();
        $oldGroup = $this->makeTag('Old group', 'old-group');
        $newGroup = $this->makeTag('Livelihoods', 'livelihoods');
        $uuid = (string) Str::uuid();
        $english = $this->makePage($uuid, 'en', 'English project', 'english-project');
        $bangla = $this->makePage($uuid, 'bn', 'বাংলা প্রকল্প', 'bangla-project');
        PageTagModule::create([
            'uuid' => (string) Str::uuid(),
            'page_id' => $english->id,
            'tag_id' => $oldGroup->id,
        ]);

        $this->actingAs($admin, 'admin')->putJson(route('page.builder.update', $uuid), [
            'locale' => 'bn',
            'expected_version' => 0,
            'name' => 'বাংলা প্রকল্প',
            'sub_title' => 'বাংলা পরিচিতি',
            'status' => true,
            'publication_status' => 'published',
            'visibility' => 'public',
            'scheduled_for' => null,
            'tag_ids' => [$newGroup->id],
        ])->assertOk()
            ->assertJsonPath('page.tag_ids.0', $newGroup->id);

        foreach ([$english, $bangla] as $translation) {
            $this->assertSame(
                [$newGroup->id],
                $translation->pageTags()->pluck('tag_id')->map(fn ($id): int => (int) $id)->all()
            );
            $this->assertTrue((bool) $translation->fresh()->is_relationship);
        }

        $this->get('/projects/old-group?lang=bn')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('properties.total_count', 0));
        $this->get('/projects/livelihoods?lang=bn')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('properties.total_count', 1)
                ->where('data.items.0.uuid', $uuid)
            );
    }

    private function enableBangla(): void
    {
        TranslationLocale::whereKey('bn')->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
    }

    private function makePage(
        string $uuid,
        string $language,
        string $name,
        string $slug
    ): Page {
        return Page::create([
            'uuid' => $uuid,
            'name' => $name,
            'slug' => $slug,
            'sub_title' => 'Public project introduction.',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
            'language' => $language,
        ]);
    }

    private function makeTag(string $name, string $slug): Tag
    {
        return Tag::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => $slug,
            'description' => 'Browse ' . $name . ' projects.',
            'status' => 1,
        ]);
    }

    private function makeOwner(): Admin
    {
        $role = Role::create([
            'name' => 'Website owner',
            'is_owner' => true,
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);

        return Admin::create([
            'name' => 'Locale regression owner',
            'username' => 'locale-regression-owner',
            'email' => 'locale-regression@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }
}
