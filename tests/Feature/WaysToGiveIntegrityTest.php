<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\DonationType;
use App\Models\MenuAction;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Services\PageBlockContentResolver;
use App\Services\TranslationCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WaysToGiveIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_resolver_preserves_manual_order_omits_unavailable_causes_and_localizes_special_cards(): void
    {
        app()->setLocale('bn');
        $page = $this->page(['language' => 'bn']);
        $this->page([
            'language' => 'bn',
            'name' => 'যাকাত',
            'slug' => 'zakat',
        ]);
        DonationType::create([
            'name' => 'Zakat Fund',
            'slug' => 'zakat-fund',
            'description' => 'Managed Zakat support.',
            'purpose_key' => 'zakat',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Zakat Fund',
            'status' => 1,
        ]);
        $active = DonationType::create([
            'name' => 'Education Fund',
            'slug' => 'education-fund',
            'description' => 'Managed education support.',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'Education Fund',
            'status' => 1,
        ]);
        $unavailableProjectUuid = (string) Str::uuid();
        $unavailable = DonationType::create([
            'name' => 'Unavailable project',
            'slug' => 'unavailable-project',
            'description' => 'This must not be advertised.',
            'destination_type' => 'page',
            'destination_page_uuid' => $unavailableProjectUuid,
            'status' => 1,
        ]);
        $translations = app(TranslationCenterService::class);
        $destinationRow = $translations->rows('en', 'bn')->first(fn (array $row) =>
            ($row['identity']['type'] ?? null) === 'content_overlay'
            && ($row['identity']['model'] ?? null) === 'donation_cause'
            && ($row['identity']['identity'] ?? null) === $active->uuid
            && ($row['identity']['field'] ?? null) === 'destination_name'
        );
        $this->assertNotNull($destinationRow);
        $translations->save('en', 'bn', [[
            'key' => $destinationRow['key'],
            'precondition' => $destinationRow['precondition'],
            'value' => 'শিক্ষা সহায়তা তহবিল',
        ]], null);

        foreach ([
            ['group' => 'sponsor_page', 'key' => 'eyebrow', 'value' => 'শিশু সহায়তা'],
            ['group' => 'sponsor_page', 'key' => 'title', 'value' => 'একটি শিশুর পাশে দাঁড়ান'],
            ['group' => 'sponsor_page', 'key' => 'introduction', 'value' => 'শিক্ষা ও যত্নে নিয়মিত সহায়তা করুন।'],
            ['group' => 'sponsor_page', 'key' => 'hero_cta_label', 'value' => 'সহায়তা শুরু করুন'],
            ['group' => 'sponsor_page', 'key' => 'monthly_period_label', 'value' => 'প্রতি শিশু, প্রতি মাসে'],
            ['group' => 'zakat_calculator', 'key' => 'eyebrow', 'value' => 'যাকাত'],
            ['group' => 'zakat_calculator', 'key' => 'title', 'value' => 'আপনার যাকাত হিসাব করুন'],
            ['group' => 'zakat_calculator', 'key' => 'introduction', 'value' => 'নিসাব বেছে নিয়ে একটি নিরাপদ হিসাব দেখুন।'],
            ['group' => 'zakat_calculator', 'key' => 'donate_label', 'value' => 'যাকাত দিন'],
        ] as $setting) {
            SiteSetting::create($setting + [
                'locale' => 'bn',
                'type' => 'text',
                'is_public' => true,
            ]);
        }

        $block = $this->block($page, [
            'type' => 'ways_to_give',
            'content' => [
                'heading' => 'সহায়তার উপায়',
                'layout' => 'card_grid',
                'selection_mode' => 'manual',
                'selected_items' => [
                    'sponsor',
                    'cause:' . $unavailable->uuid,
                    'cause:' . $active->uuid,
                    'zakat',
                ],
                'project_uuid' => '',
                'link_label' => 'এখনই দিন',
            ],
        ]);

        $items = app(PageBlockContentResolver::class)->resolve($block)['items'];

        $this->assertSame(['sponsor', 'cause:' . $active->uuid, 'zakat'], array_column($items, 'key'));
        $this->assertSame('একটি শিশুর পাশে দাঁড়ান', $items[0]['heading']);
        $this->assertSame('সহায়তা শুরু করুন', $items[0]['link_label']);
        $this->assertSame('BDT 1,500 · প্রতি শিশু, প্রতি মাসে', $items[0]['destination']);
        $this->assertSame('/donate?cause=education-fund', $items[1]['url']);
        $this->assertSame('শিক্ষা সহায়তা তহবিল', $items[1]['destination']);
        $this->assertSame('আপনার যাকাত হিসাব করুন', $items[2]['heading']);
        $this->assertSame('যাকাত দিন', $items[2]['link_label']);
        $this->assertFalse(collect($items)->contains(fn (array $item) => str_contains($item['url'], 'unavailable-project')));
    }

    public function test_automatic_and_manual_zakat_cards_are_omitted_until_page_and_cause_are_both_operational(): void
    {
        $page = $this->page();
        $block = $this->block($page, [
            'type' => 'ways_to_give',
            'content' => array_merge(config('page-builder.default_content.ways_to_give'), [
                'selection_mode' => 'automatic',
                'selected_items' => [],
            ]),
        ]);
        $automatic = app(PageBlockContentResolver::class)->resolve($block)['items'];
        $this->assertNotContains('zakat', array_column($automatic, 'key'));

        $block->update(['content' => array_merge($block->content, [
            'selection_mode' => 'manual',
            'selected_items' => ['zakat', 'sponsor'],
        ])]);
        $manual = app(PageBlockContentResolver::class)->resolve($block->fresh())['items'];
        $this->assertSame(['sponsor'], array_column($manual, 'key'));
    }

    public function test_project_context_is_accepted_only_for_one_compatible_managed_cause_in_a_single_destination_layout(): void
    {
        $admin = $this->admin(['page.builder.create', 'page.builder.edit']);
        $editorPage = $this->page();
        $category = $this->category('Education');
        $otherCategory = $this->category('Health');
        $project = $this->page(['category_id' => $category->id, 'name' => 'Learning centre']);
        $unrelatedProject = $this->page(['category_id' => $otherCategory->id, 'name' => 'Health clinic']);
        $cause = DonationType::create([
            'name' => 'Education',
            'slug' => 'education',
            'description' => 'Support education.',
            'destination_type' => 'category',
            'destination_category_uuid' => $category->uuid,
            'status' => 1,
        ]);
        $secondCause = DonationType::create([
            'name' => 'School meals',
            'slug' => 'test-school-meals',
            'description' => 'Support school meals.',
            'destination_type' => 'restricted_fund',
            'destination_name' => 'School Meals Fund',
            'status' => 1,
        ]);

        $valid = $this->waysPayload([
            'layout' => 'single_cta',
            'selection_mode' => 'manual',
            'selected_items' => ['cause:' . $cause->uuid],
            'project_uuid' => $project->uuid,
        ]);

        $this->actingAs($admin, 'admin')
            ->postJson(route('page.builder.block.store', $editorPage->uuid), $this->withEditorVersion($editorPage, $valid))
            ->assertCreated()
            ->assertJsonPath('block.content.project_uuid', $project->uuid);

        foreach ([
            ['layout' => 'card_grid', 'selection_mode' => 'manual', 'selected_items' => ['cause:' . $cause->uuid], 'project_uuid' => $project->uuid],
            ['layout' => 'banner', 'selection_mode' => 'automatic', 'selected_items' => [], 'project_uuid' => $project->uuid],
            ['layout' => 'banner', 'selection_mode' => 'manual', 'selected_items' => ['zakat'], 'project_uuid' => $project->uuid],
            ['layout' => 'banner', 'selection_mode' => 'manual', 'selected_items' => ['cause:' . $cause->uuid, 'cause:' . $secondCause->uuid], 'project_uuid' => $project->uuid],
            ['layout' => 'banner', 'selection_mode' => 'manual', 'selected_items' => ['cause:' . $cause->uuid], 'project_uuid' => $unrelatedProject->uuid],
        ] as $invalidContent) {
            $this->actingAs($admin, 'admin')
                ->postJson(
                    route('page.builder.block.store', $editorPage->uuid),
                    $this->withEditorVersion($editorPage, $this->waysPayload($invalidContent))
                )
                ->assertUnprocessable()
                ->assertJsonValidationErrors('content.project_uuid');
        }
    }

    public function test_zakat_eligibility_requires_financial_permission_syncs_all_locales_and_cannot_clear_an_active_fixed_destination(): void
    {
        $pageUuid = (string) Str::uuid();
        $english = $this->page(['uuid' => $pageUuid, 'language' => 'en', 'is_zakat_eligible' => false]);
        $bangla = $this->page([
            'uuid' => $pageUuid,
            'language' => 'bn',
            'slug' => 'bn-' . Str::lower(Str::random(8)),
            'is_zakat_eligible' => true,
        ]);
        $ordinaryEditor = $this->admin(['page.builder.edit'], 'ordinary-page-editor');

        // Even submitting the current English value would overwrite the
        // divergent Bangla sibling, so the server must compare every locale.
        $this->actingAs($ordinaryEditor, 'admin')
            ->putJson(route('page.builder.simple.save', $pageUuid), $this->withEditorVersion($english, [
                'locale' => 'en',
                'page' => [
                    'name' => $english->name,
                    'publication_status' => 'published',
                    'is_zakat_eligible' => false,
                ],
            ]))
            ->assertForbidden();

        $fundingEditor = $this->admin(['page.builder.edit', 'donationType.edit'], 'funding-editor');
        $this->actingAs($fundingEditor, 'admin')
            ->putJson(route('page.builder.simple.save', $pageUuid), $this->withEditorVersion($english, [
                'locale' => 'en',
                'page' => [
                    'name' => $english->name,
                    'publication_status' => 'published',
                    'is_zakat_eligible' => true,
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('page.is_zakat_eligible', true);

        $this->assertTrue((bool) $english->fresh()->is_zakat_eligible);
        $this->assertTrue((bool) $bangla->fresh()->is_zakat_eligible);
        $this->assertSame(1, (int) $english->fresh()->editor_version);
        $this->assertSame(1, (int) $bangla->fresh()->editor_version);

        // Both locale editors originally loaded generation zero. A save in
        // English changed a logical, cross-language funding flag, so the stale
        // Bangla editor must not be allowed to overwrite that change.
        $this->actingAs($fundingEditor, 'admin')
            ->putJson(route('page.builder.simple.save', $pageUuid), [
                'locale' => 'bn',
                'expected_version' => 0,
                'page' => [
                    'name' => $bangla->name,
                    'publication_status' => 'published',
                    'is_zakat_eligible' => false,
                ],
            ])
            ->assertStatus(409);

        $this->assertTrue((bool) $english->fresh()->is_zakat_eligible);
        $this->assertTrue((bool) $bangla->fresh()->is_zakat_eligible);
        $this->assertSame(1, (int) $english->fresh()->editor_version);
        $this->assertSame(1, (int) $bangla->fresh()->editor_version);

        DonationType::create([
            'name' => 'Zakat',
            'slug' => 'zakat',
            'purpose_key' => 'zakat',
            'description' => 'Managed Zakat giving.',
            'destination_type' => 'page',
            'destination_page_uuid' => $pageUuid,
            'status' => 1,
        ]);

        $this->actingAs($fundingEditor, 'admin')
            ->putJson(route('page.builder.simple.save', $pageUuid), $this->withEditorVersion($english, [
                'locale' => 'en',
                'page' => [
                    'name' => $english->name,
                    'publication_status' => 'published',
                    'is_zakat_eligible' => false,
                ],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_zakat_eligible');

        $this->assertTrue((bool) $english->fresh()->is_zakat_eligible);
        $this->assertTrue((bool) $bangla->fresh()->is_zakat_eligible);
    }

    public function test_simple_and_advanced_builder_cannot_hide_an_active_fixed_donation_destination(): void
    {
        $admin = $this->admin(['page.builder.edit', 'page.status'], 'publishing-editor');
        $page = $this->page(['name' => 'Fixed giving project']);
        DonationType::create([
            'name' => 'Fixed project appeal',
            'slug' => 'fixed-project-appeal',
            'description' => 'Support this exact project.',
            'destination_type' => 'page',
            'destination_page_uuid' => $page->uuid,
            'status' => 1,
        ]);

        $this->actingAs($admin, 'admin')
            ->putJson(route('page.builder.simple.save', $page->uuid), $this->withEditorVersion($page, [
                'locale' => 'en',
                'page' => [
                    'name' => $page->name,
                    'publication_status' => 'draft',
                ],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('publication_status');

        $this->actingAs($admin, 'admin')
            ->putJson(route('page.builder.update', $page->uuid), $this->withEditorVersion($page, [
                'locale' => 'en',
                'name' => $page->name,
                'sub_title' => $page->sub_title,
                'status' => true,
                'publication_status' => 'published',
                'visibility' => 'unlisted',
                'scheduled_for' => null,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('publication_status');

        $this->actingAs($admin, 'admin')
            ->putJson(route('page.builder.update', $page->uuid), $this->withEditorVersion($page, [
                'locale' => 'en',
                'name' => $page->name,
                'sub_title' => $page->sub_title,
                'status' => true,
                'publication_status' => 'scheduled',
                'visibility' => 'public',
                'scheduled_for' => now()->addDay()->format('Y-m-d H:i:s'),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('publication_status');

        $this->assertSame('published', $page->fresh()->publication_status);
        $this->assertSame('public', $page->fresh()->visibility);
    }

    private function waysPayload(array $content): array
    {
        return [
            'locale' => 'en',
            'type' => 'ways_to_give',
            'label' => 'Ways to Give',
            'content' => array_merge(config('page-builder.default_content.ways_to_give'), $content),
            'settings' => [],
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ];
    }

    /**
     * Attach the optimistic-lock token a freshly opened editor would send.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withEditorVersion(Page $page, array $payload): array
    {
        return ['expected_version' => (int) $page->fresh()->editor_version] + $payload;
    }

    private function admin(array $permissions, string $username = 'ways-editor'): Admin
    {
        $role = Role::create([
            'name' => 'Ways to Give editor',
            'permission' => '',
            'actionPermission' => '',
            'serial' => '[]',
            'status' => 1,
        ]);
        $actions = MenuAction::query()->whereIn('link', $permissions)->get();
        $this->assertSame(count($permissions), $actions->count(), 'A required permission is not registered.');
        $role->update(['actionPermission' => $actions->pluck('id')->implode(',')]);

        return Admin::create([
            'name' => 'Ways editor',
            'username' => $username,
            'email' => $username . '@example.test',
            'role' => (string) $role->id,
            'status' => 1,
            'password' => bcrypt('test-password'),
            'must_change_password' => false,
        ]);
    }

    private function category(string $name): Category
    {
        return Category::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(5)),
            'language' => 'en',
            'status' => 1,
        ]);
    }

    private function page(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Giving page',
            'sub_title' => 'Managed giving page.',
            'slug' => 'giving-' . Str::lower(Str::random(8)),
            'language' => 'en',
            'status' => 1,
            'publication_status' => 'published',
            'visibility' => 'public',
            'is_funding_project' => true,
        ], $overrides));
    }

    private function block(Page $page, array $overrides = []): PageBlock
    {
        return PageBlock::create(array_merge([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'type' => 'rich_text',
            'label' => 'Giving section',
            'content' => ['body' => 'Managed content'],
            'settings' => [],
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ], $overrides));
    }
}
