<?php

namespace App\Support;

final class AdminPermissionRegistry
{
    public const ESSENTIAL_ROUTES = [
        'dashboard.index',
        'admin.language',
        'admin.password',
        'admin.password.update',
        'admin.image',
        'admin.logout',
    ];

    /**
     * Menu records are the view capabilities for their respective admin areas.
     * `grant_from` is used only by the migration/seeder compatibility backfill.
     */
    private const MENU_DEFINITIONS = [
        ['id' => 33, 'link' => 'album.index', 'name' => 'Albums', 'parent_id' => 8],
        ['id' => 17, 'link' => 'gallery.index', 'name' => 'Gallery', 'parent_id' => 8],
        ['id' => 7, 'link' => 'banner.index', 'name' => 'Banners', 'parent_id' => 8],
        ['id' => 22, 'link' => 'notice.board.index', 'name' => 'Events & Updates', 'parent_id' => 8, 'grant_from' => ['notice.board.create', 'notice.board.edit', 'notice.board.status', 'notice.board.destroy']],
        ['id' => 51, 'link' => 'annual.report.index', 'name' => 'Annual Reports', 'parent_id' => 8, 'grant_from' => ['page.edit']],
        ['id' => 47, 'link' => 'donations.index', 'name' => 'Donations', 'parent_id' => null],
        ['id' => 52, 'link' => 'sponsorships.index', 'name' => 'Sponsorship Requests', 'parent_id' => null],
        ['id' => 53, 'link' => 'volunteer.index', 'name' => 'Volunteer Applications', 'parent_id' => null],
        ['id' => 54, 'link' => 'volunteerCause.index', 'name' => 'Volunteer Opportunities', 'parent_id' => 8],
        ['id' => 20, 'link' => 'latest.news.index', 'name' => 'Team Members', 'parent_id' => 8, 'grant_from' => ['latest.news.create', 'latest.news.edit', 'latest.news.status', 'latest.news.destroy']],
        ['id' => 9, 'link' => 'division.index', 'name' => 'Divisions', 'parent_id' => 2],
        ['id' => 12, 'link' => 'upazila.index', 'name' => 'Upazilas', 'parent_id' => 2],
        ['id' => 11, 'link' => 'district.index', 'name' => 'Districts', 'parent_id' => 2],
        ['id' => 14, 'link' => 'category.index', 'name' => 'Categories', 'parent_id' => 8],
        ['id' => 29, 'link' => 'event_calendar.index', 'name' => 'Event Calendar', 'parent_id' => 8],
        ['id' => 19, 'link' => 'editorDraft.index', 'name' => 'Editor Drafts', 'parent_id' => 2],
        ['id' => 10, 'link' => 'page.index', 'name' => 'Pages', 'parent_id' => 8],
        ['id' => 55, 'link' => 'site.settings.index', 'name' => 'Website Settings', 'parent_id' => null, 'grant_from' => ['page.edit']],
        ['id' => 56, 'link' => 'translations.index', 'name' => 'Translations', 'parent_id' => null, 'grant_from' => ['page.edit']],
        ['id' => 50, 'link' => 'seo.index', 'name' => 'Search & Sharing', 'parent_id' => null],
        ['id' => 66, 'link' => 'seo.technical.index', 'name' => 'Technical SEO & 404s', 'parent_id' => null],
        ['id' => 57, 'link' => 'reusable-blocks.index', 'name' => 'Reusable Blocks', 'parent_id' => 8, 'grant_from' => ['page.edit']],
        ['id' => 58, 'link' => 'media.index', 'name' => 'Media Library', 'parent_id' => 8, 'grant_from' => ['gallery.index']],
        ['id' => 59, 'link' => 'donationType.index', 'name' => 'Donation Causes', 'parent_id' => 8],
        ['id' => 60, 'link' => 'tag.index', 'name' => 'Projects', 'parent_id' => 8],
        ['id' => 61, 'link' => 'testimonial.index', 'name' => 'Testimonials', 'parent_id' => 8, 'grant_from' => ['page.edit']],
        ['id' => 62, 'link' => 'subscriber.index', 'name' => 'Subscribers', 'parent_id' => null],
        ['id' => 27, 'link' => 'comment.index', 'name' => 'Comments', 'parent_id' => null],
        ['id' => 15, 'link' => 'youtube.index', 'name' => 'YouTube', 'parent_id' => 2],
        ['id' => 48, 'link' => 'user.index', 'name' => 'Donors', 'parent_id' => null],
        ['id' => 18, 'link' => 'report.youtubeMeta', 'name' => 'YouTube Report', 'parent_id' => 16],
        ['id' => 13, 'link' => 'page.menu.index', 'name' => 'Header & Footer', 'parent_id' => 8],
        ['id' => 4, 'link' => 'menu.index', 'name' => 'Permission Menus', 'parent_id' => 3],
        ['id' => 5, 'link' => 'role.index', 'name' => 'Roles', 'parent_id' => 3],
        ['id' => 6, 'link' => 'admin.index', 'name' => 'Admin Users', 'parent_id' => 3],
        ['id' => 63, 'link' => 'user-approval.index', 'name' => 'Member Approvals', 'parent_id' => 3],
        ['id' => 64, 'link' => 'splash.screen.index', 'name' => 'Splash Screen', 'parent_id' => 8],
        ['id' => 26, 'link' => 'contact-message.index', 'name' => 'Contact Messages', 'parent_id' => null, 'grant_from' => ['contact-message.show']],
        ['id' => 49, 'link' => 'chat.index', 'name' => 'Website Chat', 'parent_id' => null],
        ['id' => 65, 'link' => 'content.trash.index', 'name' => 'Content Trash', 'parent_id' => 8, 'grant_from' => ['page.destroy']],
    ];

