<?php

return [
    'block_types' => [
        'hero' => 'Hero banner',
        'stats' => 'Impact statistics',
        'rich_text' => 'Rich text',
        'media_text' => 'Media and text',
        'cards' => 'Card grid',
        'ways_to_give' => 'Ways to Give',
        'causes' => 'Programs and causes',
        'events' => 'Upcoming events',
        'events_news' => 'Upcoming events + featured news',
        'testimonials' => 'Testimonials',
        'team' => 'Leadership and team',
        'partners' => 'Partner organizations',
        'faq' => 'Frequently asked questions',
        'timeline' => 'Timeline or process',
        'gallery' => 'Photo gallery',
        'video' => 'Video',
        'cta' => 'Call to action',
        'newsletter' => 'Newsletter signup',
        'layout' => 'Visual layout',
        'spacer' => 'Spacing',
        'custom_html' => 'Custom HTML',
    ],

    'simple_sections' => [
        'hero' => ['label' => 'Hero banner', 'icon' => 'fa-picture-o', 'description' => 'A large opening image with a message and buttons.'],
        'rich_text' => ['label' => 'Text section', 'icon' => 'fa-align-left', 'description' => 'A heading and formatted body copy.'],
        'media_text' => ['label' => 'Media and text', 'icon' => 'fa-columns', 'description' => 'Tell a story beside an image, uploaded video, or YouTube video.'],
        'stats' => ['label' => 'Impact statistics', 'icon' => 'fa-bar-chart', 'description' => 'Show important numbers and short labels.'],
        'cards' => ['label' => 'Cards or projects', 'icon' => 'fa-th-large', 'description' => 'A visual grid of programs, projects, or stories.'],
        'ways_to_give' => ['label' => 'Ways to Give', 'icon' => 'fa-gift', 'description' => 'Offer managed donation causes, Zakat, and child sponsorship without entering links.'],
        'causes' => ['label' => 'Programs and causes', 'icon' => 'fa-heart', 'description' => 'Automatically display active programs.'],
        'events' => ['label' => 'Upcoming events', 'icon' => 'fa-calendar', 'description' => 'Automatically display upcoming events.'],
        'events_news' => ['label' => 'Upcoming events + featured news', 'icon' => 'fa-newspaper-o', 'description' => 'Show a managed list of future events beside one featured news story.'],
        'testimonials' => ['label' => 'Community stories', 'icon' => 'fa-quote-left', 'description' => 'Automatically display approved testimonials.'],
        'team' => ['label' => 'Leadership and team', 'icon' => 'fa-users', 'description' => 'Automatically display published board or team members.'],
        'partners' => ['label' => 'Partner organizations', 'icon' => 'fa-handshake-o', 'description' => 'Show a polished wall of partner logos with optional website links.'],
        'faq' => ['label' => 'Questions and answers', 'icon' => 'fa-question-circle', 'description' => 'Add accessible expandable answers to common questions.'],
        'timeline' => ['label' => 'Timeline or process', 'icon' => 'fa-list-ol', 'description' => 'Explain milestones, history, or a step-by-step process.'],
        'gallery' => ['label' => 'Photo gallery', 'icon' => 'fa-picture-o', 'description' => 'Display selected photos or published gallery images.'],
        'video' => ['label' => 'Video', 'icon' => 'fa-play-circle', 'description' => 'Embed a YouTube or Vimeo video, or play an uploaded video.'],
        'cta' => ['label' => 'Call to action', 'icon' => 'fa-bullhorn', 'description' => 'Invite visitors to donate, volunteer, or learn more.'],
        'newsletter' => ['label' => 'Newsletter signup', 'icon' => 'fa-envelope', 'description' => 'Let visitors subscribe for updates.'],
        'layout' => ['label' => 'Visual layout', 'icon' => 'fa-columns', 'description' => 'Build controlled rows and columns, then place text, media, buttons, dividers, or spacing inside them.'],
    ],

    /*
     * Visual layout is intentionally a closed design system. The editor and
     * validator share these tokens, so a saved page can never introduce an
     * arbitrary grid, class name, colour, or element type.
     */
    'layout' => [
        'schema_version' => 2,
        'limits' => [
            'payload_bytes' => 524288,
            'rows' => 12,
            'columns' => 4,
            'elements_per_column' => 12,
        ],
        'presets' => [
            'full' => [
                'label' => 'One column',
                'columns' => 1,
                'widths' => ['1fr'],
            ],
            'halves' => [
                'label' => 'Two equal columns',
                'columns' => 2,
                'widths' => ['1fr', '1fr'],
            ],
            'thirds' => [
                'label' => 'Three equal columns',
                'columns' => 3,
                'widths' => ['1fr', '1fr', '1fr'],
            ],
            'quarter' => [
                'label' => 'Four equal columns',
                'columns' => 4,
                'widths' => ['1fr', '1fr', '1fr', '1fr'],
            ],
            'third_two_thirds' => [
                'label' => 'One third / two thirds',
                'columns' => 2,
                'widths' => ['1fr', '2fr'],
            ],
            'two_thirds_third' => [
                'label' => 'Two thirds / one third',
                'columns' => 2,
                'widths' => ['2fr', '1fr'],
            ],
        ],
        'widths' => [
            'standard' => 'Standard',
            'wide' => 'Wide',
            'full' => 'Full width',
        ],
        'backgrounds' => [
            'default' => ['label' => 'Default', 'swatch' => '#ffffff'],
            'soft' => ['label' => 'Soft', 'swatch' => '#f7f3ef'],
            'accent' => ['label' => 'Accent', 'swatch' => '#fff0e4'],
            'dark' => ['label' => 'Dark', 'swatch' => '#231f20'],
        ],
        'spacings' => [
            'compact' => 'Compact',
            'standard' => 'Standard',
            'generous' => 'Generous',
        ],
        'element_types' => [
            'heading' => 'Heading',
            'rich_text' => 'Rich text',
            'button' => 'Button',
            'icon' => 'Icon',
            'divider' => 'Divider',
            'spacer' => 'Spacer',
            'callout' => 'Highlighted message',
            'image' => 'Image',
            'video' => 'Video',
            'file' => 'File download',
            'gallery' => 'Photo gallery',
            'card' => 'Card',
            'stat' => 'Impact number',
            'quote' => 'Quotation',
            'accordion' => 'Questions and answers',
            'timeline' => 'Timeline or steps',
        ],
        'heading_levels' => [
            'h2' => 'Heading 2',
            'h3' => 'Heading 3',
            'h4' => 'Heading 4',
        ],
        'video_source_types' => [
            'upload' => 'Uploaded video',
            'youtube' => 'YouTube',
        ],
        'button_styles' => [
            'primary' => 'Primary',
            'secondary' => 'Secondary',
            'text' => 'Text link',
        ],
        'spacer_sizes' => [
            'small' => 'Small',
            'medium' => 'Medium',
            'large' => 'Large',
        ],
    ],

    /*
     * Automatic sections keep presentation copy in the block while selecting
     * their cards from managed content. These labels are shared by both page
     * editors so an editor never needs to type a model name or edit JSON.
     */
    'automatic_sources' => [
        'cards' => [
            'manual' => 'Cards I enter here',
            'projects' => 'Managed projects',
            'category' => 'Pages from a category',
        ],
        'causes' => [
            'category' => 'Published programs from a category',
        ],
        'events' => [
            'events' => 'Published events and news',
        ],
        'events_news' => [
            'events_news' => 'Scheduled events and published news',
        ],
        'testimonials' => [
            'testimonials' => 'Approved community stories',
        ],
        'team' => [
            'team' => 'Published board and team members',
        ],
        'gallery' => [
            'gallery' => 'Published gallery photos',
            'manual' => 'Photos I enter here',
        ],
    ],

    'automatic_sort_options' => [
        'featured' => 'Featured order',
        'newest' => 'Newest first',
        'oldest' => 'Oldest first',
        'title' => 'Title A–Z',
    ],

    'section_presentations' => [
        'standard' => 'Standard',
        'soft' => 'Soft background',
        'framed' => 'Framed panel',
        'contrast' => 'Dark contrast',
    ],

    'section_presentation_default' => 'standard',

    /*
     * These constrained design choices are shared by the simple editor and
     * public renderer. Keeping them in one registry prevents arbitrary class
     * names or layout values from entering saved page content.
     */
    'section_spacing_options' => [
        'compact' => 'Compact',
        'standard' => 'Standard',
        'spacious' => 'Spacious',
    ],

    'content_alignment_options' => [
        'left' => 'Left',
        'center' => 'Centered',
    ],

    'column_count_options' => [
        'auto' => 'Automatic',
        '2' => 'Two columns',
        '3' => 'Three columns',
        '4' => 'Four columns',
    ],

    'design_defaults' => [
        'section_spacing' => 'standard',
        'content_alignment' => 'left',
        'column_count' => 'auto',
    ],

    'column_count_block_types' => [
        'stats',
        'cards',
        'ways_to_give',
        'causes',
        'events',
        'team',
        'partners',
        'gallery',
    ],

    'cause_presentations' => [
        'card_grid' => 'Standard image cards',
        'focus_areas' => 'Animated focus areas',
    ],

    'team_presentations' => [
        'cards' => 'Profile cards',
        'list' => 'Simple list',
        'compact' => 'Compact profiles',
        'directory_map' => 'Interactive directory + Bangladesh map',
        'heroes_showcase' => 'Meet the Heroes showcase',
    ],

    'team_map_position_options' => [
        'left' => 'Map on the left',
        'right' => 'Map on the right',
    ],

    'team_profile_behavior_options' => [
        'panel' => 'Show the profile in the directory',
        'modal' => 'Open the profile in a dialog',
        'link' => 'Open the profile link',
    ],

    'testimonial_presentations' => [
        'spotlight' => 'Spotlight story (classic)',
        'split' => 'Two stories side by side',
    ],

    'default_content' => [
        'hero' => [
            'eyebrow' => 'Urgent initiative',
            'heading' => 'A clear, human headline',
            'body' => 'Explain the impact of this initiative in a concise sentence.',
            'primary_label' => 'Donate now',
            'primary_url' => '/donate',
            'secondary_label' => '',
            'secondary_url' => '',
            'image' => '',
            'overlay_opacity' => 64,
            'autoplay' => true,
            'interval' => 6000,
            'pause_on_hover' => true,
            'slides' => [[
                'eyebrow' => 'Urgent initiative',
                'heading' => 'A clear, human headline',
                'body' => 'Explain the impact of this initiative in a concise sentence.',
                'primary_label' => 'Donate now',
                'primary_url' => '/donate',
                'secondary_label' => '',
                'secondary_url' => '',
                'report_label' => '',
                'report_url' => '',
                'image' => '',
                'overlay_opacity' => 64,
            ]],
        ],
        'stats' => [
            'heading' => 'Our impact',
            'animation_enabled' => true,
            'animation_type' => 'count_up',
            'animation_duration' => 1600,
            'animation_delay' => 120,
            'items' => [
                ['value' => '0', 'label' => 'Lives impacted'],
                ['value' => '0', 'label' => 'Active projects'],
                ['value' => '0%', 'label' => 'Funds to field'],
            ],
        ],
        'rich_text' => [
            'eyebrow' => '',
            'heading' => 'Section heading',
            'body' => '<p>Add your content here.</p>',
        ],
        'media_text' => [
            'eyebrow' => '',
            'heading' => 'Tell a meaningful story',
            'body' => '<p>Add your story here.</p>',
            'media_type' => 'image',
            'image' => '',
            'image_alt' => '',
            'video_url' => '',
            'youtube_url' => '',
            'poster' => '',
            'caption' => '',
            'image_position' => 'left',
            'link_label' => '',
            'link_url' => '',
        ],
        'cards' => [
            'eyebrow' => '', 'heading' => 'Featured stories', 'body' => '',
            'content_source' => 'manual', 'selection_mode' => 'automatic',
            'selected_items' => [], 'sort' => 'featured', 'limit' => 3,
            'item_link_label' => 'Learn more', 'view_all_label' => '',
            'view_all_url' => '', 'empty_state' => 'New items will appear here soon.',
            'items' => [],
        ],
        'ways_to_give' => [
            'eyebrow' => 'Ways to give',
            'heading' => 'Choose how you would like to help',
            'body' => 'Select a trusted giving option and continue securely.',
            'layout' => 'card_grid',
            'selection_mode' => 'automatic',
            'selected_items' => [],
            'project_uuid' => '',
            'link_label' => 'Give now',
            'empty_state' => 'Giving options are being updated. Please check again soon.',
        ],
        'causes' => [
            'eyebrow' => 'Our work', 'heading' => 'Our programs', 'body' => '',
            'presentation' => 'card_grid',
            'content_source' => 'category', 'category_slug' => 'our-causes',
            'selection_mode' => 'automatic', 'selected_items' => [],
            'sort' => 'featured', 'limit' => 3, 'item_link_label' => 'Learn more',
            'view_all_label' => 'View all programs', 'view_all_url' => '/category/our-causes',
            'empty_state' => 'Published programs will appear here automatically.',
        ],
        'events' => [
            'eyebrow' => 'Get involved', 'heading' => 'Upcoming events', 'body' => '',
            'content_source' => 'events', 'selection_mode' => 'automatic',
            'selected_items' => [], 'sort' => 'featured', 'limit' => 3,
            'item_link_label' => 'Read more', 'view_all_label' => 'View all events',
            'view_all_url' => '/events',
            'empty_state' => 'Upcoming events and field updates will appear here automatically.',
        ],
        'events_news' => [
            'eyebrow' => 'Stay informed',
            'body' => '',
            'events_heading' => 'Upcoming events',
            'news_heading' => 'Featured news',
            'content_source' => 'events_news',
            'events_selection_mode' => 'automatic',
            'selected_event_ids' => [],
            'event_limit' => 3,
            'featured_news_id' => null,
            'item_link_label' => 'Learn more',
            'events_view_all_label' => 'View all events',
            'events_view_all_url' => '/events',
            'news_view_all_label' => 'View all news',
            'news_view_all_url' => '/news',
            'cta_label' => '',
            'cta_url' => '',
            'events_empty_state' => 'New events will be announced soon.',
            'news_empty_state' => 'A featured news story will appear here soon.',
        ],
        'testimonials' => [
            'eyebrow' => 'Community voices', 'heading' => 'Stories of change', 'body' => '',
            'display_style' => 'spotlight',
            'content_source' => 'testimonials', 'selection_mode' => 'automatic',
            'selected_items' => [], 'sort' => 'featured', 'limit' => 3,
            'empty_state' => 'Approved community stories will appear here automatically.',
        ],
        'team' => [
            'eyebrow' => 'Our people', 'heading' => 'Leadership and team',
            'body' => 'Meet the people responsible for our mission and governance.',
            'content_source' => 'team', 'selection_mode' => 'automatic',
            'selected_items' => [], 'sort' => 'featured', 'limit' => 12,
            'team_presentation' => 'cards',
            'show_map' => true,
            'map_position' => 'left',
            'profile_behavior' => 'panel',
            'animation_enabled' => true,
            'autoplay' => true,
            'item_link_label' => 'View profile',
            'empty_state' => 'Published board and team members will appear here automatically.',
            'items' => [],
        ],
        'partners' => ['eyebrow' => '', 'heading' => 'Partner Organizations', 'body' => '', 'items' => []],
        'faq' => ['eyebrow' => 'Helpful answers', 'heading' => 'Frequently asked questions', 'body' => '', 'items' => [
            ['heading' => 'Add a question', 'body' => 'Add the answer here.'],
        ]],
        'timeline' => ['eyebrow' => 'Our journey', 'heading' => 'Milestones', 'body' => '', 'items' => [
            ['heading' => 'First milestone', 'body' => 'Describe what happened and why it mattered.'],
        ]],
        'gallery' => [
            'eyebrow' => 'In pictures', 'heading' => 'Photo gallery', 'body' => '',
            'content_source' => 'gallery', 'selection_mode' => 'automatic',
            'selected_items' => [], 'sort' => 'featured', 'limit' => 12,
            'view_all_label' => 'View all photos', 'view_all_url' => '/gallery',
            'empty_state' => 'Published gallery photos will appear here automatically.',
            'items' => [],
        ],
        'video' => [
            'eyebrow' => '',
            'heading' => 'Watch our story',
            'body' => '',
            'video_url' => '',
            'poster' => '',
            'caption' => '',
        ],
        'cta' => [
            'eyebrow' => '',
            'heading' => 'Help create lasting change',
            'body' => 'Your support can transform a community.',
            'primary_label' => 'Donate now',
            'primary_url' => '/donate',
        ],
        'newsletter' => [
            'heading' => 'Stories worth sharing',
            'body' => 'Receive field updates and opportunities to help.',
            'button_label' => 'Subscribe',
        ],
        'layout' => [
            'rows' => [[
                'layout' => 'full',
                'width' => 'standard',
                'background' => 'default',
                'spacing' => 'standard',
                'columns' => [[
                    'elements' => [
                        [
                            'type' => 'heading',
                            'text' => 'Section heading',
                            'level' => 'h2',
                        ],
                        [
                            'type' => 'rich_text',
                            'body' => '<p>Add your content here.</p>',
                        ],
                    ],
                ]],
            ]],
        ],
        'spacer' => ['size' => 'medium'],
        'custom_html' => ['html' => ''],
    ],
];
