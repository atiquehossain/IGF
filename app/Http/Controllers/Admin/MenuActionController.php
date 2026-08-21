<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Helper\MyMenu;
use App\Models\AuthMenu;
use App\Models\MenuAction;
use App\Services\AdminAuditService;
use App\Services\AdminAuthorityService;
use App\Support\AdminUi;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Route;

class MenuActionController extends Controller {

    public function __construct(private readonly AdminAuthorityService $authority)
    {
    }

    public function index(Request $request) {
        if (!$request->route('id')) {
            return redirect()->route('menu.index');
        }
        $title = $request->Lang->MenuActionTitle;
        $search = $request->search;
        $authMenu = AuthMenu::findOrFail($request->route('id'));
        $menuActions = MenuAction::select('menu_actions.*', 'auth_menus.name as parent_name')
                ->leftjoin('auth_menus', 'auth_menus.id', '=', 'menu_actions.auth_menu_id')
                ->where('menu_actions.name', 'like', '%' . $search . '%')
                ->where('menu_actions.auth_menu_id', $authMenu->id)
                ->orderBy('menu_actions.order_by', 'ASC')
                ->paginate(15);
        return view('admin.menu-action.index')->with(compact('title', 'authMenu', 'menuActions', 'search'));
    }

    public function create() {
        return redirect()->route('menu.index')->with('message', 'Choose a permission menu, then create its action from the action list.');
    }

    public function store(Request $request) {
        $this->assertOwner();
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(MyMenu::menuActons()))],
            'name' => ['required', 'string', 'max:120', 'not_regex:/[<>\x00-\x1F\x7F]/u'],
            'link' => ['required', 'string', 'max:150', 'regex:/\A[A-Za-z0-9_.-]+\z/', Rule::unique('menu_actions', 'link'), $this->existingRouteRule()],
            'auth_menu_id' => ['required', 'integer', Rule::exists('auth_menus', 'id')],
            'icon' => AdminUi::iconValidationRules(),
            'order_by' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        try {
            DB::transaction(function () use ($validated): void {
                $menuAction = MenuAction::create([
                        'name' => trim($validated['name']),
                        'type' => $validated['type'],
                        'link' => $validated['link'],
                        'auth_menu_id' => $validated['auth_menu_id'],
                        'icon' => AdminUi::iconClass($validated['icon'] ?? null, ''),
                        'order_by' => $validated['order_by'] ?? null,
                        'status' => 0
                ]);
                app(AdminAuditService::class)->record(auth('admin')->user(), 'permission_action.created', $menuAction, [
                    'link' => $menuAction->link,
                    'auth_menu_id' => $menuAction->auth_menu_id,
                    'type' => $menuAction->type,
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
        $action = MenuAction::query()->findOrFail($id);

        return redirect()->route('menu.action.index', $action->auth_menu_id)
            ->with('message', 'Action details are managed from the menu action list.');
    }

    public function edit($id = null, Request $request) {
        $menuAction = MenuAction::query()->findOrFail($id);

        return response(['data' => $menuAction], 200);
    }

    public function update(Request $request) {
        $this->assertOwner();
        $validated = $request->validate([
            'id' => ['required', 'integer', Rule::exists('menu_actions', 'id')],
            'type' => ['required', Rule::in(array_keys(MyMenu::menuActons()))],
            'name' => ['required', 'string', 'max:120', 'not_regex:/[<>\x00-\x1F\x7F]/u'],
            'link' => ['required', 'string', 'max:150', 'regex:/\A[A-Za-z0-9_.-]+\z/', Rule::unique('menu_actions', 'link')->ignore($request->input('id')), $this->existingRouteRule()],
            'auth_menu_id' => ['required', 'integer', Rule::exists('auth_menus', 'id')],
            'icon' => AdminUi::iconValidationRules(),
            'order_by' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        try {
            DB::transaction(function () use ($validated): void {
                $menuAction = MenuAction::query()->lockForUpdate()->findOrFail($validated['id']);
                $before = $menuAction->only(['auth_menu_id', 'name', 'type', 'link', 'icon', 'order_by']);
                $menuAction->update([
                    'name' => trim($validated['name']),
                    'type' => $validated['type'],
                    'link' => $validated['link'],
                    'auth_menu_id' => $validated['auth_menu_id'],
                    'icon' => AdminUi::iconClass($validated['icon'] ?? null, ''),
                    'order_by' => $validated['order_by'] ?? null,
                ]);
                app(AdminAuditService::class)->record(auth('admin')->user(), 'permission_action.updated', $menuAction, [
                    'before' => $before,
                    'after' => $menuAction->only(['auth_menu_id', 'name', 'type', 'link', 'icon', 'order_by']),
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
        $data = DB::transaction(function () use ($request): MenuAction {
            $action = MenuAction::query()->lockForUpdate()->findOrFail($request->route('id'));
            $before = (bool) $action->status;
            $action->update(['status' => !$before]);
            app(AdminAuditService::class)->record(auth('admin')->user(), 'permission_action.status_changed', $action, [
                'status' => ['before' => $before, 'after' => (bool) $action->status],
            ]);

            return $action;
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
            $menuAction = MenuAction::query()->lockForUpdate()->findOrFail($id);
            $menuAction->delete();
            app(AdminAuditService::class)->record(auth('admin')->user(), 'permission_action.deleted', $menuAction, [
                'link' => $menuAction->link,
                'auth_menu_id' => $menuAction->auth_menu_id,
                'type' => $menuAction->type,
            ]);
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