    /**
     * Conventional create/edit/publish/delete capabilities. Existing IDs are
     * retained so deployed role CSVs keep their meaning.
     */
    private const RESOURCE_ACTION_DEFINITIONS = [
        'album' => ['menu' => 'album.index', 'label' => 'albums', 'actions' => ['create' => 60, 'edit' => 61, 'publish' => 62, 'delete' => 63]],
        'gallery' => ['menu' => 'gallery.index', 'label' => 'gallery items', 'actions' => ['create' => 45, 'edit' => 46, 'publish' => 47, 'delete' => 48]],
        'banner' => ['menu' => 'banner.index', 'label' => 'banners', 'actions' => ['create' => 15, 'edit' => 16, 'publish' => 17, 'delete' => 44]],
        'notice.board' => ['menu' => 'notice.board.index', 'label' => 'events and updates', 'actions' => ['create' => 65, 'edit' => 66, 'publish' => 67, 'delete' => 68]],
        'annual.report' => ['menu' => 'annual.report.index', 'label' => 'annual reports', 'actions' => ['create' => 170, 'edit' => 171, 'publish' => 172, 'delete' => 173], 'grant_from' => ['page.edit']],
        'volunteerCause' => ['menu' => 'volunteerCause.index', 'label' => 'volunteer opportunities', 'actions' => ['create' => 174, 'edit' => 175, 'publish' => 176, 'delete' => 177]],
        'latest.news' => ['menu' => 'latest.news.index', 'label' => 'team members', 'actions' => ['create' => 54, 'edit' => 55, 'publish' => 56, 'delete' => 57]],
        'division' => ['menu' => 'division.index', 'label' => 'divisions', 'actions' => ['create' => 18, 'edit' => 19, 'publish' => 20, 'delete' => 178]],
        'upazila' => ['menu' => 'upazila.index', 'label' => 'upazilas', 'actions' => ['create' => 24, 'edit' => 25, 'publish' => 26, 'delete' => 179]],
        'district' => ['menu' => 'district.index', 'label' => 'districts', 'actions' => ['create' => 21, 'edit' => 22, 'publish' => 23, 'delete' => 180]],
        'category' => ['menu' => 'category.index', 'label' => 'categories', 'actions' => ['create' => 27, 'edit' => 28, 'publish' => 29, 'delete' => 49]],
        'event_calendar' => ['menu' => 'event_calendar.index', 'label' => 'calendar events', 'actions' => ['create' => 87, 'edit' => 88, 'publish' => 90, 'delete' => 89]],
        'editorDraft' => ['menu' => 'editorDraft.index', 'label' => 'editor drafts', 'actions' => ['create' => 50, 'edit' => 51, 'publish' => 52, 'delete' => 53]],
        'page' => ['menu' => 'page.index', 'label' => 'pages', 'actions' => ['create' => 35, 'edit' => 38, 'publish' => 39, 'delete' => 40]],
        'site.settings' => ['menu' => 'site.settings.index', 'label' => 'website settings', 'actions' => ['edit' => 181, 'delete' => 182], 'grant_from' => ['page.edit']],
        'translations' => ['menu' => 'translations.index', 'label' => 'translations', 'actions' => ['edit' => 183, 'publish' => 184], 'grant_from' => ['page.edit']],
        'page.builder' => ['menu' => 'page.index', 'label' => 'page-builder content', 'actions' => ['create' => 185, 'edit' => 186, 'delete' => 187], 'grant_from' => ['page.edit']],
        'reusable-blocks' => ['menu' => 'reusable-blocks.index', 'label' => 'reusable blocks', 'actions' => ['edit' => 188, 'delete' => 189], 'grant_from' => ['page.edit']],
        'media' => [
            'menu' => 'media.index',
            'label' => 'media',
            'actions' => ['create' => 190, 'edit' => 191, 'delete' => 192],
            'grant_from_by_action' => ['create' => ['gallery.create'], 'edit' => ['gallery.edit'], 'delete' => ['gallery.destroy']],
        ],
        'donationType' => ['menu' => 'donationType.index', 'label' => 'donation causes', 'actions' => ['create' => 193, 'edit' => 194, 'publish' => 195, 'delete' => 196]],
        'tag' => ['menu' => 'tag.index', 'label' => 'projects', 'actions' => ['create' => 197, 'edit' => 198, 'publish' => 199, 'delete' => 200]],
        'testimonial' => ['menu' => 'testimonial.index', 'label' => 'testimonials', 'actions' => ['create' => 201, 'edit' => 202, 'publish' => 203, 'delete' => 204], 'grant_from' => ['page.edit']],
        'subscriber' => ['menu' => 'subscriber.index', 'label' => 'subscribers', 'actions' => ['delete' => 205]],
        'comment' => ['menu' => 'comment.index', 'label' => 'comments', 'actions' => ['delete' => 82]],
        'youtube' => ['menu' => 'youtube.index', 'label' => 'YouTube entries', 'actions' => ['create' => 31, 'edit' => 32, 'publish' => 33, 'delete' => 36]],
        'page.menu' => ['menu' => 'page.menu.index', 'label' => 'navigation items', 'actions' => ['create' => 34, 'edit' => 41, 'publish' => 42, 'delete' => 43]],
        'menu' => ['menu' => 'menu.index', 'label' => 'permission menus', 'actions' => ['create' => 1, 'edit' => 2, 'publish' => 3, 'delete' => 5]],
        'menu.action' => ['menu' => 'menu.index', 'label' => 'permission actions', 'actions' => ['create' => 215, 'edit' => 216, 'publish' => 217, 'delete' => 218]],
        'role' => ['menu' => 'role.index', 'label' => 'roles', 'actions' => ['create' => 6, 'edit' => 7, 'publish' => 8, 'delete' => 10]],
        'admin' => ['menu' => 'admin.index', 'label' => 'admin users', 'actions' => ['create' => 11, 'edit' => 12, 'publish' => 13, 'delete' => 14]],
        'user-approval' => ['menu' => 'user-approval.index', 'label' => 'member approvals', 'actions' => ['edit' => 207]],
        'splash.screen' => ['menu' => 'splash.screen.index', 'label' => 'splash-screen content', 'actions' => ['create' => 208]],
        'content.trash' => ['menu' => 'content.trash.index', 'label' => 'content trash', 'actions' => ['edit' => 209, 'delete' => 210], 'grant_from' => ['page.destroy']],
        'sponsorships' => ['menu' => 'sponsorships.index', 'label' => 'sponsorship requests', 'actions' => ['edit' => 220], 'grant_from' => ['sponsorships.index']],
        'volunteer' => ['menu' => 'volunteer.index', 'label' => 'volunteer applications', 'actions' => ['edit' => 221], 'grant_from' => ['volunteer.index']],
        'contact-message' => ['menu' => 'contact-message.index', 'label' => 'contact messages', 'actions' => ['edit' => 222], 'grant_from' => ['contact-message.index']],
    ];

