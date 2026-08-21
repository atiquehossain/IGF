<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\User;
use App\Services\AdminPrivateSearch;

use Exception;

class UserApprovalController extends Controller
{
    public function __construct(private AdminPrivateSearch $privateSearch)
    {
    }

    public function index(Request $request)
    {
        if ($request->query->has('search')) {
            return redirect()->route('user-approval.index');
        }

        $title = 'Member Applications';
        $search = $this->privateSearch->current($request, 'member-approvals');
        $users = User::where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('phone_no', 'like', '%' . $search . '%')
                    ->orWhere('org', 'like', '%' . $search . '%');
            })
            ->orderBy('created_at','DESC')
            ->paginate(15);
        return view('admin.user-approval.index')->with(compact('title', 'users', 'search'));
    }

    public function show($id = null, Request $request)
    {
        $user = User::findOrFail($id);
        $title = "View {$user->name} Details";

        return view('admin.user-approval.view')->with(compact('title', 'user'));
    }

    public function approve(Request $request)
    {
        try {
            $id = $request->id;
            $user = User::where('id', $id)->first();

            if (empty($user)) {
                $notification = array(
                    'message' => 'User Not Found',
                    'alert-type' => 'warning',
                );
                return back()->with($notification);
            }
            DB::beginTransaction();

            $user->update([
              'is_approved' => 1
            ]);

            DB::commit();
            $notification = array(
                'message' => 'Member application approved successfully.',
                'alert-type' => 'success',
            );
            return back()->with($notification);
        } catch (Exception $e) {
            DB::rollback();
            $notification = array(
                'message' => $request->Lang->Common->Form->NotUpdate,
                'alert-type' => 'error',
            );
            return back()->with($notification);
        }
    }

    public function reject(Request $request)
    {
        try {
            $id = $request->id;
            $user = User::where('id', $id)->first();

            if (empty($user)) {
                $notification = array(
                    'message' => 'User Not Found',
                    'alert-type' => 'warning',
                );
                return back()->with($notification);
            }
            DB::beginTransaction();

            $user->update([
              'is_approved' => 2
            ]);

            DB::commit();
            $notification = array(
                'message' => 'Member application rejected.',
                'alert-type' => 'success',
            );
            return back()->with($notification);
        } catch (Exception $e) {
            DB::rollback();
            $notification = array(
                'message' => $request->Lang->Common->Form->NotUpdate,
                'alert-type' => 'error',
            );
            return back()->with($notification);
        }
    }
}
