<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PageBuilderController;
use App\Models\Division;
use App\Models\LatestNews;
use App\Models\PageBlock;
use App\Services\PageBlockContentResolver;
use App\Support\PageBuilderElementManifest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InteractiveTeamDirectoryBackendTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_resolver_and_builder_resources_expose_a_stable_division_contract(): void
    {
        app()->setLocale('en');

        $division = Division::query()->where('name', 'Chattogram')->firstOrFail();
        $member = LatestNews::create([
            'name' => 'Regional program lead',
            'type' => 'our-members',
            'description' => 'Program Director',
            'division_id' => $division->id,
            'language' => 'en',
            'order_by' => 500,
            'status' => 1,
        ]);

        $resolved = app(PageBlockContentResolver::class)->resolve(new PageBlock([
            'type' => 'team',
            'content' => [
                'content_source' => 'team',
                'team_presentation' => 'map_directory',
                'show_map' => true,
                'map_position' => 'right',
                'profile_behavior' => 'panel',
                'limit' => 12,
            ],
        ]));
        $profile = collect($resolved['items'])->firstWhere('id', $member->id);

        $this->assertSame('directory_map', $resolved['team_presentation']);
        $this->assertTrue($resolved['show_map']);
        $this->assertSame('right', $resolved['map_position']);
        $this->assertSame('panel', $resolved['profile_behavior']);
        $this->assertSame($division->id, $profile['division_id']);
        $this->assertSame('chattogram', $profile['division_slug']);
        $this->assertSame('Chattogram', $profile['division_name']);
        $this->assertSame('Chattogram', $profile['division_label']);
        $this->assertSame(
            route('frontend.heroes.division', ['division' => 'chattogram']),
            $profile['division_url']
        );
        $this->assertSame([
            'id' => $division->id,
            'slug' => 'chattogram',
            'name' => 'Chattogram',
            'label' => 'Chattogram',
            'url' => route('frontend.heroes.division', ['division' => 'chattogram']),
        ], $profile['division']);

        app()->setLocale('bn');
        $showcase = app(PageBlockContentResolver::class)->resolve(new PageBlock([
            'type' => 'team',
            'content' => [
                'content_source' => 'team',
                'team_presentation' => 'heroes_showcase',
                'limit' => 12,
            ],
        ]));
        $showcaseProfile = collect($showcase['items'])->firstWhere('id', $member->id);
        $this->assertSame(
            route('frontend.heroes.division', ['division' => 'chattogram', 'lang' => 'bn']),
            $showcaseProfile['division_url']
        );
        $this->assertSame($showcaseProfile['division_url'], $showcaseProfile['division']['url']);
        app()->setLocale('en');

        $legacy = app(PageBlockContentResolver::class)->resolve(new PageBlock([
            'type' => 'team',
            'content' => ['content_source' => 'team', 'limit' => 12],
        ]));
        $this->assertSame('cards', $legacy['team_presentation']);
        $this->assertTrue($legacy['animation_enabled']);
        $this->assertTrue($legacy['autoplay']);

        $division->update(['name' => 'Chattogram Programs']);
        $options = app(PageBuilderController::class)->reusableBlockEditorOptions('en');
        $option = collect(data_get($options, 'items.team'))->firstWhere('value', (string) $member->id);
        $this->assertSame($division->id, $option['division_id']);
        $this->assertSame('chattogram', $option['division_slug']);
        $this->assertSame('Chattogram Programs', $option['division_name']);
        $this->assertSame(config('page-builder.team_presentations'), data_get($options, 'presentations.team'));
        $this->assertSame(
            config('page-builder.team_map_position_options'),
            data_get($options, 'team_directory.map_positions')
        );
        $this->assertSame(
            config('page-builder.team_profile_behavior_options'),
            data_get($options, 'team_directory.profile_behaviors')
        );
        $map = data_get($options, 'team_directory.map');
        $this->assertSame('0 0 420 560', $map['viewBox']);
        $this->assertCount(8, $map['divisions']);
        $this->assertSame([
            'rangpur',
            'rajshahi',
            'mymensingh',
            'sylhet',
            'khulna',
            'dhaka',
            'barishal',
            'chattogram',
        ], array_column($map['divisions'], 'key'));
        $this->assertArrayNotHasKey('metadata', $map);
        foreach ($map['divisions'] as $mapDivision) {
            $this->assertSame(
                ['key', 'names', 'path', 'labelX', 'labelY'],
                array_keys($mapDivision)
            );
            $this->assertNotSame('', $mapDivision['names']['en']);
            $this->assertNotSame('', $mapDivision['names']['bn']);
            $this->assertMatchesRegularExpression('/^M[MmZzLlHhVvCcSsQqTtAaEe0-9+.,\s-]*$/', $mapDivision['path']);
            $this->assertIsNumeric($mapDivision['labelX']);
            $this->assertIsNumeric($mapDivision['labelY']);
        }
    }

    public function test_team_directory_controls_are_allowlisted_and_legacy_aliases_are_canonicalized(): void
    {
        $controller = app(PageBuilderController::class);
        $content = $controller->validateReusableBlockPayload('team', [
            'content_source' => 'team',
            'team_presentation' => 'map_directory',
            'show_map' => false,
            'map_position' => 'right',
            'profile_behavior' => 'modal',
        ], 'en');

        $this->assertSame('directory_map', $content['team_presentation']);
        $this->assertFalse($content['show_map']);
        $this->assertSame('right', $content['map_position']);
        $this->assertSame('modal', $content['profile_behavior']);

        foreach (['list', 'compact'] as $legacyPresentation) {
            $legacyContent = $controller->validateReusableBlockPayload('team', [
                'content_source' => 'team',
                'team_presentation' => $legacyPresentation,
            ], 'en');
            $this->assertSame($legacyPresentation, $legacyContent['team_presentation']);
        }
        $this->assertSame([
            'cards',
            'list',
            'compact',
            'directory_map',
            'heroes_showcase',
        ], array_keys(config('page-builder.team_presentations')));
        $this->assertTrue(config('page-builder.default_content.team.animation_enabled'));
        $this->assertTrue(config('page-builder.default_content.team.autoplay'));

        $teamElement = PageBuilderElementManifest::get('team');
        $this->assertSame('cards', $teamElement['defaults']['presentation']);
        $this->assertSame(true, $teamElement['defaults']['show_map']);
        $this->assertSame('left', $teamElement['defaults']['map_position']);
        $this->assertSame('panel', $teamElement['defaults']['profile_behavior']);
        $this->assertArrayHasKey('directory_map', $teamElement['fields']['presentation']['options']);
        $this->assertArrayHasKey('heroes_showcase', $teamElement['fields']['presentation']['options']);
        $this->assertTrue($teamElement['defaults']['animation_enabled']);
        $this->assertTrue($teamElement['defaults']['autoplay']);

        $showcase = $controller->validateReusableBlockPayload('team', [
            'content_source' => 'team',
            'team_presentation' => 'heroes_showcase',
            'show_map' => true,
            'map_position' => 'left',
            'profile_behavior' => 'panel',
            'animation_enabled' => false,
            'autoplay' => false,
        ], 'en');
        $this->assertSame('heroes_showcase', $showcase['team_presentation']);
        $this->assertFalse($showcase['animation_enabled']);
        $this->assertFalse($showcase['autoplay']);

        try {
            $controller->validateReusableBlockPayload('team', [
                'content_source' => 'team',
                'team_presentation' => 'directory_map',
                'map_position' => 'floating',
                'profile_behavior' => 'execute-script',
            ], 'en');
            $this->fail('Unsafe directory configuration should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('content.map_position', $exception->errors());
            $this->assertArrayHasKey('content.profile_behavior', $exception->errors());
        }

        try {
            $controller->validateReusableBlockPayload('rich_text', [
                'team_presentation' => 'directory_map',
                'show_map' => true,
            ], 'en');
            $this->fail('Team directory settings should not be accepted by another section type.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('content.team_presentation', $exception->errors());
            $this->assertArrayHasKey('content.show_map', $exception->errors());
        }
    }
}