    private const CUSTOM_ACTION_DEFINITIONS = [
        ['id' => 64, 'link' => 'page.view', 'menu' => 'page.index', 'name' => 'View pages', 'type' => 8],
        ['id' => 211, 'link' => 'page.trash.view', 'menu' => 'page.index', 'name' => 'View deleted pages', 'type' => 8, 'grant_from' => ['page.destroy']],
        ['id' => 212, 'link' => 'page.trash.edit', 'menu' => 'page.index', 'name' => 'Restore deleted pages', 'type' => 2, 'grant_from' => ['page.destroy']],
        ['id' => 213, 'link' => 'page.trash.destroy', 'menu' => 'page.index', 'name' => 'Permanently delete pages', 'type' => 4, 'grant_from' => ['page.destroy']],
        ['id' => 214, 'link' => 'page.menu.trash.view', 'menu' => 'page.menu.index', 'name' => 'View deleted navigation items', 'type' => 8, 'grant_from' => ['page.menu.destroy']],
        ['id' => 4, 'link' => 'menu.action.index', 'menu' => 'menu.index', 'name' => 'View permission actions', 'type' => 8],
        ['id' => 9, 'link' => 'role.permission', 'menu' => 'role.index', 'name' => 'View role permissions', 'type' => 8],
        ['id' => 219, 'link' => 'role.permission.edit', 'menu' => 'role.index', 'name' => 'Edit role permissions', 'type' => 2, 'grant_from' => ['role.permission']],
        ['id' => 30, 'link' => 'admin.reset', 'menu' => 'admin.index', 'name' => 'Reset admin passwords', 'type' => 2],
        ['id' => 206, 'link' => 'subscriber.sendEmail', 'menu' => 'subscriber.index', 'name' => 'Email subscribers', 'type' => 2, 'grant_from' => ['subscriber.index']],
        ['id' => 160, 'link' => 'chat.show', 'menu' => 'chat.index', 'name' => 'View conversations', 'type' => 8],
        ['id' => 161, 'link' => 'chat.reply', 'menu' => 'chat.index', 'name' => 'Reply to conversations', 'type' => 2],
        ['id' => 162, 'link' => 'chat.status', 'menu' => 'chat.index', 'name' => 'Update conversation status', 'type' => 3],
        ['id' => 163, 'link' => 'chat.settings.update', 'menu' => 'chat.index', 'name' => 'Update chat settings', 'type' => 2],
        ['id' => 164, 'link' => 'chat.faq.store', 'menu' => 'chat.index', 'name' => 'Add chat questions', 'type' => 1],
        ['id' => 165, 'link' => 'chat.faq.update', 'menu' => 'chat.index', 'name' => 'Edit chat questions', 'type' => 2],
        ['id' => 166, 'link' => 'chat.faq.destroy', 'menu' => 'chat.index', 'name' => 'Delete chat questions', 'type' => 4],
        ['id' => 167, 'link' => 'chat.faq.index', 'menu' => 'chat.index', 'name' => 'View chat questions and settings', 'type' => 8],
        ['id' => 168, 'link' => 'seo.metadata.edit', 'menu' => 'seo.index', 'name' => 'Edit SEO metadata', 'type' => 2, 'rename_from' => 'seo.metadata.manage', 'grant_from' => ['seo.metadata.manage']],
        ['id' => 169, 'link' => 'seo.redirects.create', 'menu' => 'seo.index', 'name' => 'Create SEO redirects', 'type' => 1, 'rename_from' => 'seo.redirects.manage', 'grant_from' => ['seo.redirects.manage']],
        ['id' => 223, 'link' => 'seo.redirects.destroy', 'menu' => 'seo.index', 'name' => 'Delete SEO redirects', 'type' => 4, 'grant_from' => ['seo.redirects.manage']],
        ['id' => 224, 'link' => 'donations.review.resolve', 'menu' => 'donations.index', 'name' => 'Resolve reviewed donations', 'type' => 2],
        ['id' => 225, 'link' => 'seo.metadata.view', 'menu' => 'seo.index', 'name' => 'View SEO metadata', 'type' => 8, 'grant_from' => ['seo.metadata.edit', 'seo.metadata.manage']],
        ['id' => 226, 'link' => 'seo.metadata.review', 'menu' => 'seo.index', 'name' => 'Review and approve SEO metadata', 'type' => 3],
        ['id' => 227, 'link' => 'seo.metadata.restore', 'menu' => 'seo.index', 'name' => 'Restore SEO revisions', 'type' => 2, 'grant_from' => ['seo.metadata.edit', 'seo.metadata.manage']],
        ['id' => 228, 'link' => 'seo.technical.scan', 'menu' => 'seo.technical.index', 'name' => 'Run technical SEO scans', 'type' => 1],
        ['id' => 229, 'link' => 'seo.technical.ignore', 'menu' => 'seo.technical.index', 'name' => 'Manage technical SEO ignore rules', 'type' => 2],
        ['id' => 230, 'link' => 'seo.technical.redirect', 'menu' => 'seo.technical.index', 'name' => 'Create redirects from the 404 inbox', 'type' => 1],
        ['id' => 232, 'link' => 'donations.allocate', 'menu' => 'donations.index', 'name' => 'Allocate successful donations', 'type' => 2],
        ['id' => 231, 'link' => 'seo.canonical.external', 'menu' => 'seo.index', 'name' => 'Use external canonical URLs', 'type' => 2],
        ['id' => 233, 'link' => 'comment.publish', 'menu' => 'comment.index', 'name' => 'Publish or unpublish comments', 'type' => 3, 'grant_from' => ['comment.destroy', 'page.status']],
        ['id' => 234, 'link' => 'volunteer.export', 'menu' => 'volunteer.index', 'name' => 'Export volunteer applications', 'type' => 8],
        ['id' => 235, 'link' => 'subscriber.export', 'menu' => 'subscriber.index', 'name' => 'Export subscribers', 'type' => 8],
    ];

