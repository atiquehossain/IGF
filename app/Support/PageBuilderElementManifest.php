<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Canonical catalogue for elements that may be placed inside visual-layout
 * columns. The catalogue is intentionally framework independent so the
 * request validator, editor and public renderer can consume the same contract.
 *
 * A field definition describes storage, not arbitrary UI configuration. In
 * particular, managed elements contain only published-record references and
 * approved presentation presets; they can never store a model, query, webhook,
 * payment gateway, form recipient, or executable code.
 */
final class PageBuilderElementManifest
{
    public const VERSION = 1;

    private const CATEGORIES = [
        'text_layout' => [
            'label' => 'Text and layout',
            'icon' => 'fa-align-left',
            'description' => 'Words, actions, icons, separators, and spacing.',
        ],
        'media_files' => [
            'label' => 'Media and files',
            'icon' => 'fa-picture-o',
            'description' => 'Images, video, galleries, and approved downloads.',
        ],
        'highlights' => [
            'label' => 'Highlights',
            'icon' => 'fa-star',
            'description' => 'Cards, important numbers, quotations, answers, and steps.',
        ],
        'website_content' => [
            'label' => 'From your website',
            'icon' => 'fa-database',
            'description' => 'Published records selected from protected website tools.',
        ],
    ];

    private const ICON_OPTIONS = [
        '' => 'No icon',
        'people' => 'People',
        'map' => 'Location',
        'heart' => 'Care and support',
        'school' => 'Education',
        'health' => 'Health',
        'water' => 'Water',
        'leaf' => 'Environment',
        'relief' => 'Emergency relief',
        'child' => 'Children',
        'report' => 'Report',
        'financials' => 'Finance',
        'security' => 'Safeguarding',
        'policy' => 'Policy',
    ];

    /** Standalone icon elements must always render a real approved symbol. */
    private const REQUIRED_ICON_OPTIONS = [
        'people' => 'People',
        'map' => 'Location',
        'heart' => 'Care and support',
        'school' => 'Education',
        'health' => 'Health',
        'water' => 'Water',
        'leaf' => 'Environment',
        'relief' => 'Emergency relief',
        'child' => 'Children',
        'report' => 'Report',
        'financials' => 'Finance',
        'security' => 'Safeguarding',
        'policy' => 'Policy',
    ];

