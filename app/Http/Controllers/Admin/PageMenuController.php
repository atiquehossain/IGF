<?php

namespace App\Http\Controllers\Admin;

use App\Helper\MyMenu;
use App\Http\Controllers\Controller;
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
            'name.*' =>  ['required', new ValidateUniqueRule('page_menus'), 'nullable'],
            'description.*' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            DB::beginTransaction();
            $uuid = Seq::uuidV4();
            foreach ($request->language as $language) {
                PageMenu::create([
                    'uuid' => $uuid,
                    'parent_id' => $request->parent[$language],
                    'name' => $request->name[$language],
                    'description' => $this->plainDescription($request->description[$language] ?? null),
                    'type' => $request->type[$language],
                    'link' => $request->link[$language],
                    'slug' => $request->slug[$language],
                    'icon' => $request->icon[$language],
                    'banner_id' => $request->banner_id[$language],
                    'language' => $language,
                    'order_by' => $request->order_by[$language],
                    'status' => 0
                ]);
            }

            DB::commit();

            $notification = array(
                'message' => $request->Lang->Common->Form->AddedSuccessfully,
                'alert-type' => 'success',
            );
            if (@$request->save_and_update) {
                return redirect(route('page.menu.edit', $uuid))->with($notification);
            } else {
                return redirect(route('page.menu.index'))->with($notification);
            }
        } catch (Exception $e) {
            DB::rollback();
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
            $json = MyMenu::frontMenus($lang, $type);
            MyMenu::reset();
            $menu = json_decode($json, true);
            $flat = MyMenu::flattenMenu($menu);
            // $data = PageMenu::where('status', 1)->where('type', $type)->where('language', $lang)->whereNull('parent_id')->get();
            $response = ['data' => $flat];
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
            'name.*' =>  ['required', new ValidateUniqueRule('page_menus|uuid,' . $request->uuid), 'nullable'],
            'description.*' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $uuid = $request->uuid;
            DB::transaction(function () use ($request, $uuid): void {
                $logicalMenus = PageMenu::query()
                    ->where('uuid', $uuid)
                    ->orderBy('language')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
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
                    $values = [
                        'parent_id' => @$request->parent[$language],
                        'name' => @$request->name[$language],
                        'description' => $this->plainDescription($request->description[$language] ?? null),
                        'type' => @$request->type[$language],
                        'link' => @$request->link[$language],
                        'icon' => @$request->icon[$language],
                        'banner_id' => @$request->banner_id[$language],
                        'slug' => @$request->slug[$language],
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
                $data = PageMenu::where('uuid', $id)->where('language', 'en')->first();
                $data->status = $data->status ^ 1;
                PageMenu::where('uuid', $id)->update(['status' => $data->status]);
                return response(['message' => ($data->status ? $request->Lang->Common->Form->PublishSuccessfully : $request->Lang->Common->Form->UnpublishSuccessfully)], 200);
            }
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotUpdate], 403);
        }
    }

    public function destroy($id = null, Request $request)
    {
        try {
            $menus = PageMenu::where('uuid', $id)->get();
            if ($menus->isEmpty()) {
                abort(404);
            }
            if (PageMenu::whereIn('parent_id', $menus->pluck('id'))->exists()) {
                return response(['message' => 'Move submenu items out before removing their parent.'], 422);
            }
            PageMenu::where('uuid', $id)->update(['deleted_by' => auth('admin')->id()]);
            PageMenu::where('uuid', $id)->delete();
            return response(['message' => $request->Lang->Common->Form->DeleteSuccessfully], 200);
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
        $menus = PageMenu::onlyTrashed()->where('uuid', $uuid)->get();
        abort_if($menus->isEmpty(), 404);
        PageMenu::onlyTrashed()->where('uuid', $uuid)->restore();
        PageMenu::where('uuid', $uuid)->update(['deleted_by' => null]);

        return back()->with(['message' => 'Navigation item restored in every language.', 'alert-type' => 'success']);
    }

    public function forceDestroy(string $uuid, Request $request)
    {
        $menus = PageMenu::onlyTrashed()->where('uuid', $uuid)->get();
        abort_if($menus->isEmpty(), 404);
        $ids = $menus->pluck('id');
        abort_if(
            PageMenu::withTrashed()->whereIn('parent_id', $ids)->exists(),
            422,
            'Restore or remove child navigation items before permanently deleting this parent.'
        );
        PageMenu::onlyTrashed()->where('uuid', $uuid)->forceDelete();

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
            if ($parentUuid && !$menus->has($parentUuid)) {
                throw ValidationException::withMessages(['items' => 'A parent navigation item is missing from the submitted tree.']);
            }
            if ($parentUuid && $parentByUuid->get($parentUuid)) {
                throw ValidationException::withMessages(['items' => 'Only one submenu level is supported in the public navigation.']);
            }
            $seen = [$uuid => true];
            while ($parentUuid) {
                if (isset($seen[$parentUuid])) {
                    throw ValidationException::withMessages(['items' => 'Navigation items cannot contain a circular parent relationship.']);
                }
                $seen[$parentUuid] = true;
                $parentUuid = $parentByUuid->get($parentUuid);
            }
        }

        DB::transaction(function () use ($data, $menus) {
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
        $menu = PageMenu::where('uuid', $uuid)->where('language', $data['locale'])->firstOrFail();
        $updates = [
            'name' => trim($data['label']),
            'description' => $this->plainDescription($data['description'] ?? null),
            'status' => $data['enabled'],
        ];

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
            if ($parent->parent_id !== null) {
                throw ValidationException::withMessages(['parent_uuid' => 'A submenu cannot contain another submenu. Choose a top-level parent.']);
            }
        }
        $order = ((int) PageMenu::query()
            ->where('language', $data['locale'])
            ->where('type', $data['location'])
            ->where('parent_id', $parent?->id)
            ->max('order_by')) + 1;

        $menu = PageMenu::create([
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
            'status' => $data['enabled'],
        ]);

        return $request->expectsJson()
            ? response()->json(['message' => 'Menu item added.', 'item' => $menu], 201)
            : redirect()->route('page.menu.index', ['location' => $data['location'], 'locale' => $data['locale']])
                ->with(['message' => 'Menu item added.', 'alert-type' => 'success']);
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