    private const ACTION_SUFFIXES = [
        'create' => 'create',
        'edit' => 'edit',
        'publish' => 'status',
        'delete' => 'destroy',
    ];

    private const ACTION_TYPES = [
        'create' => 1,
        'edit' => 2,
        'publish' => 3,
        'delete' => 4,
    ];

    private const ACTION_VERBS = [
        'create' => 'Create',
        'edit' => 'Edit',
        'publish' => 'Publish or unpublish',
        'delete' => 'Delete',
    ];

    /** @return array<string, array<string, mixed>> */
    public static function menus(): array
    {
        $menus = [];
        foreach (self::MENU_DEFINITIONS as $definition) {
            $definition += [
                'icon' => null,
                'order_by' => 100 + count($menus),
                'status' => 1,
                'grant_from' => [],
            ];
            $menus[$definition['link']] = $definition;
        }

        return $menus;
    }

    /** @return array<string, array<string, mixed>> */
    public static function actions(): array
    {
        $actions = [];
        foreach (self::RESOURCE_ACTION_DEFINITIONS as $prefix => $resource) {
            foreach ($resource['actions'] as $operation => $id) {
                $link = $prefix . '.' . self::ACTION_SUFFIXES[$operation];
                $grantFrom = $resource['grant_from_by_action'][$operation]
                    ?? $resource['grant_from']
                    ?? [];
                $actions[$link] = [
                    'id' => $id,
                    'link' => $link,
                    'menu' => $resource['menu'],
                    'name' => self::ACTION_VERBS[$operation] . ' ' . $resource['label'],
                    'type' => self::ACTION_TYPES[$operation],
                    'order_by' => array_search($operation, array_keys(self::ACTION_SUFFIXES), true) + 1,
                    'status' => 1,
                    'grant_from' => $grantFrom,
                ];
            }
        }

        foreach (self::CUSTOM_ACTION_DEFINITIONS as $definition) {
            $definition += ['order_by' => 10, 'status' => 1, 'grant_from' => []];
            $actions[$definition['link']] = $definition;
        }

        return $actions;
    }