    /**
     * Definitions are keyed by their stable storage token. Field names are a
     * strict allowlist. Nested repeater fields carry a second strict allowlist
     * in `item_fields`.
     *
     * @var array<string, array<string, mixed>>
     */
    private const ELEMENTS = [
        'heading' => [
            'label' => 'Heading',
            'description' => 'Add a clear heading to organize this part of the page.',
            'category' => 'text_layout',
            'icon' => 'fa-header',
            'mode' => 'static',
            'max_instances_per_column' => 12,
            'fields' => [
                'text' => [
                    'label' => 'Heading text',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => true,
                    'default' => 'New heading',
                    'bounds' => ['max_length' => 500],
                ],
                'level' => [
                    'label' => 'Heading size',
                    'kind' => 'choice',
                    'translatable' => false,
                    'required' => true,
                    'default' => 'h2',
                    'options' => ['h2' => 'Large', 'h3' => 'Medium', 'h4' => 'Small'],
                ],
            ],
        ],
        'rich_text' => [
            'label' => 'Formatted text',
            'description' => 'Add paragraphs, links, emphasis, or a short list.',
            'category' => 'text_layout',
            'icon' => 'fa-align-left',
            'mode' => 'static',
            'max_instances_per_column' => 12,
            'fields' => [
                'body' => [
                    'label' => 'Text',
                    'kind' => 'rich_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => '<p>Add your text here.</p>',
                    'bounds' => ['max_length' => 20000, 'html_policy' => 'layout_rich_text'],
                ],
            ],
        ],
        'button' => [
            'label' => 'Button',
            'description' => 'Help visitors move to another page or approved web address.',
            'category' => 'text_layout',
            'icon' => 'fa-link',
            'mode' => 'static',
            'max_instances_per_column' => 12,
            'fields' => [
                'label' => [
                    'label' => 'Button text',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => true,
                    'default' => 'Learn more',
                    'bounds' => ['max_length' => 120],
                ],
                'url' => [
                    'label' => 'Destination',
                    'kind' => 'safe_link',
                    'translatable' => false,
                    'required' => true,
                    'default' => '',
                    'bounds' => ['max_length' => 2048, 'schemes' => ['internal', 'anchor', 'https']],
                ],
                'style' => [
                    'label' => 'Button style',
                    'kind' => 'choice',
                    'translatable' => false,
                    'required' => true,
                    'default' => 'primary',
                    'options' => [
                        'primary' => 'Primary',
                        'secondary' => 'Secondary',
                        'text' => 'Text link',
                    ],
                ],
            ],
        ],
        'icon' => [
            'label' => 'Icon',
            'description' => 'Add a simple approved symbol without uploading an image.',
            'category' => 'text_layout',
            'icon' => 'fa-heart-o',
            'mode' => 'static',
            'max_instances_per_column' => 12,
            'fields' => [
                'icon' => [
                    'label' => 'Symbol',
                    'kind' => 'approved_icon',
                    'translatable' => false,
                    'required' => true,
                    'default' => 'heart',
                    'options' => self::REQUIRED_ICON_OPTIONS,
                ],
                'accessible_label' => [
                    'label' => 'Meaning for screen readers',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 120],
                ],
                'decorative' => [
                    'label' => 'This icon is decorative',
                    'kind' => 'boolean',
                    'translatable' => false,
                    'required' => true,
                    'default' => true,
                ],
                'size' => [
                    'label' => 'Icon size',
                    'kind' => 'choice',
                    'translatable' => false,
                    'required' => true,
                    'default' => 'medium',
                    'options' => ['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'],
                ],
                'style' => [
                    'label' => 'Icon appearance',
                    'kind' => 'choice',
                    'translatable' => false,
                    'required' => true,
                    'default' => 'soft',
                    'options' => ['plain' => 'Plain', 'soft' => 'Soft background', 'circle' => 'Circle'],
                ],
            ],
        ],
        'divider' => [
            'label' => 'Divider',
            'description' => 'Separate nearby content with a subtle horizontal line.',
            'category' => 'text_layout',
            'icon' => 'fa-minus',
            'mode' => 'static',
            'max_instances_per_column' => 12,
            'fields' => [],
        ],
        'spacer' => [
            'label' => 'Space',
            'description' => 'Add controlled breathing room between nearby items.',
            'category' => 'text_layout',
            'icon' => 'fa-arrows-v',
            'mode' => 'static',
            'max_instances_per_column' => 12,
            'fields' => [
                'size' => [
                    'label' => 'Amount of space',
                    'kind' => 'choice',
                    'translatable' => false,
                    'required' => true,
                    'default' => 'medium',
                    'options' => ['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'],
                ],
            ],
        ],
        'callout' => [
            'label' => 'Highlighted message',
            'description' => 'Draw attention to a short message and optional action.',
            'category' => 'text_layout',
            'icon' => 'fa-info-circle',
            'mode' => 'static',
            'max_instances_per_column' => 6,
            'fields' => [
                'eyebrow' => [
                    'label' => 'Small heading',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 120],
                ],
                'heading' => [
                    'label' => 'Heading',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => true,
                    'default' => 'Important information',
                    'bounds' => ['max_length' => 300],
                ],
                'body' => [
                    'label' => 'Message',
                    'kind' => 'rich_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 5000, 'html_policy' => 'layout_rich_text'],
                ],
                'icon' => [
                    'label' => 'Icon',
                    'kind' => 'approved_icon',
                    'translatable' => false,
                    'required' => false,
                    'default' => '',
                    'options' => self::ICON_OPTIONS,
                ],
                'tone' => [
                    'label' => 'Appearance',
                    'kind' => 'choice',
                    'translatable' => false,
                    'required' => true,
                    'default' => 'accent',
                    'options' => [
                        'neutral' => 'Neutral',
                        'accent' => 'Accent',
                        'success' => 'Positive',
                        'warning' => 'Important',
                    ],
                ],
                'link_label' => [
                    'label' => 'Link text',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 120],
                ],
                'url' => [
                    'label' => 'Destination',
                    'kind' => 'safe_link',
                    'translatable' => false,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 2048, 'schemes' => ['internal', 'anchor', 'https']],
                ],
            ],
        ],
        'image' => [
            'label' => 'Image',
            'description' => 'Choose an approved image from the Media Library.',
            'category' => 'media_files',
            'icon' => 'fa-picture-o',
            'mode' => 'static',
            'max_instances_per_column' => 12,
            'fields' => [
                'path' => [
                    'label' => 'Image',
                    'kind' => 'managed_image',
                    'translatable' => false,
                    'required' => true,
                    'default' => '',
                    'bounds' => ['max_length' => 2048, 'disk' => 'public'],
                ],
                'alt' => [
                    'label' => 'Describe the image',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 255],
                ],
                'caption' => [
                    'label' => 'Caption',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 1000],
                ],
            ],
        ],
        'video' => [
            'label' => 'Video',
            'description' => 'Choose an uploaded video or add a secure YouTube link.',
            'category' => 'media_files',
            'icon' => 'fa-play-circle',
            'mode' => 'static',
            'max_instances_per_column' => 6,
            'fields' => [
                'source_type' => [
                    'label' => 'Video source',
                    'kind' => 'choice',
                    'translatable' => false,
                    'required' => true,
                    'default' => 'upload',
                    'options' => ['upload' => 'Uploaded video', 'youtube' => 'YouTube'],
                ],
                'source' => [
                    'label' => 'Video',
                    'kind' => 'approved_video_source',
                    'translatable' => false,
                    'required' => true,
                    'default' => '',
                    'bounds' => [
                        'max_length' => 2048,
                        'upload_mime_types' => ['video/mp4', 'video/webm'],
                        'remote_hosts' => ['youtube.com', 'www.youtube.com', 'youtu.be'],
                    ],
                ],
                'title' => [
                    'label' => 'Video title',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => true,
                    'default' => '',
                    'bounds' => ['max_length' => 255],
                ],
            ],
        ],
        'file' => [
            'label' => 'File download',
            'description' => 'Offer an approved public document from the Media Library.',
            'category' => 'media_files',
            'icon' => 'fa-file-o',
            'mode' => 'static',
            'max_instances_per_column' => 8,
            'fields' => [
                'path' => [
                    'label' => 'File',
                    'kind' => 'managed_file',
                    'translatable' => false,
                    'required' => true,
                    'default' => '',
                    'bounds' => [
                        'max_length' => 2048,
                        'disk' => 'public',
                        'mime_types' => [
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ],
                    ],
                ],
                'label' => [
                    'label' => 'Link text',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => true,
                    'default' => 'Download file',
                    'bounds' => ['max_length' => 160],
                ],
                'description' => [
                    'label' => 'Description',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 500],
                ],
                'open_in_new_tab' => [
                    'label' => 'Open in a new tab',
                    'kind' => 'boolean',
                    'translatable' => false,
                    'required' => true,
                    'default' => false,
                ],
            ],
        ],
        'gallery' => [
            'label' => 'Photo gallery',
            'description' => 'Show a small collection of approved images.',
            'category' => 'media_files',
            'icon' => 'fa-th',
            'mode' => 'static',
            'max_instances_per_column' => 3,
            'fields' => [
                'items' => [
                    'label' => 'Photos',
                    'kind' => 'repeater',
                    'translatable' => false,
                    'required' => true,
                    'default' => [],
                    'bounds' => ['max_items' => 12],
                    'item_identity' => 'id',
                    'item_fields' => [
                        'id' => [
                            'label' => 'Photo identity',
                            'kind' => 'uuid',
                            'translatable' => false,
                            'required' => false,
                            'default' => null,
                        ],
                        'path' => [
                            'label' => 'Image',
                            'kind' => 'managed_image',
                            'translatable' => false,
                            'required' => true,
                            'default' => '',
                            'bounds' => ['max_length' => 2048, 'disk' => 'public'],
                        ],
                        'alt' => [
                            'label' => 'Describe the image',
                            'kind' => 'plain_text',
                            'translatable' => true,
                            'required' => false,
                            'default' => '',
                            'bounds' => ['max_length' => 255],
                        ],
                        'caption' => [
                            'label' => 'Caption',
                            'kind' => 'plain_text',
                            'translatable' => true,
                            'required' => false,
                            'default' => '',
                            'bounds' => ['max_length' => 500],
                        ],
                    ],
                ],
                'columns' => [
                    'label' => 'Photos per row',
                    'kind' => 'choice',
                    'translatable' => false,
                    'required' => true,
                    'default' => '3',
                    'options' => ['2' => 'Two', '3' => 'Three', '4' => 'Four'],
                ],
                'lightbox' => [
                    'label' => 'Open larger photos',
                    'kind' => 'boolean',
                    'translatable' => false,
                    'required' => true,
                    'default' => true,
                ],
            ],
        ],
        'card' => [
            'label' => 'Card',
            'description' => 'Highlight one topic with an image or icon and an optional link.',
            'category' => 'highlights',
            'icon' => 'fa-id-card-o',
            'mode' => 'static',
            'max_instances_per_column' => 8,
            'fields' => [
                'eyebrow' => [
                    'label' => 'Small heading',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 120],
                ],
                'heading' => [
                    'label' => 'Card heading',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => true,
                    'default' => 'New card',
                    'bounds' => ['max_length' => 300],
                ],
                'body' => [
                    'label' => 'Description',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 2000],
                ],
                'image' => [
                    'label' => 'Image',
                    'kind' => 'managed_image',
                    'translatable' => false,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 2048, 'disk' => 'public'],
                ],
                'image_alt' => [
                    'label' => 'Describe the image',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 255],
                ],
                'icon' => [
                    'label' => 'Icon shown when there is no image',
                    'kind' => 'approved_icon',
                    'translatable' => false,
                    'required' => false,
                    'default' => '',
                    'options' => self::ICON_OPTIONS,
                ],
                'link_label' => [
                    'label' => 'Link text',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => 'Learn more',
                    'bounds' => ['max_length' => 120],
                ],
                'url' => [
                    'label' => 'Destination',
                    'kind' => 'safe_link',
                    'translatable' => false,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 2048, 'schemes' => ['internal', 'anchor', 'https']],
                ],
                'style' => [
                    'label' => 'Card appearance',
                    'kind' => 'choice',
                    'translatable' => false,
                    'required' => true,
                    'default' => 'standard',
                    'options' => ['standard' => 'Standard', 'soft' => 'Soft', 'contrast' => 'Dark contrast'],
                ],
            ],
        ],
        'stat' => [
            'label' => 'Impact number',
            'description' => 'Emphasize one important number and explain what it means.',
            'category' => 'highlights',
            'icon' => 'fa-bar-chart',
            'mode' => 'static',
            'max_instances_per_column' => 8,
            'fields' => [
                'value' => [
                    'label' => 'Number or value',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => true,
                    'default' => '0',
                    'bounds' => ['max_length' => 40],
                ],
                'label' => [
                    'label' => 'What this number means',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => true,
                    'default' => 'People reached',
                    'bounds' => ['max_length' => 200],
                ],
                'icon' => [
                    'label' => 'Icon',
                    'kind' => 'approved_icon',
                    'translatable' => false,
                    'required' => false,
                    'default' => '',
                    'options' => self::ICON_OPTIONS,
                ],
                'emphasis' => [
                    'label' => 'Appearance',
                    'kind' => 'choice',
                    'translatable' => false,
                    'required' => true,
                    'default' => 'standard',
                    'options' => ['standard' => 'Standard', 'accent' => 'Accent', 'contrast' => 'Dark contrast'],
                ],
            ],
        ],
        'quote' => [
            'label' => 'Quotation',
            'description' => 'Highlight a quotation with an optional name and photograph.',
            'category' => 'highlights',
            'icon' => 'fa-quote-left',
            'mode' => 'static',
            'max_instances_per_column' => 6,
            'fields' => [
                'quote' => [
                    'label' => 'Quotation',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => true,
                    'default' => 'Add a meaningful quotation.',
                    'bounds' => ['max_length' => 2000],
                ],
                'attribution' => [
                    'label' => 'Name',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 200],
                ],
                'role' => [
                    'label' => 'Role or organization',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 300],
                ],
                'image' => [
                    'label' => 'Photograph',
                    'kind' => 'managed_image',
                    'translatable' => false,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 2048, 'disk' => 'public'],
                ],
                'image_alt' => [
                    'label' => 'Describe the photograph',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 255],
                ],
                'style' => [
                    'label' => 'Quotation appearance',
                    'kind' => 'choice',
                    'translatable' => false,
                    'required' => true,
                    'default' => 'standard',
                    'options' => ['standard' => 'Standard', 'featured' => 'Featured', 'compact' => 'Compact'],
                ],
            ],
        ],
        'accordion' => [
            'label' => 'Questions and answers',
            'description' => 'Add expandable answers to common questions.',
            'category' => 'highlights',
            'icon' => 'fa-question-circle',
            'mode' => 'static',
            'max_instances_per_column' => 4,
            'fields' => [
                'items' => [
                    'label' => 'Questions',
                    'kind' => 'repeater',
                    'translatable' => false,
                    'required' => true,
                    'default' => [[
                        'id' => null,
                        'question' => 'Add a question',
                        'answer' => '<p>Add the answer here.</p>',
                    ]],
                    'bounds' => ['max_items' => 12],
                    'item_identity' => 'id',
                    'item_fields' => [
                        'id' => [
                            'label' => 'Question identity',
                            'kind' => 'uuid',
                            'translatable' => false,
                            'required' => false,
                            'default' => null,
                        ],
                        'question' => [
                            'label' => 'Question',
                            'kind' => 'plain_text',
                            'translatable' => true,
                            'required' => true,
                            'default' => 'Add a question',
                            'bounds' => ['max_length' => 500],
                        ],
                        'answer' => [
                            'label' => 'Answer',
                            'kind' => 'rich_text',
                            'translatable' => true,
                            'required' => false,
                            'default' => '<p>Add the answer here.</p>',
                            'bounds' => ['max_length' => 10000, 'html_policy' => 'layout_rich_text'],
                        ],
                    ],
                ],
                'allow_one_open' => [
                    'label' => 'Keep only one answer open',
                    'kind' => 'boolean',
                    'translatable' => false,
                    'required' => true,
                    'default' => false,
                ],
            ],
        ],
        'timeline' => [
            'label' => 'Timeline or steps',
            'description' => 'Explain milestones, history, or a step-by-step process.',
            'category' => 'highlights',
            'icon' => 'fa-list-ol',
            'mode' => 'static',
            'max_instances_per_column' => 3,
            'fields' => [
                'items' => [
                    'label' => 'Milestones or steps',
                    'kind' => 'repeater',
                    'translatable' => false,
                    'required' => true,
                    'default' => [[
                        'id' => null,
                        'date_label' => 'Step 1',
                        'heading' => 'First step',
                        'body' => '',
                        'icon' => '',
                    ]],
                    'bounds' => ['max_items' => 12],
                    'item_identity' => 'id',
                    'item_fields' => [
                        'id' => [
                            'label' => 'Milestone identity',
                            'kind' => 'uuid',
                            'translatable' => false,
                            'required' => false,
                            'default' => null,
                        ],
                        'date_label' => [
                            'label' => 'Date or step number',
                            'kind' => 'plain_text',
                            'translatable' => true,
                            'required' => false,
                            'default' => '',
                            'bounds' => ['max_length' => 120],
                        ],
                        'heading' => [
                            'label' => 'Heading',
                            'kind' => 'plain_text',
                            'translatable' => true,
                            'required' => true,
                            'default' => 'New milestone',
                            'bounds' => ['max_length' => 300],
                        ],
                        'body' => [
                            'label' => 'Description',
                            'kind' => 'plain_text',
                            'translatable' => true,
                            'required' => false,
                            'default' => '',
                            'bounds' => ['max_length' => 2000],
                        ],
                        'icon' => [
                            'label' => 'Icon',
                            'kind' => 'approved_icon',
                            'translatable' => false,
                            'required' => false,
                            'default' => '',
                            'options' => self::ICON_OPTIONS,
                        ],
                    ],
                ],
                'style' => [
                    'label' => 'Display as',
                    'kind' => 'choice',
                    'translatable' => false,
                    'required' => true,
                    'default' => 'timeline',
                    'options' => ['timeline' => 'Timeline', 'steps' => 'Numbered steps', 'compact' => 'Compact list'],
                ],
            ],
        ],
        'content_feed' => [
            'label' => 'Content list',
            'description' => 'Automatically show published programs, projects, events, stories, pages, or reports.',
            'category' => 'website_content',
            'icon' => 'fa-newspaper-o',
            'mode' => 'managed',
            'max_instances_per_column' => 3,
            'fields' => [
                'heading' => [
                    'label' => 'Heading',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 300],
                ],
                'intro' => [
                    'label' => 'Introduction',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 1000],
                ],
                'source' => [
                    'label' => 'What should appear?',
                    'kind' => 'choice',
                    'translatable' => false,
                    'required' => true,
                    'default' => 'programs',
                    'options' => [
                        'programs' => 'Published programs',
                        'projects' => 'Published projects',
                        'events' => 'Published events',
                        'stories' => 'Published stories',
                        'pages' => 'Published pages',
                        'reports' => 'Published reports',
                    ],
                ],
                'category_id' => [
                    'label' => 'Category',
                    'kind' => 'managed_reference',
                    'translatable' => false,
                    'required' => false,
                    'default' => null,
                    'resource' => 'content_categories',
                    'scope' => 'published',
                ],
                'sort' => [
                    'label' => 'Order',
                    'kind' => 'choice',
                    'translatable' => false,
                    'required' => true,
                    'default' => 'featured',
                    'options' => [
                        'featured' => 'Featured order',
                        'newest' => 'Newest first',
                        'oldest' => 'Oldest first',
                        'title' => 'Title A–Z',
                    ],
                ],
                'item_limit' => [
                    'label' => 'Number of items',
                    'kind' => 'integer',
                    'translatable' => false,
                    'required' => true,
                    'default' => 6,
                    'bounds' => ['min' => 1, 'max' => 12],
                ],
                'presentation' => [
                    'label' => 'Appearance',
                    'kind' => 'choice',
                    'translatable' => false,
                    'required' => true,
                    'default' => 'cards',
                    'options' => ['cards' => 'Cards', 'list' => 'List', 'compact' => 'Compact'],
                ],
                'show_image' => [
                    'label' => 'Show images',
                    'kind' => 'boolean',
                    'translatable' => false,
                    'required' => true,
                    'default' => true,
                ],
                'show_summary' => [
                    'label' => 'Show summaries',
                    'kind' => 'boolean',
                    'translatable' => false,
                    'required' => true,
                    'default' => true,
                ],
                'show_date' => [
                    'label' => 'Show dates',
                    'kind' => 'boolean',
                    'translatable' => false,
                    'required' => true,
                    'default' => false,
                ],
                'item_link_label' => [
                    'label' => 'Item link text',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => 'Learn more',
                    'bounds' => ['max_length' => 120],
                ],
            ],
        ],
        'team' => [
            'label' => 'Team members',
            'description' => 'Show published board or team members without copying their profiles.',
            'category' => 'website_content',
            'icon' => 'fa-users',
            'mode' => 'managed',
            'max_instances_per_column' => 3,
            'fields' => [
                'heading' => [
                    'label' => 'Heading',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 300],
                ],
                'intro' => [
                    'label' => 'Introduction',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 1000],
                ],
                'selection' => [
                    'label' => 'Who should appear?',
                    'kind' => 'choice',
                    'translatable' => false,
                    'required' => true,
                    'default' => 'groups',
                    'options' => [
                        'all' => 'All published people',
                        'groups' => 'Selected groups',
                        'people' => 'Selected people',
                    ],
                ],
                'group_ids' => [
                    'label' => 'Groups',
                    'kind' => 'managed_reference_list',
                    'translatable' => false,
                    'required' => false,
                    'default' => [],
                    'resource' => 'published_team_groups',
                    'scope' => 'published',
                    'bounds' => ['max_items' => 8],
                ],
                'member_ids' => [
                    'label' => 'People',
                    'kind' => 'managed_reference_list',
                    'translatable' => false,
                    'required' => false,
                    'default' => [],
                    'resource' => 'published_team_members',
                    'scope' => 'published',
                    'bounds' => ['max_items' => 24],
                ],
                'sort' => [
                    'label' => 'Order',
                    'kind' => 'choice',
                    'translatable' => false,
                    'required' => true,
                    'default' => 'managed',
                    'options' => ['managed' => 'Managed order', 'name' => 'Name A–Z'],
                ],
                'item_limit' => [
                    'label' => 'Maximum people',
                    'kind' => 'integer',
                    'translatable' => false,
                    'required' => true,
                    'default' => 12,
                    'bounds' => ['min' => 1, 'max' => 24],
                ],
                'presentation' => [
                    'label' => 'Appearance',
                    'kind' => 'choice',
                    'translatable' => false,
                    'required' => true,
                    'default' => 'cards',
                    'options' => ['cards' => 'Cards', 'list' => 'List', 'compact' => 'Compact'],
                ],
            ],
        ],
        'giving' => [
            'label' => 'Ways to give',
            'description' => 'Show approved donation destinations without editing payment settings.',
            'category' => 'website_content',
            'icon' => 'fa-gift',
            'mode' => 'managed',
            'max_instances_per_column' => 2,
            'fields' => [
                'heading' => [
                    'label' => 'Heading',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 300],
                ],
                'intro' => [
                    'label' => 'Introduction',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 1000],
                ],
                'destination_ids' => [
                    'label' => 'Giving options',
                    'kind' => 'managed_reference_list',
                    'translatable' => false,
                    'required' => true,
                    'default' => [],
                    'resource' => 'active_donation_destinations',
                    'scope' => 'published',
                    'bounds' => ['min_items' => 1, 'max_items' => 8],
                ],
                'presentation' => [
                    'label' => 'Appearance',
                    'kind' => 'choice',
                    'translatable' => false,
                    'required' => true,
                    'default' => 'card_grid',
                    'options' => [
                        'card_grid' => 'Cards',
                        'single_cta' => 'Single call to action',
                        'banner' => 'Banner',
                    ],
                ],
                'link_label' => [
                    'label' => 'Button text',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => true,
                    'default' => 'Give now',
                    'bounds' => ['max_length' => 120],
                ],
            ],
        ],
        'managed_form' => [
            'label' => 'Saved form',
            'description' => 'Place an approved published form without changing its fields or recipients.',
            'category' => 'website_content',
            'icon' => 'fa-list-alt',
            'mode' => 'managed',
            'max_instances_per_column' => 1,
            'fields' => [
                'heading' => [
                    'label' => 'Heading',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 300],
                ],
                'intro' => [
                    'label' => 'Introduction',
                    'kind' => 'plain_text',
                    'translatable' => true,
                    'required' => false,
                    'default' => '',
                    'bounds' => ['max_length' => 1000],
                ],
                'form_id' => [
                    'label' => 'Form',
                    'kind' => 'managed_reference',
                    'translatable' => false,
                    'required' => true,
                    'default' => null,
                    'resource' => 'published_public_forms',
                    'scope' => 'published',
                ],
                'presentation' => [
                    'label' => 'Appearance',
                    'kind' => 'choice',
                    'translatable' => false,
                    'required' => true,
                    'default' => 'standard',
                    'options' => ['standard' => 'Standard', 'compact' => 'Compact', 'framed' => 'Framed panel'],
                ],
            ],
        ],
    ];

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        $manifest = [];

        foreach (self::ELEMENTS as $type => $definition) {
            $fields = (array) $definition['fields'];
            $manifest[$type] = [
                'type' => $type,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'category' => $definition['category'],
                'icon' => $definition['icon'],
                'mode' => $definition['mode'],
                'defaults' => ['type' => $type] + self::fieldDefaults($fields),
                'allowed_fields' => array_merge(['id', 'type'], array_keys($fields)),
                'translatable_fields' => self::fieldPaths($fields, true),
                'machine_fields' => array_merge(['id', 'type'], self::fieldPaths($fields, false)),
                'safe_bounds' => [
                    'max_serialized_bytes' => 65536,
                    'max_instances_per_column' => $definition['max_instances_per_column'],
                    'fields' => self::fieldConstraints($fields),
                ],
                'fields' => $fields,
            ];
        }

        return $manifest;
    }

    /** @return list<string> */
    public static function types(): array
    {
        return array_keys(self::ELEMENTS);
    }

    public static function has(string $type): bool
    {
        return array_key_exists($type, self::ELEMENTS);
    }

    /** @return array<string, mixed> */
    public static function get(string $type): array
    {
        $definition = self::all()[$type] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException("Unknown page-builder element type [{$type}].");
        }

        return $definition;
    }

    /** @return array<string, mixed> */
    public static function defaults(string $type): array
    {
        return self::get($type)['defaults'];
    }

    /** @return array<string, array<string, mixed>> */
    public static function categories(): array
    {
        return self::CATEGORIES;
    }

    /**
     * Grouped data is ready for a friendly element picker. Category and element
     * order remain intentional rather than being alphabetically re-sorted.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function grouped(): array
    {
        $groups = [];
        $manifest = self::all();

        foreach (self::CATEGORIES as $category => $metadata) {
            $groups[$category] = $metadata + [
                'elements' => array_values(array_filter(
                    $manifest,
                    static fn (array $element): bool => $element['category'] === $category
                )),
            ];
        }

        return $groups;
    }

    /** @param array<string, array<string, mixed>> $fields */
    private static function fieldDefaults(array $fields): array
    {
        $defaults = [];

        foreach ($fields as $name => $field) {
            $defaults[$name] = $field['default'];
        }

        return $defaults;
    }

    /**
     * Return leaf paths so translation extraction can preserve nested machine
     * structure while clearing only authored words.
     *
     * @param  array<string, array<string, mixed>>  $fields
     * @return list<string>
     */
    private static function fieldPaths(array $fields, bool $translatable, string $prefix = ''): array
    {
        $paths = [];

        foreach ($fields as $name => $field) {
            $path = $prefix . $name;
            $itemFields = (array) ($field['item_fields'] ?? []);

            if ($itemFields !== []) {
                $paths = array_merge(
                    $paths,
                    self::fieldPaths($itemFields, $translatable, $path . '.*.')
                );
                continue;
            }

            if ((bool) $field['translatable'] === $translatable) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * Export only storage constraints. Labels and default copy are deliberately
     * omitted so validators can use this map without depending on editor text.
     *
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, array<string, mixed>>
     */
    private static function fieldConstraints(array $fields, string $prefix = ''): array
    {
        $constraints = [];

        foreach ($fields as $name => $field) {
            $path = $prefix . $name;
            $constraint = [
                'kind' => $field['kind'],
                'required' => $field['required'],
            ];

            if (isset($field['options'])) {
                $constraint['choices'] = array_keys((array) $field['options']);
            }
            if (isset($field['resource'])) {
                $constraint['resource'] = $field['resource'];
                $constraint['scope'] = $field['scope'];
            }
            if (isset($field['item_identity'])) {
                $constraint['item_identity'] = $field['item_identity'];
            }
            if (isset($field['bounds'])) {
                $constraint += (array) $field['bounds'];
            }

            $constraints[$path] = $constraint;

            $itemFields = (array) ($field['item_fields'] ?? []);
            if ($itemFields !== []) {
                $constraints += self::fieldConstraints($itemFields, $path . '.*.');
            }
        }

        return $constraints;
    }
}
