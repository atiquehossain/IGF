<?php

namespace Tests\Feature;

use Tests\TestCase;

class EventsNewsAdminBuilderUiTest extends TestCase
{
    public function test_simple_builder_exposes_a_guided_events_and_news_section(): void
    {
        $source = file_get_contents(resource_path('views/admin/page/builder-simple.blade.php'));
        $catalog = config('page-builder.simple_sections.events_news');

        $this->assertSame('Upcoming events + featured news', $catalog['label']);
        $this->assertSame(
            'Show a managed list of future events beside one featured news story.',
            $catalog['description']
        );
        $this->assertStringContainsString('data-add-section="{{ $type }}"', $source);
        $this->assertStringContainsString('function renderEventsNewsEditor(block)', $source);
        $this->assertStringContainsString("contentManagementLinks(block,'events_news')", $source);
        $this->assertStringContainsString("kind === 'news' ? 'news_options' : 'event_options'", $source);
        $this->assertStringContainsString('Array.isArray(contentOptions[key])', $source);
        $this->assertStringContainsString("textField('eyebrow','Small heading'", $source);
        $this->assertStringContainsString("textField('body','Introduction'", $source);
        $this->assertStringContainsString("selectField('events_selection_mode'", $source);
        $this->assertStringContainsString('data-events-news-event-toggle', $source);
        $this->assertStringContainsString('data-events-news-event-move', $source);
        $this->assertStringContainsString('data-content-key="featured_news_id"', $source);
        $this->assertStringContainsString("linkField('events_view_all_url'", $source);
        $this->assertStringContainsString("linkField('news_view_all_url'", $source);
        $this->assertStringContainsString("linkField('cta_url'", $source);
        $this->assertStringContainsString("if (block.type === 'events_news') return renderEventsNewsEditor(block);", $source);
        $this->assertStringContainsString("if (block.type === 'events_news') {", $source);
        $this->assertStringContainsString('aria-label="Upcoming events preview"', $source);
        $this->assertStringContainsString('aria-label="Featured news preview"', $source);
        $this->assertStringContainsString('simple-events-news-feature', $source);
        $this->assertStringContainsString('simple-events-news-intro', $source);
        $this->assertStringContainsString('.simple-events-news-cta{display:flex;width:100%;min-height:48px;', $source);
        $this->assertStringContainsString('padding:0 18px;border-radius:14px;', $source);
        $this->assertStringContainsString('<span aria-hidden="true">→</span></span>', $source);
        $this->assertStringContainsString('[data-viewport=mobile] .simple-preview-events-news', $source);
        $this->assertStringContainsString('entry.add_url || entry.create_url', $source);
        $this->assertStringContainsString('there are no IDs or JSON to enter', $source);
        $this->assertStringContainsString('Automatic — highest-priority published news', $source);
        $this->assertStringContainsString('const automaticNews = [...news].sort', $source);
        $this->assertStringContainsString('Number(right?.featured_order || 0)', $source);
        $this->assertStringContainsString('Number(right?.published_at || 0)', $source);
        $this->assertStringContainsString('Number(right?.sort_id || 0)', $source);
        $this->assertStringContainsString('item.location || item.venue || item.event_attendance_mode', $source);
        $this->assertStringContainsString("const eventsNewsTimeZone = @json(config('app.timezone'));", $source);
        $this->assertStringContainsString('timeZone:eventsNewsTimeZone', $source);
    }

    public function test_advanced_builder_keeps_the_same_safe_controls_and_preview_contract(): void
    {
        $source = file_get_contents(resource_path('views/admin/page/builder.blade.php'));

        $this->assertStringContainsString('@foreach ($blockTypes as $type => $label)', $source);
        $this->assertStringContainsString('function renderEventsNewsInspector(block)', $source);
        $this->assertStringContainsString("contentManagementLinks(block,'events_news')", $source);
        $this->assertStringContainsString("kind === 'news' ? 'news_options' : 'event_options'", $source);
        $this->assertStringContainsString('Array.isArray(contentOptions[key])', $source);
        $this->assertStringContainsString("eventsNewsField('eyebrow','Small heading'", $source);
        $this->assertStringContainsString("eventsNewsField('body','Introduction'", $source);
        $this->assertStringContainsString("managedSelect('events_selection_mode'", $source);
        $this->assertStringContainsString('data-events-news-event-toggle', $source);
        $this->assertStringContainsString('data-events-news-event-move', $source);
        $this->assertStringContainsString('data-content-key="featured_news_id"', $source);
        $this->assertStringContainsString("eventsNewsField('events_view_all_url'", $source);
        $this->assertStringContainsString("eventsNewsField('news_view_all_url'", $source);
        $this->assertStringContainsString("eventsNewsField('cta_url'", $source);
        $this->assertStringContainsString("block.type === 'events_news'", $source);
        $this->assertStringContainsString('aria-label="Upcoming events preview"', $source);
        $this->assertStringContainsString('aria-label="Featured news preview"', $source);
        $this->assertStringContainsString('igf-preview-events-news__feature', $source);
        $this->assertStringContainsString('igf-preview-events-news__intro', $source);
        $this->assertStringContainsString('.igf-preview-events-news__cta{display:flex;width:100%;min-height:48px;', $source);
        $this->assertStringContainsString('padding:0 18px;border-radius:14px;', $source);
        $this->assertStringContainsString('<span aria-hidden="true">→</span></span>', $source);
        $this->assertStringContainsString('@container (max-width:760px)', $source);
        $this->assertStringContainsString('entry.add_url || entry.create_url', $source);
        $this->assertStringContainsString('there are no IDs or JSON to enter', $source);
        $this->assertStringContainsString('Automatic — highest-priority published news', $source);
        $this->assertStringContainsString('const automaticNews = [...news].sort', $source);
        $this->assertStringContainsString('Number(right?.featured_order || 0)', $source);
        $this->assertStringContainsString('Number(right?.published_at || 0)', $source);
        $this->assertStringContainsString('Number(right?.sort_id || 0)', $source);
        $this->assertStringContainsString('item.location || item.venue || item.event_attendance_mode', $source);
        $this->assertStringContainsString("const eventsNewsTimeZone = @json(config('app.timezone'));", $source);
        $this->assertStringContainsString('timeZone:eventsNewsTimeZone', $source);
    }
}
