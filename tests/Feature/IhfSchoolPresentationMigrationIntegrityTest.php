<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IhfSchoolPresentationMigrationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_preserves_editor_changed_items_even_when_legacy_headings_still_match(): void
    {
        $page = $this->makePage();
        $initiativeItems = $this->legacyInitiativeItems();
        $initiativeItems[0] = array_merge($initiativeItems[0], [
            'body' => 'Editor-approved inclusive education copy.',
            'image' => '/storage/media/editor/initiative.jpg',
            'url' => '/page/editor-initiative',
            'link_label' => 'Read the editor story',
        ]);
        $contributionItems = $this->legacyContributionItems();
        $contributionItems[0] = array_merge($contributionItems[0], [
            'body' => 'Editor-approved sponsorship details.',
            'image' => '/storage/media/editor/sponsorship.jpg',
            'url' => '/page/editor-sponsorship',
            'link_label' => 'Use the editor action',
        ]);

        $initiatives = $this->makeBlock($page, [
            'uuid' => '69400000-0000-4000-8000-000000000003',
            'type' => 'cards',
            'label' => 'School Initiatives',
            'content' => [
                'variant' => 'initiatives',
                'heading' => 'Our initiatives at Ignite School',
                'items' => $initiativeItems,
            ],
        ]);
        $contributions = $this->makeBlock($page, [
            'uuid' => '69400000-0000-4000-8000-000000000005',
            'type' => 'cards',
            'label' => 'Ways to Contribute',
            'content' => [
                'variant' => 'contributions',
                'heading' => 'Choose a meaningful way to contribute',
                'items' => $contributionItems,
            ],
        ]);
        $stats = $this->makeBlock($page, [
            'uuid' => '69400000-0000-4000-8000-000000000002',
            'type' => 'stats',
            'content' => [
                'items' => [
                    ['value' => 'Playgroup–5', 'label' => 'Classes supported', 'icon' => 'people', 'accent' => 'editor-owned'],
                ],
            ],
        ]);

        $this->presentationMigration()->up();

        $this->assertSame($initiativeItems, $initiatives->fresh()->content['items']);
        $this->assertSame($contributionItems, $contributions->fresh()->content['items']);
        $this->assertSame([
            ['value' => 'Playgroup–5', 'label' => 'Classes supported', 'icon' => 'people', 'accent' => 'editor-owned'],
        ], $stats->fresh()->content['items']);
    }

    public function test_migration_upgrades_only_exact_seeded_payloads_and_uses_consistent_learner_wording(): void
    {
        $page = $this->makePage();
        $legacyIntro = '<p>Ignite School began in 2016 with 35 children. Today, the Bawnia campus supports nearly 120 learners, including children with additional needs, through free and inclusive education.</p><p>Every child is welcomed into a safe learning community where dignity, creativity, and practical support make regular participation possible.</p>';
        $intro = $this->makeBlock($page, [
            'uuid' => '69400000-0000-4000-8000-000000000001',
            'type' => 'media_text',
            'content' => ['body' => $legacyIntro],
        ]);
        $stats = $this->makeBlock($page, [
            'uuid' => '69400000-0000-4000-8000-000000000002',
            'type' => 'stats',
            'content' => [
                'heading' => 'A school community built around every learner',
                'items' => [
                    ['value' => '120+', 'label' => 'Current learners', 'icon' => 'child'],
                    ['value' => '35', 'label' => 'Children at launch', 'icon' => 'child'],
                    ['value' => '2016', 'label' => 'School founded', 'icon' => 'school'],
                    ['value' => 'Playgroup–5', 'label' => 'Classes supported', 'icon' => 'people'],
                ],
            ],
        ]);
        $initiatives = $this->makeBlock($page, [
            'uuid' => '69400000-0000-4000-8000-000000000003',
            'type' => 'cards',
            'content' => [
                'heading' => 'Our initiatives at Ignite School',
                'items' => $this->legacyInitiativeItems(),
            ],
        ]);
        $contributions = $this->makeBlock($page, [
            'uuid' => '69400000-0000-4000-8000-000000000005',
            'type' => 'cards',
            'content' => [
                'heading' => 'Choose a meaningful way to contribute',
                'items' => $this->legacyContributionItems(),
            ],
        ]);

        $this->presentationMigration()->up();

        $this->assertStringContainsString('<strong>nearly 120 learners</strong>', $intro->fresh()->content['body']);
        $this->assertSame([
            ['value' => 'Nearly 120', 'label' => 'Learners supported', 'icon' => 'child'],
            ['value' => '35', 'label' => 'Children at launch', 'icon' => 'child'],
            ['value' => '2016', 'label' => 'School founded', 'icon' => 'school'],
        ], $stats->fresh()->content['items']);
        $this->assertCount(6, $initiatives->fresh()->content['items']);
        $this->assertSame('Inclusive education', $initiatives->fresh()->content['items'][0]['heading']);
        $this->assertCount(5, $contributions->fresh()->content['items']);
        $this->assertSame('Partner with the school', $contributions->fresh()->content['items'][4]['heading']);
    }

    public function test_follow_up_wording_migration_updates_only_the_exact_unedited_stat_item(): void
    {
        $page = $this->makePage();
        $intermediateIntro = '<p><strong>Ignite School, Bawnia Campus</strong> began in <strong>2016 with 35 children</strong>. Today it supports nearly <strong>120 learners</strong>, including children with additional needs, through free inclusive education, learning materials, uniforms, nutritious meals, healthcare, creative activities, and practical life skills.</p>';
        $intro = $this->makeBlock($page, [
            'uuid' => '69400000-0000-4000-8000-000000000001',
            'type' => 'media_text',
            'content' => ['body' => $intermediateIntro, 'editor_note' => 'keep'],
        ]);
        $stats = $this->makeBlock($page, [
            'uuid' => '69400000-0000-4000-8000-000000000002',
            'type' => 'stats',
            'content' => [
                'items' => [
                    ['value' => '120+', 'label' => 'Current learners', 'icon' => 'child'],
                    ['value' => '120+', 'label' => 'Current learners', 'icon' => 'child', 'accent' => 'editor-owned'],
                    ['value' => '120+', 'label' => 'Editor learner wording', 'icon' => 'child'],
                ],
            ],
        ]);

        $this->wordingMigration()->up();

        $this->assertStringContainsString('<strong>nearly 120 learners</strong>', $intro->fresh()->content['body']);
        $this->assertSame('keep', $intro->fresh()->content['editor_note']);
        $this->assertSame([
            ['value' => 'Nearly 120', 'label' => 'Learners supported', 'icon' => 'child'],
            ['value' => '120+', 'label' => 'Current learners', 'icon' => 'child', 'accent' => 'editor-owned'],
            ['value' => '120+', 'label' => 'Editor learner wording', 'icon' => 'child'],
        ], $stats->fresh()->content['items']);
    }

    public function test_presentation_migration_preserves_order_when_an_editor_added_a_custom_block(): void
    {
        $page = $this->makePage();
        $blocks = [
            ['69400000-0000-4000-8000-000000000000', 1],
            ['69400000-0000-4000-8000-000000000001', 2],
            ['69400000-0000-4000-8000-000000000002', 3],
            ['69400000-0000-4000-8000-000000000003', 4],
            ['69400000-0000-4000-8000-000000000005', 5],
            ['69999999-0000-4000-8000-000000000099', 6],
            ['69400000-0000-4000-8000-000000000006', 7],
            ['69400000-0000-4000-8000-000000000004', 8],
        ];
        foreach ($blocks as [$uuid, $sortOrder]) {
            $this->makeBlock($page, [
                'uuid' => $uuid,
                'sort_order' => $sortOrder,
                'content' => ['editor_note' => $uuid],
            ]);
        }

        $this->presentationMigration()->up();

        $this->assertSame($blocks, PageBlock::query()
            ->where('page_id', $page->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['uuid', 'sort_order'])
            ->map(static fn (PageBlock $block): array => [$block->uuid, $block->sort_order])
            ->all());
    }

    private function presentationMigration(): object
    {
        return require database_path('migrations/2026_08_19_120300_match_ihf_school_landing_presentation.php');
    }

    private function wordingMigration(): object
    {
        return require database_path('migrations/2026_08_19_120500_reconcile_ignite_school_learner_wording.php');
    }

    private function makePage(): Page
    {
        return Page::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Ignite School migration test',
            'sub_title' => 'Migration integrity',
            'slug' => 'ignite-school-migration-' . Str::lower(Str::random(8)),
            'status' => 1,
            'language' => 'en',
        ]);
    }

    private function makeBlock(Page $page, array $overrides): PageBlock
    {
        return PageBlock::create(array_merge([
            'page_id' => $page->id,
            'uuid' => (string) Str::uuid(),
            'type' => 'rich_text',
            'label' => 'Migration test block',
            'content' => [],
            'settings' => [],
            'sort_order' => 1,
            'is_enabled' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
        ], $overrides));
    }

    private function legacyInitiativeItems(): array
    {
        return [
            ['heading' => 'Free education', 'body' => 'Inclusive teaching, books, school supplies, and structured learning from Playgroup through Class Five.', 'icon' => 'school', 'url' => ''],
            ['heading' => 'Nutrition and health', 'body' => 'Nutritious meals, preventive care, referrals, and practical wellbeing support.', 'icon' => 'health', 'url' => ''],
            ['heading' => 'Uniforms and essentials', 'body' => 'Uniforms, bags, learning materials, and essentials that help children participate with dignity.', 'icon' => 'report', 'url' => ''],
            ['heading' => 'Creative development', 'body' => 'Play, sports, arts, leadership, and life-skills activities that build confidence beyond the classroom.', 'icon' => 'heart', 'url' => ''],
        ];
    }

    private function legacyContributionItems(): array
    {
        return [
            ['heading' => 'Sponsor a child', 'body' => 'Help make education, nutrition, healthcare, uniforms, and learning materials more dependable for one learner.', 'icon' => 'child', 'url' => '/sponsor-child', 'link_label' => 'View sponsorship'],
            ['heading' => 'Donate to education', 'body' => 'Make a one-time contribution to Ignite’s education work and ongoing school support.', 'icon' => 'heart', 'url' => '/donate', 'link_label' => 'Make a donation'],
            ['heading' => 'Volunteer with Ignite', 'body' => 'Offer your time and skills through Ignite’s managed volunteer registration process.', 'icon' => 'people', 'url' => '/volunteer/register', 'link_label' => 'Register to volunteer'],
            ['heading' => 'Visit or partner', 'body' => 'Contact the Ignite team to discuss a school visit, institutional support, or another partnership idea.', 'icon' => 'map', 'url' => '/contact-us', 'link_label' => 'Contact the team'],
        ];
    }
}