    /** @return list<string> */
    public static function capabilitiesForRoute(string $routeName): array
    {
        if (self::isEssentialRoute($routeName)) {
            return [];
        }

        $map = self::routeMap();
        if (!array_key_exists($routeName, $map)) {
            // Views sometimes need to ask about a canonical capability rather
            // than an HTTP endpoint. Accept registered capabilities directly,
            // while keeping every explicit route override authoritative.
            return self::isRegisteredCapability($routeName) ? [$routeName] : [];
        }

        return (array) $map[$routeName];
    }

    public static function isEssentialRoute(string $routeName): bool
    {
        return in_array($routeName, self::ESSENTIAL_ROUTES, true);
    }

    /** @return list<string> */
    public static function registeredCapabilities(): array
    {
        return array_values(array_unique(array_merge(array_keys(self::menus()), array_keys(self::actions()))));
    }

    public static function isRegisteredCapability(string $capability): bool
    {
        return in_array($capability, self::registeredCapabilities(), true);
    }

    /** @return array<string, string|list<string>> */
    public static function routeMap(): array
    {
        $map = [];
        foreach (self::RESOURCE_ACTION_DEFINITIONS as $prefix => $resource) {
            $viewCapability = $resource['menu'];
            foreach (['index', 'show', 'image', 'photo', 'thumbnail', 'api', 'export', 'export.excel'] as $suffix) {
                $map[$prefix . '.' . $suffix] = $viewCapability;
            }
            if (isset($resource['actions']['create'])) {
                $map[$prefix . '.create'] = $prefix . '.create';
                $map[$prefix . '.store'] = $prefix . '.create';
            }
            if (isset($resource['actions']['edit'])) {
                $map[$prefix . '.edit'] = $prefix . '.edit';
                $map[$prefix . '.update'] = $prefix . '.edit';
                $map[$prefix . '.workflow'] = $prefix . '.edit';
            }
            if (isset($resource['actions']['publish'])) {
                $map[$prefix . '.status'] = $prefix . '.status';
            }
            if (isset($resource['actions']['delete'])) {
                $map[$prefix . '.destroy'] = $prefix . '.destroy';
                $map[$prefix . '.force-destroy'] = $prefix . '.destroy';
            }
        }

        return array_replace($map, [
            'donations.index' => 'donations.index',
            'donations.search' => 'donations.index',
            'donations.search.clear' => 'donations.index',
            'donations.review.resolve' => 'donations.review.resolve',
            'donations.allocate' => 'donations.allocate',
            'sponsorships.index' => 'sponsorships.index',
            'sponsorships.search' => 'sponsorships.index',
            'sponsorships.search.clear' => 'sponsorships.index',
            'volunteer.index' => 'volunteer.index',
            'volunteer.search' => 'volunteer.index',
            'volunteer.search.clear' => 'volunteer.index',
            'volunteer.export' => 'volunteer.export',
            'volunteer.export.excel' => 'volunteer.export',
            'page.bulk.copy' => 'page.edit',
            'page.bulk.destroy' => 'page.destroy',
            'page.view' => 'page.view',
            'page.comments.search' => 'page.view',
            'page.comments.search.clear' => 'page.view',
            'page.status.comment' => 'comment.publish',
            'page.is-comments' => 'page.status',
            'page.trash.index' => 'page.trash.view',
            'page.trash.restore' => 'page.trash.edit',
            'page.trash.force-destroy' => 'page.trash.destroy',
            'content.trash.index' => 'content.trash.index',
            'content.trash.restore' => 'content.trash.edit',
            'content.trash.force-destroy' => 'content.trash.destroy',
            'translations.toggle' => 'translations.status',
            'seo.index' => ['seo.metadata.view', 'seo.metadata.edit'],
            'seo.update' => 'seo.metadata.edit',
            'seo.content.edit' => ['seo.metadata.view', 'seo.metadata.edit'],
            'seo.content.update' => 'seo.metadata.edit',
            'seo.revisions.restore' => ['seo.metadata.restore', 'seo.metadata.edit'],
            'seo.bulk.index' => ['seo.metadata.view', 'seo.metadata.edit'],
            'seo.bulk.update' => 'seo.metadata.edit',
            'seo.bulk.export' => ['seo.metadata.view', 'seo.metadata.edit'],
            'seo.media.index' => ['seo.metadata.view', 'seo.metadata.edit'],
            'seo.review.request' => 'seo.metadata.edit',
            'seo.review.resolve' => 'seo.metadata.review',
            'seo.redirects.index' => ['seo.redirects.create', 'seo.redirects.destroy'],
            'seo.redirects.store' => 'seo.redirects.create',
            'seo.redirects.destroy' => 'seo.redirects.destroy',
            'seo.technical.index' => 'seo.technical.index',
            'seo.technical.scan' => 'seo.technical.scan',
            'seo.technical.issues.ignore' => 'seo.technical.ignore',
            'seo.technical.ignore-rules.destroy' => 'seo.technical.ignore',
            'seo.technical.not-found.redirect' => 'seo.technical.redirect',
            'seo.technical.not-found.dismiss' => 'seo.technical.ignore',
            'page.builder.edit' => 'page.builder.edit',
            'page.builder.preview' => ['page.index', 'page.builder.edit'],
            'page.builder.update' => 'page.builder.edit',
            'page.builder.simple.save' => 'page.builder.edit',
            'page.builder.media.store' => 'page.builder.create',
            'page.builder.block.store' => 'page.builder.create',
            'page.builder.block.reorder' => 'page.builder.edit',
            'page.builder.block.update' => 'page.builder.edit',
            'page.builder.block.duplicate' => 'page.builder.create',
            'page.builder.block.promote' => 'page.builder.create',
            'page.builder.reusable.attach' => 'page.builder.edit',
            'page.builder.block.detach' => 'page.builder.edit',
            'page.builder.block.destroy' => 'page.builder.destroy',
            'page.builder.revision.restore' => 'page.builder.edit',
            'reusable-blocks.restore' => 'reusable-blocks.edit',
            'reusable-blocks.force-destroy' => 'reusable-blocks.destroy',
            'media.bulk' => 'media.destroy',
            'media.restore' => 'media.edit',
            'media.force-destroy' => 'media.destroy',
            'subscriber.filter' => 'subscriber.index',
            'subscriber.search.clear' => 'subscriber.index',
            'subscriber.export' => 'subscriber.export',
            'subscriber-excel-download.index' => 'subscriber.export',
            'subscriber.sendEmail' => 'subscriber.sendEmail',
            'comment.search' => 'comment.index',
            'comment.search.clear' => 'comment.index',
            'user.index' => 'user.index',
            'user.search' => 'user.index',
            'user.search.clear' => 'user.index',
            'user.show' => 'user.index',
            'admin.user.search' => 'user.index',
            'report.youtubeMeta' => 'report.youtubeMeta',
            'report.youtubeMeta.search' => 'report.youtubeMeta',
            'report.youtubeMeta.search.clear' => 'report.youtubeMeta',
            'page.menu.trash' => 'page.menu.trash.view',
            'page.menu.restore' => 'page.menu.edit',
            'page.menu.force-destroy' => 'page.menu.destroy',
            'page.menu.reorder' => 'page.menu.edit',
            'page.menu.item.update' => 'page.menu.edit',
            'page.menu.showSlug' => 'page.menu.index',
            'page.menu.showParent' => 'page.menu.index',
            'menu.action.index' => 'menu.action.index',
            'role.permission' => 'role.permission',
            'role.permission.store' => 'role.permission.edit',
            'admin.reset' => 'admin.reset',
            'admin.reset.perform' => 'admin.reset',
            'admin.search' => 'admin.index',
            'admin.search.clear' => 'admin.index',
            'admin.image' => 'admin.index',
            'user-approval.index' => 'user-approval.index',
            'user-approval.search' => 'user-approval.index',
            'user-approval.search.clear' => 'user-approval.index',
            'user-approval.show' => 'user-approval.index',
            'user-approval.update.approve' => 'user-approval.edit',
            'user-approval.update.reject' => 'user-approval.edit',
            'splash.screen.index' => 'splash.screen.index',
            'splash.screen.store' => 'splash.screen.create',
            'contact-message.index' => 'contact-message.index',
            'contact-message.search' => 'contact-message.index',
            'contact-message.search.clear' => 'contact-message.index',
            'contact-message.show' => 'contact-message.index',
            'chat.index' => 'chat.index',
            'chat.search' => 'chat.index',
            'chat.search.clear' => 'chat.index',
            'chat.faq.index' => 'chat.faq.index',
            'chat.settings.update' => 'chat.settings.update',
            'chat.faq.store' => 'chat.faq.store',
            'chat.faq.update' => 'chat.faq.update',
            'chat.faq.destroy' => 'chat.faq.destroy',
            'chat.show' => 'chat.show',
            'chat.reply' => 'chat.reply',
            'chat.status' => 'chat.status',
            'unisharp.lfm.show' => 'media.index',
            'unisharp.lfm.getErrors' => 'media.index',
            'unisharp.lfm.getItems' => 'media.index',
            'unisharp.lfm.move' => 'media.index',
            'unisharp.lfm.getFolders' => 'media.index',
            'unisharp.lfm.getCrop' => 'media.index',
            'unisharp.lfm.getResize' => 'media.index',
            'unisharp.lfm.getDownload' => 'media.index',
            'unisharp.lfm.upload' => 'media.create',
            'unisharp.lfm.getAddfolder' => 'media.create',
            'unisharp.lfm.getNewCropImage' => 'media.create',
            'unisharp.lfm.performResizeNew' => 'media.create',
            'unisharp.lfm.doMove' => 'media.edit',
            'unisharp.lfm.getCropImage' => 'media.edit',
            'unisharp.lfm.getRename' => 'media.edit',
            'unisharp.lfm.performResize' => 'media.edit',
            'unisharp.lfm.getDelete' => 'media.destroy',
            'unisharp.lfm.' => 'media.index',
        ]);
    }
}
