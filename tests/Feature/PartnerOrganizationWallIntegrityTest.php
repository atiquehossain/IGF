<?php

namespace Tests\Feature;

use App\Models\PageBlock;
use Database\Seeders\IgniteParityContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerOrganizationWallIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const BLOCK_UUID = '69000000-0000-4000-8000-000000000005';

    public function test_reference_partner_wall_has_the_expected_valid_logos_in_order(): void
    {
        $this->seed(IgniteParityContentSeeder::class);

        $block = PageBlock::query()->where('uuid', self::BLOCK_UUID)->firstOrFail();
        $items = $block->content['items'];
        $expected = [
            'Bangladesh Brand Forum' => '01-bangladesh-brand-forum.png',
            'TechHub Bangladesh' => '02-techhub-bangladesh.jpeg',
            'Daraz Bangladesh' => '03-daraz.png',
            'ICT Division' => '04-ict-division.png',
            'It’s Humanity Foundation' => '05-its-humanity-foundation.png',
            'Rtv' => '06-rtv.jpg',
            'Prothom Alo' => '07-prothom-alo.png',
            'ATN News' => '08-atn-news.png',
            'The Daily Star' => '09-the-daily-star.png',
            'Incepta Pharmaceuticals' => '10-incepta.png',
            'JCI Bangladesh' => '11-jci-bangladesh.jpeg',
            'JCI Dhaka West' => '12-jci-dhaka-west.png',
            'Matribhumi Group' => '13-matribhumi-group.jpeg',
            'PriyoShop' => '14-priyoshop.png',
            'Technohaven' => '15-technohaven.png',
            'The Business Standard' => '16-the-business-standard.png',
            'Maasranga Television' => '17-maasranga-tv.png',
            'Loop' => '18-loop.png',
            'Rotary Club of Banani Model Town' => '19-rotary-club-banani-model-town.jpeg',
            'What’s On Guide' => '20-whatson-guide.svg',
        ];

        $this->assertSame('Partner Organizations', $block->label);
        $this->assertSame('', $block->content['eyebrow']);
        $this->assertSame('Partner Organizations', $block->content['heading']);
        $this->assertSame('', $block->content['body']);
        $this->assertSame(array_keys($expected), array_column($items, 'heading'));

        foreach ($items as $item) {
            $this->assertSame($item['heading'], $item['image_alt'] === 'ICT Division, Government of Bangladesh'
                ? 'ICT Division'
                : ($item['image_alt'] === 'Technohaven Company Limited' ? 'Technohaven' : $item['image_alt']));
            $this->assertSame('', $item['url']);
            $filename = $expected[$item['heading']];
            $this->assertSame('/storage/media/ignite-live/partners/' . $filename, $item['image']);
            $this->assertFileExists(storage_path('app/public/media/ignite-live/partners/' . $filename));
            $this->assertGreaterThan(0, filesize(storage_path('app/public/media/ignite-live/partners/' . $filename)));
        }
    }

    public function test_upgrade_replaces_only_the_old_stock_partner_content_and_rolls_back_safely(): void
    {
        $this->seed(IgniteParityContentSeeder::class);
        $block = PageBlock::query()->where('uuid', self::BLOCK_UUID)->firstOrFail();
        $oldItems = [
            ['heading' => 'United Nations Volunteers', 'body' => '', 'image' => '/old/unv.jpg', 'url' => 'https://www.unv.org/'],
            ['heading' => 'VSO', 'body' => '', 'image' => '/old/vso.jpg', 'url' => 'https://www.vsointernational.org/'],
            ['heading' => 'The Daily Star', 'body' => '', 'image' => '/old/daily-star.jpg', 'url' => 'https://www.thedailystar.net/'],
            ['heading' => 'Samakal', 'body' => '', 'image' => '/old/samakal.jpg', 'url' => 'https://samakal.com/'],
            ['heading' => 'Banik Barta', 'body' => '', 'image' => '/old/banik-barta.jpg', 'url' => 'https://bonikbarta.net/'],
        ];
        $block->update([
            'label' => 'Partners and Supporters',
            'content' => [
                'eyebrow' => 'Working together',
                'heading' => 'Partners and supporters',
                'body' => 'Institutions and media organizations that have supported, amplified, or collaborated with Ignite’s work.',
                'items' => $oldItems,
            ],
            'settings' => [],
        ]);

        $migration = require database_path('migrations/2026_08_19_120400_match_reference_partner_organizations.php');
        $migration->up();

        $block->refresh();
        $this->assertSame('Partner Organizations', $block->label);
        $this->assertSame('', $block->content['eyebrow']);
        $this->assertSame('Partner Organizations', $block->content['heading']);
        $this->assertCount(20, $block->content['items']);
        $this->assertArrayHasKey('_migration_20260819_partner_wall', $block->settings);
        $this->assertArrayNotHasKey('_migration_20260819_partner_wall', $block->resolvedSettings());

        $content = $block->content;
        $content['heading'] = 'An editor’s custom heading';
        $block->update(['content' => $content]);
        $migration->down();

        $block->refresh();
        $this->assertSame('Partners and Supporters', $block->label);
        $this->assertSame('Working together', $block->content['eyebrow']);
        $this->assertSame('An editor’s custom heading', $block->content['heading']);
        $this->assertSame($oldItems, $block->content['items']);
        $this->assertArrayNotHasKey('_migration_20260819_partner_wall', $block->settings);
    }
}
