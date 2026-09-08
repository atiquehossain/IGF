<?php

namespace Tests\Feature;

use Tests\TestCase;

class PageBuilderTeamDirectoryEditorTest extends TestCase
{
    public function test_both_admin_editors_expose_the_canonical_team_directory_controls(): void
    {
        foreach ($this->builderSources() as $source) {
            $this->assertStringContainsString("directory_map: 'Interactive directory + Bangladesh map'", $source);
            $this->assertStringContainsString("heroes_showcase: 'Meet the Heroes showcase'", $source);
            $this->assertStringContainsString("'team_presentation'", $source);
            $this->assertStringContainsString('data-content-key="show_map"', $source);
            $this->assertStringContainsString("'map_position'", $source);
            $this->assertStringContainsString("'profile_behavior'", $source);
            $this->assertStringContainsString("panel: 'Show the profile in the directory'", $source);
            $this->assertStringContainsString("modal: 'Open the profile in a dialog'", $source);
            $this->assertStringContainsString("link: 'Open the profile link'", $source);
        }
    }

    public function test_both_admin_editors_preview_the_meet_the_heroes_showcase_with_existing_safe_controls(): void
    {
        foreach ($this->builderSources() as $source) {
            $this->assertStringContainsString('function renderTeamHeroesPreview(block, items)', $source);
            $this->assertStringContainsString('aria-label="Meet the Heroes showcase preview"', $source);
            $this->assertStringContainsString('aria-label="Profile selector preview"', $source);
            $this->assertStringContainsString('aria-label="Social links preview"', $source);
            $this->assertStringContainsString('aria-label="Bangladesh map preview"', $source);
            $this->assertStringContainsString('data-content-key="animation_enabled"', $source);
            $this->assertStringContainsString('data-content-key="autoplay"', $source);
            $this->assertStringContainsString('data-content-key="profile_behavior" value="panel"', $source);
            $this->assertStringContainsString('teamPreviewMapDivisions.map(division=>`<g data-division=', $source);
            $this->assertStringContainsString('biographyParagraphs.map(paragraph=>`<p>', $source);
            $this->assertStringContainsString("grid-template-columns:repeat(2,minmax(0,1fr))", $source);
            $this->assertStringContainsString("grid-template-areas:'content map'", $source);
            $this->assertStringContainsString('background:transparent;box-shadow:none;color:', $source);
            $this->assertStringNotContainsString('<text x=', $source);
            $this->assertStringNotContainsString('const teamHeroesMapPaths', $source);

            $showcaseStart = strpos($source, 'function renderTeamHeroesPreview(block, items)');
            $showcaseEnd = strpos($source, 'function ', $showcaseStart + 20);
            $showcaseSource = substr($source, $showcaseStart, $showcaseEnd - $showcaseStart);
            $this->assertStringContainsString('${introduction}${selectors?', $showcaseSource);
            $this->assertStringContainsString('${profile}</div>', $showcaseSource);
            $this->assertLessThan(
                strpos($showcaseSource, '${profile}</div>'),
                strpos($showcaseSource, '${introduction}${selectors?')
            );
        }
    }

    public function test_both_admin_previews_explain_the_map_and_profile_behavior_accessibly(): void
    {
        foreach ($this->builderSources() as $source) {
            $this->assertStringContainsString('function renderTeamDirectoryPreview(block, items)', $source);
            $this->assertStringContainsString('contentOptions.team_directory?.map', $source);
            $this->assertStringContainsString('teamPreviewMapDivisions.map(division=>`<g data-division=', $source);
            $this->assertStringContainsString('escapeHtml(division.path)', $source);
            $this->assertStringNotContainsString('const teamPreviewMapPaths', $source);
            $this->assertStringNotContainsString('M85 35 L180 24 L205 93', $source);
            $this->assertStringContainsString('role="region" aria-label="Interactive directory and Bangladesh map preview"', $source);
            $this->assertStringContainsString('aria-label="Interactive directory and Bangladesh map preview"', $source);
            $this->assertStringContainsString('role="img" aria-labelledby=', $source);
            $this->assertStringContainsString('<title>Bangladesh division map preview</title>', $source);
            $this->assertStringContainsString('role="group" aria-label="Division filter preview"', $source);
            $this->assertStringContainsString('aria-label="Division filter preview"', $source);
            $this->assertStringContainsString('Profile details open in an accessible dialog.', $source);
            $this->assertStringContainsString('The map is hidden; division filters remain available.', $source);
        }
    }

    public function test_team_directory_is_not_advertised_as_a_visual_layout_element(): void
    {
        $source = file_get_contents(resource_path('views/admin/page/builder-simple.blade.php'));

        $this->assertStringContainsString("filter(element => element?.mode === 'static')", $source);
        $this->assertStringNotContainsString("element?.mode === 'managed'", $source);
    }

    /** @return array<string, string> */
    private function builderSources(): array
    {
        return [
            'simple' => file_get_contents(resource_path('views/admin/page/builder-simple.blade.php')),
            'advanced' => file_get_contents(resource_path('views/admin/page/builder.blade.php')),
        ];
    }
}
