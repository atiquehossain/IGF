<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\PageMenu;
use App\Models\Banner;
use App\Models\Page;
use App\Models\Category;
use App\Models\Tag;

use App\Rules\ValidateUniqueRule;

use App\Helper\Seq;
use App\Helper\StaticUtil;
use App\Helper\Translation;
use App\Services\ContentSanitizer;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class PageMenuController extends Controller
{
    private const MAX_MENU_DEPTH = 3;
    private const LEGACY_MENU_LOCATIONS = ['main', 'middle', 'footer'];

    public function __construct(private ContentSanitizer $sanitizer)
    {
    }

    public function index(Request $request)
    {
        $location = in_array($request->string('location')->toString(), ['main', 'footer'], true)
            ? $request->string('location')->toString()
            : 'main';
        $locale = mb_substr($request->string('locale', app()->getLocale())->toString(), 0, 10) ?: 'en';
        $menuItems = PageMenu::query()
            ->where('language', $locale)
            ->where('type', $location)
            ->orderBy('order_by')
            ->orderBy('id')
            ->get();
        $menuTree = $this->editorTree($menuItems);
        $builtInDestinations = collect(StaticUtil::pageRoute())
            ->reject(fn ($route) => in_array($route->id, ['frontend.page', 'frontend.category', 'frontend.project'], true))
            ->values();
        $pages = Page::query()->publiclyAvailable()->where('language', $locale)->orderBy('name')->get(['name', 'slug']);
        $categories = Category::query()->where('language', $locale)->where('status', 1)->orderBy('name')->get(['name', 'slug']);
        $projects = Tag::query()->where('status', 1)->orderBy('name')->get(['name', 'slug']);

        return view('admin.page_menu.index', [
            'title' => 'Navigation',
            'location' => $location,
            'locale' => $locale,
            'locations' => ['main' => 'Header & mobile', 'footer' => 'Footer'],
            'translations' => Translation::languageList(),
            'menuTree' => $menuTree,
            'destinationGroups' => [
                'route' => $builtInDestinations->map(fn ($item) => ['value' => $item->id, 'label' => $item->name])->all(),
                'page' => $pages->map(fn ($item) => ['value' => $item->slug, 'label' => $item->name])->all(),
                'category' => $categories->map(fn ($item) => ['value' => $item->slug, 'label' => $item->name])->all(),
                'project' => $projects->map(fn ($item) => ['value' => $item->slug, 'label' => $item->name])->all(),
            ],
        ]);
    }

    public function create(Request $request)
    {
        return redirect()->route('page.menu.index')->with([
            'message' => 'Add and arrange navigation items from this one simple screen.',
            'alert-type' => 'info',
        ]);
    }

    public function store(Request $request)
    {
        if ($request->boolean('simple')) {
            return $this->storeSimple($request);
        }

        $this->validate(request(), [
            'language' => ['required', 'array', 'min:1'],
            'language.*' => ['required', 'string', 'max:10', 'distinct'],
            'name' => ['required', 'array'],
            'name.*' =>  ['required', new ValidateUniqueRule('page_menus'), 'nullable'],
            'description.*' => ['nullable', 'string', 'max:255'],
            'parent' => ['required', 'array'],
            'type' => ['required', 'array'],
            'type.*' => ['required', Rule::in(self::LEGACY_MENU_LOCATIONS)],
            'link' => ['required', 'array'],
            'link.*' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'array'],
            'slug.*' => ['nullable', 'string', 'max:2048'],
        ]);

        try {
            $uuid = Seq::uuidV4();
            $scopes = collect($request->language)->map(fn ($language): array => [
                'language' => (string) $language,
                'type' => (string) $request->input("type.{$language}"),
            ])->all();
            $this->mutateMenuTrees($scopes, function () use ($request, $uuid): void {
                foreach ($request->language as $language) {
                    $location = (string) $request->input("type.{$language}");
                    $parentId = $this->normalizeParentId(
                        $request->input("parent.{$language}"),
                        "parent.{$language}"
                    );
                    $parent = $this->validatedParent(
                        $parentId,
                        (string) $language,
                        $location,
                        null,
                        $uuid,
                        "parent.{$language}"
                    );
                    [$link, $slug] = $this->sanitizeLegacyDestination(
                        $request->input("link.{$language}"),
                        $request->input("slug.{$language}"),
                        "slug.{$language}"
                    );
                    PageMenu::create([
                        'uuid' => $uuid,
                        'parent_id' => $parent?->id,
                        'name' => $request->name[$language],
                        'description' => $this->plainDescription($request->description[$language] ?? null),
                        'type' => $location,
                        'link' => $link,
                        'slug' => $slug,
                        'icon' => $request->icon[$language] ?? null,
                        'banner_id' => $request->banner_id[$language] ?? null,
                        'language' => $language,
                        'order_by' => $request->order_by[$language] ?? null,
                        'status' => 0,
                    ]);
                }
            });

            $notification = array(
                'message' => $request->Lang->Common->Form->AddedSuccessfully,
                'alert-type' => 'success',
            );
            if (@$request->save_and_update) {
                return redirect(route('page.menu.edit', $uuid))->with($notification);
            } else {
                return redirect(route('page.menu.index'))->with($notification);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            $notification = array(
                'message' => $request->Lang->Common->Form->NotCreate,
                'alert-type' => 'error',
            );
            return back()->with($notification);
        }
    }

    public function show($id = null, Request $request)
    {
        return redirect()->route('page.menu.index')->with('message', 'Navigation details are managed from the navigation editor.');
    }

    public function showSlug($type = null, $lang = null, Request $request)
    {
        try {
            $data = [];
            if ($type == 'page') {
                $data = Page::select('name', 'slug')->where('status', 1)->where('language', $lang)->orderBy('order_by', 'ASC')->get();
            } else if ($type == 'category') {
                $data = Category::select('name', 'slug')->where('status', 1)->where('language', $lang)
                    ->where(function ($query) {
                        $query->whereNull('type')
                            ->orWhere('type', '!=', 'category-services');
                    })->orderBy('id', 'ASC')->get();
            } else if ($type == 'project') {
                $data = Tag::select('name', 'slug')->where('status', 1)->orderBy('id', 'ASC')->get();
            }
            $response = ['data' => $data];
            return response($response, 200);
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->DataNotFound], 403);
        }
    }

    public function showParent($type = null, $lang = null, Request $request)
    {
        try {
            if (!in_array($type, self::LEGACY_MENU_LOCATIONS, true)) {
                throw ValidationException::withMessages(['type' => 'Choose a valid menu location.']);
            }
            $movingUuid = $request->string('exclude_uuid')->toString() ?: null;
            $moving = $movingUuid
                ? PageMenu::query()->where('uuid', $movingUuid)->where('language', $lang)->first()
                : null;
            $movingBranchHeight = $moving
                ? $this->subtreeHeight($moving->id, (string) $lang, (string) $moving->type, 'parent')
                : null;
            $data = PageMenu::query()
                ->where('status', 1)
                ->where('type', $type)
                ->where('language', $lang)
                ->orderBy('order_by')
                ->orderBy('id')
                ->get()
                ->filter(function (PageMenu $candidate) use ($lang, $type, $moving, $movingUuid, $movingBranchHeight): bool {
                    try {
                        $this->validatedParent(
                            $candidate->id,
                            (string) $lang,
                            (string) $type,
                            $moving?->id,
                            $movingUuid,
                            'parent',
                            $movingBranchHeight
                        );

                        return true;
                    } catch (ValidationException) {
                        return false;
                    }
                })
                ->map(fn (PageMenu $candidate): array => [
                    'id' => $candidate->id,
                    'name' => $candidate->name,
                ])
                ->values();
            $response = ['data' => $data];
            return response($response, 200);
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->DataNotFound], 403);
        }
    }

    public function edit($id = null, Request $request)
    {
        $title = $request->Lang->Menu->Page . " " . $request->Lang->MenuTitle . " " . $request->Lang->Common->Update;
        try {
            $translations = Translation::languageList();
            $menuList = PageMenu::Where('uuid', $id)->get();
            $bannerList = Banner::where('type', 'banner-about')->where('status', 1)->get();
            $pageRoute = StaticUtil::pageRoute();
            $categorylist = Category::select('categories.*')->where('status', 1)->get();

            return view('admin.page_menu.edit')->with(compact('title', 'menuList', 'pageRoute', 'id', 'categorylist', 'bannerList', 'translations'));
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->DataNotFound], 403);
        }
    }

    public function update(Request $request)
    {

        $this->validate(request(), [
            'uuid' => ['required', 'uuid'],
            'language' => ['required', 'array', 'min:1'],
            'language.*' => ['required', 'string', 'max:10', 'distinct'],
            'name' => ['required', 'array'],
            'name.*' =>  ['required', new ValidateUniqueRule('page_menus|uuid,' . $request->uuid), 'nullable'],
            'description.*' => ['nullable', 'string', 'max:255'],
            'parent' => ['required', 'array'],
            'type' => ['required', 'array'],
            'type.*' => ['required', Rule::in(self::LEGACY_MENU_LOCATIONS)],
            'link' => ['required', 'array'],
            'link.*' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'array'],
            'slug.*' => ['nullable', 'string', 'max:2048'],
        ]);

        try {
            $uuid = $request->uuid;
            $requestedScopes = collect($request->language)->map(fn ($language): array => [
                'language' => (string) $language,
                'type' => (string) $request->input("type.{$language}"),
            ])->all();
            $currentScopes = $this->menuScopes(PageMenu::withTrashed()->where('uuid', $uuid)->get(['language', 'type']));
            $scopes = [...$requestedScopes, ...$currentScopes];
            $this->mutateMenuTrees($scopes, function () use ($request, $uuid, $scopes): void {
                $logicalMenus = PageMenu::query()
                    ->where('uuid', $uuid)
                    ->orderBy('language')
                    ->orderBy('id')
                    ->get();
                $this->assertMenusInLockedScopes($logicalMenus, $scopes);
                if (!$logicalMenus->contains('language', 'en')) {
                    throw ValidationException::withMessages([
                        'uuid' => $request->Lang->Common->Form->NotFound ?? 'The navigation item no longer exists.',
                    ]);
                }

                foreach ($request->language as $language) {
                    // Route identity comes from the URL UUID and locale. A
                    // hidden numeric id is presentation data and must never
                    // be able to move an unrelated navigation row.
                    $pageMenu = $logicalMenus->firstWhere('language', $language);
                    $location = (string) $request->input("type.{$language}");
                    $parentId = $this->normalizeParentId(
                        $request->input("parent.{$language}"),
                        "parent.{$language}"
                    );
                    $parent = $this->validatedParent(
                        $parentId,
                        (string) $language,
                        $location,
                        $pageMenu?->id,
                        $uuid,
                        "parent.{$language}"
                    );
                    if ($pageMenu
                        && $pageMenu->type !== $location
                        && PageMenu::withTrashed()->where('parent_id', $pageMenu->id)->exists()) {
                        throw ValidationException::withMessages([
                            "type.{$language}" => 'Move child navigation items out before changing this menu location.',
                        ]);
                    }
                    [$link, $slug] = $this->sanitizeLegacyDestination(
                        $request->input("link.{$language}"),
                        $request->input("slug.{$language}"),
                        "slug.{$language}"
                    );
                    $values = [
                        'parent_id' => $parent?->id,
                        'name' => @$request->name[$language],
                        'description' => $this->plainDescription($request->description[$language] ?? null),
                        'type' => $location,
                        'link' => $link,
                        'icon' => @$request->icon[$language],
                        'banner_id' => @$request->banner_id[$language],
                        'slug' => $slug,
                        'language' => $language,
                        'order_by' => @$request->order_by[$language],
                    ];

                    if ($pageMenu) {
                        $pageMenu->update($values);
                    } else {
                        $logicalMenus->push(PageMenu::create($values + [
                            'uuid' => $uuid,
                            'status' => 0,
                        ]));
                    }
                }
            });

            $notification = array(
                'message' => $request->Lang->Common->Form->UpdatedSuccessfully,
                'alert-type' => 'success',
            );

            if (@$request->save_and_update) {
                return redirect(route('page.menu.edit', $uuid))->with($notification);
            } else {
                return redirect(route('page.menu.index'))->with($notification);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            $notification = array(
                'message' => $request->Lang->Common->Form->NotUpdate,
                'alert-type' => 'error',
            );
            return back()->with($notification);
        }
    }

    public function status(Request $request, $id = null)
    {
        try {
            if ($request->ajax()) {
                $preflight = PageMenu::withTrashed()->where('uuid', $id)->get(['language', 'type']);
                $scopes = $this->menuScopes($preflight);
                return $this->mutateMenuTrees($scopes, function () use ($request, $id, $scopes) {
                    $menus = PageMenu::where('uuid', $id)->get();
                    $this->assertMenusInLockedScopes($menus, $scopes);
                    $data = $menus->firstWhere('language', 'en');
                    if (!$data) {
                        abort(404);
                    }
                    $status = ((int) $data->status) ^ 1;
                    PageMenu::whereIn('id', $menus->pluck('id'))->update(['status' => $status]);

                    return response(['message' => ($status ? $request->Lang->Common->Form->PublishSuccessfully : $request->Lang->Common->Form->UnpublishSuccessfully)], 200);
                });
            }
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotUpdate], 403);
        }
    }

    public function destroy($id = null, Request $request)
    {
        try {
            $preflight = PageMenu::withTrashed()->where('uuid', $id)->get(['language', 'type']);
            $scopes = $this->menuScopes($preflight);
            return $this->mutateMenuTrees($scopes, function () use ($id, $request, $scopes) {
                $menus = PageMenu::where('uuid', $id)->get();
                $this->assertMenusInLockedScopes($menus, $scopes);
                if ($menus->isEmpty()) {
                    abort(404);
                }
                if (PageMenu::whereIn('parent_id', $menus->pluck('id'))->exists()) {
                    return response(['message' => 'Move submenu items out before removing their parent.'], 422);
                }
                PageMenu::whereIn('id', $menus->pluck('id'))->update(['deleted_by' => auth('admin')->id()]);
                PageMenu::whereIn('id', $menus->pluck('id'))->delete();

                return response(['message' => $request->Lang->Common->Form->DeleteSuccessfully], 200);
            });
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotDelete], 403);
        }
    }

    public function trash(Request $request)
    {
        $title = 'Navigation trash';
        $search = $request->search;
        $pageMenus = PageMenu::onlyTrashed()
            ->where('language', app()->getLocale())
            ->when($search, fn ($query) => $query->where('name', 'like', '%' . $search . '%'))
            ->latest('deleted_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.page_menu.trash', compact('title', 'pageMenus', 'search'));
    }

    public function restore(string $uuid, Request $request)
    {
        $trashed = PageMenu::onlyTrashed()->where('uuid', $uuid)->get(['language', 'type']);
        abort_if($trashed->isEmpty(), 404);
        $scopes = $this->menuScopes(PageMenu::withTrashed()->where('uuid', $uuid)->get(['language', 'type']));
        $restoredAsDraft = false;
        $this->mutateMenuTrees($scopes, function () use ($uuid, $request, $scopes, &$restoredAsDraft): void {
            $restoredAsDraft = false;
            $menus = PageMenu::onlyTrashed()->where('uuid', $uuid)->get();
            $this->assertMenusInLockedScopes($menus, $scopes);
            abort_if($menus->isEmpty(), 404);
            foreach ($menus as $menu) {
                $this->validatedParent(
                    $menu->parent_id ? (int) $menu->parent_id : null,
                    (string) $menu->language,
                    (string) $menu->type,
                    (int) $menu->id,
                    (string) $menu->uuid,
                    'parent'
                );
            }

            $ids = $menus->pluck('id');
            PageMenu::onlyTrashed()->whereIn('id', $ids)->restore();
            $updates = ['deleted_by' => null];
            if (!$this->canChangeStatus($request)) {
                $updates['status'] = 0;
                $restoredAsDraft = $menus->contains(fn (PageMenu $menu): bool => (bool) $menu->status);
            }
            PageMenu::whereIn('id', $ids)->update($updates);
        });

        return back()->with([
            'message' => $restoredAsDraft
                ? 'Navigation item restored hidden in every language. Publication access is required to show it on the website.'
                : 'Navigation item restored in every language.',
            'alert-type' => 'success',
        ]);
    }

    public function forceDestroy(string $uuid, Request $request)
    {
        $trashed = PageMenu::onlyTrashed()->where('uuid', $uuid)->get(['language', 'type']);
        abort_if($trashed->isEmpty(), 404);
        $scopes = $this->menuScopes(PageMenu::withTrashed()->where('uuid', $uuid)->get(['language', 'type']));
        $this->mutateMenuTrees($scopes, function () use ($uuid, $scopes): void {
            $menus = PageMenu::onlyTrashed()->where('uuid', $uuid)->get();
            $this->assertMenusInLockedScopes($menus, $scopes);
            abort_if($menus->isEmpty(), 404);
            $ids = $menus->pluck('id');
            abort_if(
                PageMenu::withTrashed()->whereIn('parent_id', $ids)->exists(),
                422,
                'Restore or remove child navigation items before permanently deleting this parent.'
            );
            PageMenu::onlyTrashed()->whereIn('id', $ids)->forceDelete();
        });

        return back()->with(['message' => 'Navigation item permanently deleted.', 'alert-type' => 'success']);
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'location' => ['required', Rule::in(['main', 'footer'])],
            'items' => ['required', 'array'],
            'items.*.uuid' => ['required', 'uuid', 'distinct'],
            'items.*.parent_uuid' => ['nullable', 'uuid'],
            'items.*.order' => ['required', 'integer', 'min:0'],
        ]);

        $this->mutateMenuTrees([[
            'language' => $data['locale'],
            'type' => $data['location'],
        ]], function () use ($data): void {
            $menus = PageMenu::where('language', $data['locale'])
                ->where('type', $data['location'])
                ->whereIn('uuid', collect($data['items'])->pluck('uuid'))
                ->get()
                ->keyBy('uuid');
            $expectedCount = PageMenu::where('language', $data['locale'])->where('type', $data['location'])->count();
            if ($menus->count() !== count($data['items']) || $menus->count() !== $expectedCount) {
                throw ValidationException::withMessages(['items' => 'Submit every item from the selected menu location exactly once.']);
            }

            $parentByUuid = collect($data['items'])->mapWithKeys(fn ($item) => [$item['uuid'] => $item['parent_uuid'] ?? null]);
            foreach ($parentByUuid as $uuid => $parentUuid) {
                $seen = [$uuid => true];
                $depth = 1;
                while ($parentUuid) {
                    if (!$menus->has($parentUuid)) {
                        throw ValidationException::withMessages(['items' => 'A parent navigation item is missing from the submitted tree.']);
                    }
                    if (isset($seen[$parentUuid])) {
                        throw ValidationException::withMessages(['items' => 'Navigation items cannot contain a circular parent relationship.']);
                    }
                    $seen[$parentUuid] = true;
                    $depth++;
                    if ($depth > self::MAX_MENU_DEPTH) {
                        throw ValidationException::withMessages(['items' => 'Navigation supports at most three levels.']);
                    }
                    $parentUuid = $parentByUuid->get($parentUuid);
                }
            }

            foreach ($data['items'] as $item) {
                $menus[$item['uuid']]->update([
                    'parent_id' => empty($item['parent_uuid']) ? null : $menus[$item['parent_uuid']]->id,
                    'order_by' => $item['order'],
                ]);
            }
        });

        return response()->json(['message' => 'Navigation order saved.']);
    }

    public function quickUpdate(string $uuid, Request $request)
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'enabled' => ['required', 'boolean'],
            'custom_url' => ['nullable', 'string', 'max:2048'],
        ]);
        $preflight = PageMenu::withTrashed()
            ->where('uuid', $uuid)
            ->where('language', $data['locale'])
            ->get(['language', 'type']);
        $scopes = $this->menuScopes($preflight);

        return $this->mutateMenuTrees($scopes, function () use ($data, $request, $scopes, $uuid) {
            $menu = PageMenu::where('uuid', $uuid)->where('language', $data['locale'])->firstOrFail();
            $this->assertMenusInLockedScopes(collect([$menu]), $scopes);
            $updates = [
                'name' => trim($data['label']),
                'description' => $this->plainDescription($data['description'] ?? null),
            ];
            if ($this->canChangeStatus($request)) {
                $updates['status'] = $data['enabled'];
            }

            if ($menu->link === 'custom') {
                $customUrl = $this->sanitizer->sanitizeUrl($data['custom_url'] ?? '');
                if ($customUrl === '') {
                    throw ValidationException::withMessages(['custom_url' => 'Enter a safe local, HTTP, HTTPS, email, or telephone link.']);
                }
                $updates['slug'] = $customUrl;
            }

            $menu->update($updates);

            return response()->json([
                'message' => 'Menu item saved.',
                'item' => [
                    'uuid' => $menu->uuid,
                    'name' => $menu->name,
                    'description' => $menu->description,
                    'status' => (bool) $menu->status,
                    'destination' => $this->destinationLabel($menu),
                ],
            ]);
        });
    }

    private function storeSimple(Request $request)
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'location' => ['required', Rule::in(['main', 'footer'])],
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'destination_type' => ['required', Rule::in(['route', 'page', 'category', 'project', 'custom', 'label'])],
            'destination' => ['nullable', 'string', 'max:2048'],
            'parent_uuid' => ['nullable', 'uuid'],
            'enabled' => ['required', 'boolean'],
        ]);
        [$link, $slug] = $this->resolveSimpleDestination(
            $data['destination_type'],
            $data['destination'] ?? '',
            $data['locale']
        );
        $menu = $this->mutateMenuTrees([[
            'language' => $data['locale'],
            'type' => $data['location'],
        ]], function () use ($data, $link, $slug, $request): PageMenu {
            $parent = null;
            if (!empty($data['parent_uuid'])) {
                $parent = PageMenu::query()
                    ->where('uuid', $data['parent_uuid'])
                    ->where('language', $data['locale'])
                    ->where('type', $data['location'])
                    ->first();
                if (!$parent) {
                    throw ValidationException::withMessages(['parent_uuid' => 'Choose a parent from the current menu location.']);
                }
                $parent = $this->validatedParent(
                    $parent->id,
                    $data['locale'],
                    $data['location'],
                    null,
                    null,
                    'parent_uuid'
                );
            }
            $order = ((int) PageMenu::query()
                ->where('language', $data['locale'])
                ->where('type', $data['location'])
                ->where('parent_id', $parent?->id)
                ->max('order_by')) + 1;

            return PageMenu::create([
                'uuid' => (string) Str::uuid(),
                'parent_id' => $parent?->id,
                'name' => trim($data['label']),
                'description' => $this->plainDescription($data['description'] ?? null),
                'type' => $data['location'],
                'link' => $link,
                'slug' => $slug,
                'icon' => null,
                'banner_id' => null,
                'language' => $data['locale'],
                'order_by' => $order,
                'status' => $this->canChangeStatus($request) ? $data['enabled'] : 0,
            ]);
        });

        return $request->expectsJson()
            ? response()->json(['message' => 'Menu item added.', 'item' => $menu], 201)
            : redirect()->route('page.menu.index', ['location' => $data['location'], 'locale' => $data['locale']])
                ->with(['message' => 'Menu item added.', 'alert-type' => 'success']);
    }

    private function normalizeParentId(mixed $value, string $field): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1) {
            throw ValidationException::withMessages([$field => 'Choose a valid parent navigation item.']);
        }

        return (int) $value;
    }

    private function validatedParent(
        ?int $parentId,
        string $locale,
        string $location,
        ?int $movingId,
        ?string $movingUuid,
        string $field,
        ?int $movingBranchHeight = null
    ): ?PageMenu {
        $parent = $parentId === null ? null : PageMenu::query()->whereKey($parentId)->first();
        if ($parentId !== null && (!$parent || $parent->language !== $locale || $parent->type !== $location)) {
            throw ValidationException::withMessages([
                $field => 'Choose an existing parent from the same language and menu location.',
            ]);
        }

        $depth = 1;
        $seen = $movingId === null ? [] : [$movingId => true];
        $cursor = $parent;
        while ($cursor) {
            if (($movingUuid && hash_equals($movingUuid, (string) $cursor->uuid)) || isset($seen[$cursor->id])) {
                throw ValidationException::withMessages([
                    $field => 'Navigation items cannot be their own parent or contain a circular parent relationship.',
                ]);
            }
            if ($cursor->language !== $locale || $cursor->type !== $location) {
                throw ValidationException::withMessages([
                    $field => 'Every parent must use the same language and menu location.',
                ]);
            }
            $seen[$cursor->id] = true;
            $depth++;
            if ($depth > self::MAX_MENU_DEPTH) {
                throw ValidationException::withMessages([
                    $field => 'Navigation supports at most three levels. Choose a parent at level one or two.',
                ]);
            }

            if (!$cursor->parent_id) {
                break;
            }
            $cursor = PageMenu::query()->whereKey($cursor->parent_id)->first();
            if (!$cursor) {
                throw ValidationException::withMessages([
                    $field => 'The selected parent has a missing or deleted ancestor.',
                ]);
            }
        }

        if ($movingId !== null
            && $depth + ($movingBranchHeight ?? $this->subtreeHeight($movingId, $locale, $location, $field)) - 1 > self::MAX_MENU_DEPTH) {
            throw ValidationException::withMessages([
                $field => 'Moving this branch there would create more than three navigation levels.',
            ]);
        }

        return $parent;
    }

    private function subtreeHeight(int $rootId, string $locale, string $location, string $field): int
    {
        $children = PageMenu::query()
            ->where('language', $locale)
            ->where('type', $location)
            ->get(['id', 'parent_id'])
            ->groupBy(fn (PageMenu $menu) => (string) $menu->parent_id);
        $walk = function (int $id, array $ancestors = []) use (&$walk, $children, $field): int {
            if (isset($ancestors[$id])) {
                throw ValidationException::withMessages([
                    $field => 'Navigation items cannot contain a circular parent relationship.',
                ]);
            }
            $ancestors[$id] = true;
            $height = 1;
            foreach ($children->get((string) $id, collect()) as $child) {
                $height = max($height, 1 + $walk((int) $child->id, $ancestors));
            }

            return $height;
        };

        return $walk($rootId);
    }

    private function canChangeStatus(Request $request): bool
    {
        return app(Permission::class)->allows($request->user('admin'), 'page.menu.status');
    }

    /**
     * Serialize structural mutations for each locale/location tree. MySQL uses
     * named advisory locks so even an empty scope is protected; PostgreSQL uses
     * transaction advisory locks; SQLite starts with a harmless write so its
     * database writer lock is obtained before any validation snapshot. Row
     * locks remain as defense in depth for every configured driver.
     *
     * @param array<int, array{language: mixed, type: mixed}> $scopes
     */
    private function mutateMenuTrees(array $scopes, callable $callback): mixed
    {
        $scopes = $this->normalizeMenuScopes($scopes);
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $mysqlLocks = [];

        try {
            if ($driver === 'mysql') {
                foreach ($scopes as $scope) {
                    $lockName = $this->menuScopeLockName($scope);
                    $result = $connection->selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName], false);
                    if ((int) data_get($result, 'acquired', 0) !== 1) {
                        throw ValidationException::withMessages([
                            'navigation' => 'Navigation is being changed by someone else. Try again in a moment.',
                        ]);
                    }
                    $mysqlLocks[] = $lockName;
                }
            }

            return DB::transaction(function () use ($callback, $connection, $driver, $scopes) {
                if ($driver === 'pgsql') {
                    foreach ($scopes as $scope) {
                        $connection->select('SELECT pg_advisory_xact_lock(1229866573, hashtext(?))', [
                            $this->menuScopeLockKey($scope),
                        ], false);
                    }
                } elseif ($driver === 'sqlite') {
                    // SQLite ignores SELECT ... FOR UPDATE. A no-op UPDATE is
                    // therefore deliberately the first statement in this
                    // transaction so competing writers cannot validate stale
                    // parent chains and both commit.
                    DB::update('UPDATE page_menus SET id = id WHERE id = (SELECT MIN(id) FROM page_menus)');
                }

                foreach ($scopes as $scope) {
                    PageMenu::withTrashed()
                        ->where('language', $scope['language'])
                        ->where('type', $scope['type'])
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get(['id']);
                }

                return $callback();
            }, 3);
        } finally {
            if ($driver === 'mysql') {
                foreach (array_reverse($mysqlLocks) as $lockName) {
                    try {
                        $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName], false);
                    } catch (Exception) {
                        // A lost connection releases MySQL named locks itself;
                        // never mask the original mutation error during cleanup.
                    }
                }
            }
        }
    }

    /** @param Collection<int, PageMenu> $menus */
    private function menuScopes(Collection $menus): array
    {
        return $menus->map(fn ($menu): array => [
            'language' => (string) $menu->language,
            'type' => (string) $menu->type,
        ])->all();
    }

    /**
     * @param Collection<int, PageMenu> $menus
     * @param array<int, array{language: mixed, type: mixed}> $scopes
     */
    private function assertMenusInLockedScopes(Collection $menus, array $scopes): void
    {
        $allowed = collect($this->normalizeMenuScopes($scopes))
            ->mapWithKeys(fn (array $scope): array => [$this->menuScopeKey($scope) => true]);
        if ($menus->contains(fn (PageMenu $menu): bool => !$allowed->has($this->menuScopeKey([
            'language' => (string) $menu->language,
            'type' => (string) $menu->type,
        ])))) {
            throw ValidationException::withMessages([
                'uuid' => 'The navigation item changed location. Reload the editor and try again.',
            ]);
        }
    }

    /**
     * @param array<int, array{language: mixed, type: mixed}> $scopes
     * @return array<int, array{language: string, type: string}>
     */
    private function normalizeMenuScopes(array $scopes): array
    {
        return collect($scopes)
            ->map(fn (array $scope): array => [
                'language' => trim((string) ($scope['language'] ?? '')),
                'type' => trim((string) ($scope['type'] ?? '')),
            ])
            ->filter(fn (array $scope): bool => $scope['language'] !== '' && $scope['type'] !== '')
            ->unique(fn (array $scope): string => $this->menuScopeKey($scope))
            ->sortBy(fn (array $scope): string => $this->menuScopeKey($scope), SORT_STRING)
            ->values()
            ->all();
    }

    /** @param array{language: string, type: string} $scope */
    private function menuScopeKey(array $scope): string
    {
        return base64_encode($scope['language']) . '.' . base64_encode($scope['type']);
    }

    /** @param array{language: string, type: string} $scope */
    private function menuScopeLockName(array $scope): string
    {
        return 'igf-page-menu-' . sha1($this->menuScopeLockKey($scope));
    }

    /** @param array{language: string, type: string} $scope */
    private function menuScopeLockKey(array $scope): string
    {
        return DB::connection()->getDatabaseName() . '|' . $this->menuScopeKey($scope);
    }

    private function sanitizeLegacyDestination(mixed $link, mixed $slug, string $field): array
    {
        $link = trim((string) $link);
        if ($link === '') {
            return [null, null];
        }
        if ($link === 'custom') {
            $safeUrl = $this->sanitizer->sanitizeUrl($slug);
            if ($safeUrl === '') {
                throw ValidationException::withMessages([
                    $field => 'Enter a safe local, HTTP, HTTPS, email, or telephone link.',
                ]);
            }

            return ['custom', $safeUrl];
        }

        $slug = is_string($slug) ? trim($slug) : null;

        return [$link, $slug === '' ? null : $slug];
    }

    private function resolveSimpleDestination(string $type, string $destination, string $locale): array
    {
        $destination = trim($destination);
        if ($type === 'label') {
            return [null, null];
        }
        if ($type === 'custom') {
            $safeUrl = $this->sanitizer->sanitizeUrl($destination);
            if ($safeUrl === '') {
                throw ValidationException::withMessages(['destination' => 'Enter a safe local, HTTP, HTTPS, email, or telephone link.']);
            }

            return ['custom', $safeUrl];
        }
        if ($destination === '') {
            throw ValidationException::withMessages(['destination' => 'Choose where this menu item should link.']);
        }
        if ($type === 'route') {
            $allowed = collect(StaticUtil::pageRoute())
                ->reject(fn ($route) => in_array($route->id, ['frontend.page', 'frontend.category', 'frontend.project'], true))
                ->pluck('id');
            if (!$allowed->contains($destination) || !\Route::has($destination)) {
                throw ValidationException::withMessages(['destination' => 'Choose an available built-in page.']);
            }

            return [$destination, null];
        }
        if ($type === 'page' && !Page::query()->publiclyAvailable()->where('language', $locale)->where('slug', $destination)->exists()) {
            throw ValidationException::withMessages(['destination' => 'Choose a published page.']);
        }
        if ($type === 'category' && !Category::query()->where('language', $locale)->where('status', 1)->where('slug', $destination)->exists()) {
            throw ValidationException::withMessages(['destination' => 'Choose an active category.']);
        }
        if ($type === 'project' && !Tag::query()->where('status', 1)->where('slug', $destination)->exists()) {
            throw ValidationException::withMessages(['destination' => 'Choose an active project.']);
        }

        return [match ($type) {
            'page' => 'frontend.page',
            'category' => 'frontend.category',
            'project' => 'frontend.project',
        }, $destination];
    }

    private function editorTree(Collection $menus): array
    {
        $ids = $menus->pluck('id')->all();
        $groups = $menus->groupBy(fn (PageMenu $menu) => $menu->parent_id && in_array($menu->parent_id, $ids, true)
            ? (string) $menu->parent_id
            : 'root');
        $walk = function (string $parentKey, array $ancestors = []) use (&$walk, $groups): array {
            return collect($groups->get($parentKey, collect()))->map(function (PageMenu $menu) use (&$walk, $ancestors) {
                if (in_array($menu->id, $ancestors, true)) {
                    return null;
                }

                return [
                    'id' => $menu->id,
                    'uuid' => $menu->uuid,
                    'name' => $menu->name,
                    'description' => $menu->description,
                    'link' => $menu->link,
                    'slug' => $menu->slug,
                    'status' => (bool) $menu->status,
                    'destination_type' => $this->destinationType($menu),
                    'destination' => $this->destinationLabel($menu),
                    'children' => $walk((string) $menu->id, [...$ancestors, $menu->id]),
                ];
            })->filter()->values()->all();
        };

        return $walk('root');
    }

    private function destinationType(PageMenu $menu): string
    {
        return match ($menu->link) {
            'frontend.page' => 'page',
            'frontend.category' => 'category',
            'frontend.project' => 'project',
            'custom' => 'custom',
            null, '' => 'label',
            default => 'route',
        };
    }

    private function destinationLabel(PageMenu $menu): string
    {
        return match ($this->destinationType($menu)) {
            'custom' => (string) $menu->slug,
            'label' => 'Parent label — no link',
            'page' => 'Page · ' . $menu->slug,
            'category' => 'Category · ' . $menu->slug,
            'project' => 'Project · ' . $menu->slug,
            default => StaticUtil::pageRouteName((string) $menu->link) ?: Str::headline(str_replace(['frontend.', '.'], ['', ' '], (string) $menu->link)),
        };
    }

    private function plainDescription(mixed $value): ?string
    {
        $description = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)) ?? '');

        return $description === '' ? null : mb_substr($description, 0, 255);
    }
}
