<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Services\AdminAuditService;
use App\Services\AdminAuthorityService;
use App\Support\AdminUi;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Route;

class AuthMenuController extends Controller {

    public function __construct(private readonly AdminAuthorityService $authority)
    {
    }

    public function index(Request $request) {
        $title = $request->Lang->MenuTitle;
        $search = $request->search;
        $authMenus = AuthMenu::with('parent')
                ->where('name', 'like', '%' . $search . '%')
                ->orderBy('order_by', 'ASC')
                ->paginate(15);
        $menuList = AuthMenu::where('status', 1)->whereNull('parent_id')->get();
        return view('admin.menu.index')->with(compact('title', 'authMenus', 'menuList', 'search'));
    }

    public function create() {
        return redirect()->route('menu.index')->with('message', 'Create menus from the menu list.');
    }

    public function store(Request $request) {
        $this->assertOwner();
        $validated = $request->validate([
            'parent' => ['nullable', 'integer', Rule::exists('auth_menus', 'id')->whereNull('parent_id')],
            'link' => ['required', 'string', 'max:150', 'regex:/\A[A-Za-z0-9_.-]+\z/', Rule::unique('auth_menus', 'link'), $this->existingRouteRule()],
            'name' => ['required', 'string', 'max:120', 'not_regex:/[<>\x00-\x1F\x7F]/u'],
            'icon' => AdminUi::iconValidationRules(),
            'order_by' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        try {
            DB::transaction(function () use ($request, $validated): void {
                $authMenu = AuthMenu::create([
                        'parent_id' => $validated['parent'] ?? null,
                        'name' => trim($validated['name']),
                        'link' => $validated['link'],
                        'icon' => AdminUi::iconClass($validated['icon'] ?? null, ''),
                        'order_by' => $validated['order_by'] ?? null,
                        'status' => 0
                ]);
                app(AdminAuditService::class)->record(auth('admin')->user(), 'permission_menu.created', $authMenu, [
                    'link' => $authMenu->link,
                    'parent_id' => $authMenu->parent_id,
                    'status' => false,
                ]);
            });
            $notification = array(
                'message' => $request->Lang->Common->Form->AddedSuccessfully,
                'alert-type' => 'success'
            );
            return back()->with($notification);
        } catch (\Throwable $e) {
            report($e);
            $notification = array(
                'message' => $request->Lang->Common->Form->NotCreate,
                'alert-type' => 'error'
            );
            return back()->with($notification);
        }
    }

    public function show($id = null, Request $request) {
        return redirect()->route('menu.index')->with('message', 'Menu details are managed from the menu list.');
    }

    public function edit($id = null, Request $request) {
        $authMenu = AuthMenu::select('id', 'name', 'parent_id', 'link', 'icon', 'order_by')->findOrFail($id);

        return response(['data' => $authMenu], 200);
    }

    public function update(Request $request) {
        $this->assertOwner();
        $validated = $request->validate([
            'id' => ['required', 'integer', Rule::exists('auth_menus', 'id')],
            'parent' => ['nullable', 'integer', Rule::exists('auth_menus', 'id')->whereNull('parent_id'), Rule::notIn([(int) $request->input('id')])],
            'link' => ['required', 'string', 'max:150', 'regex:/\A[A-Za-z0-9_.-]+\z/', Rule::unique('auth_menus', 'link')->ignore($request->input('id')), $this->existingRouteRule()],
            'name' => ['required', 'string', 'max:120', 'not_regex:/[<>\x00-\x1F\x7F]/u'],
            'icon' => AdminUi::iconValidationRules(),
            'order_by' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        try {
            DB::transaction(function () use ($validated): void {
                $authMenu = AuthMenu::query()->lockForUpdate()->findOrFail($validated['id']);
                $before = $authMenu->only(['parent_id', 'name', 'link', 'icon', 'order_by']);
                $authMenu->update([
                    'parent_id' => $validated['parent'] ?? null,
                    'name' => trim($validated['name']),
                    'link' => $validated['link'],
                    'icon' => AdminUi::iconClass($validated['icon'] ?? null, ''),
                    'order_by' => $validated['order_by'] ?? null,
                ]);
                app(AdminAuditService::class)->record(auth('admin')->user(), 'permission_menu.updated', $authMenu, [
                    'before' => $before,
                    'after' => $authMenu->only(['parent_id', 'name', 'link', 'icon', 'order_by']),
                ]);
            });
            $notification = array(
                'message' => $request->Lang->Common->Form->UpdatedSuccessfully,
                'alert-type' => 'success'
            );
            return back()->with($notification);
        } catch (\Throwable $e) {
            report($e);
            $notification = array(
                'message' => $request->Lang->Common->Form->NotUpdate,
                'alert-type' => 'error'
            );
            return back()->with($notification);
        }
    }

    public function status(Request $request) {
        $this->assertOwner();
        abort_unless($request->ajax(), 404);
        $data = DB::transaction(function () use ($request): AuthMenu {
            $menu = AuthMenu::query()->lockForUpdate()->findOrFail($request->route('id'));
            $before = (bool) $menu->status;
            $menu->update(['status' => !$before]);
            app(AdminAuditService::class)->record(auth('admin')->user(), 'permission_menu.status_changed', $menu, [
                'status' => ['before' => $before, 'after' => (bool) $menu->status],
            ]);

            return $menu;
        });

        return response([
            'message' => $data->status
                ? $request->Lang->Common->Form->PublishSuccessfully
                : $request->Lang->Common->Form->UnpublishSuccessfully,
            'status' => (bool) $data->status,
        ], 200);
    }

    public function destroy($id = null, Request $request) {
        $this->assertOwner();
        DB::transaction(function () use ($id): void {
            $authMenu = AuthMenu::query()->lockForUpdate()->findOrFail($id);
            $dependencies = [
                'child_menus' => AuthMenu::query()->where('parent_id', $authMenu->id)->count(),
                'actions' => MenuAction::query()->where('auth_menu_id', $authMenu->id)->count(),
            ];
            abort_if(array_sum($dependencies) > 0, 409, 'Remove this menu\'s child menus and actions before deleting it.');
            $authMenu->delete();
            app(AdminAuditService::class)->record(auth('admin')->user(), 'permission_menu.deleted', $authMenu, $dependencies);
        });

        return response(['message' => $request->Lang->Common->Form->DeleteSuccessfully], 200);
    }

    private function assertOwner(): void
    {
        $this->authority->assertOwner(auth('admin')->user() ?? abort(401));
    }

    private function existingRouteRule(): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail): void {
            if (!Route::has((string) $value)) {
                $fail('Choose an existing named application route.');
            }
        };
    }

}
