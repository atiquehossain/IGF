<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\User;
use App\Services\AdminPrivateSearch;
use Throwable;


class UserController extends Controller {

    public function __construct(private AdminPrivateSearch $privateSearch)
    {
    }

    public function index(Request $request) {
        if ($request->query->has('search')) {
            return redirect()->route('user.index');
        }

        $title = $request->Lang->MemberTitle;
        $search = $this->privateSearch->current($request, 'users');
        $users = User::where(function ($query) use($search) {
                    $query->where('phone_no', 'like', '%' . $search . '%')
                    ->orWhere('gender', 'like', '%' . $search . '%')
                    ->orWhere('provider_type', 'like', '%' . $search . '%')
                    ->orWhere('address', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
                })
                ->orderBy('id', 'desc')
                ->paginate(15);
        return view('admin.user.index')->with(compact('title', 'users', 'search'));
    }

    public function show(Request $request) {
        $title = $request->Lang->MemberTitle. " " . $request->Lang->Details;
        $user = User::select('users.*', 'divisions.name as division_name', 'districts.name as district_name', 'upazilas.name as upazila_name')
                        ->leftJoin('divisions', 'divisions.id', '=', 'users.division_id')
                        ->leftJoin('districts', 'districts.id', '=', 'users.district_id')
                        ->leftJoin('upazilas', 'upazilas.id', '=', 'users.upazila_id')
                        ->where('users.id', $request->id)->first();
        return view('admin.user.show')->with(compact('title', 'user'));
    }

    public function seachUserApi(Request $request) {
        try {
            $validated = $request->validate(['q' => ['required', 'string', 'max:100']]);
            $search = $this->privateSearch->normalize($validated['q']);
            $users = User::select('id', 'name', 'phone_no', 'email')->where(function ($query) use($search) {
                        $query->where('phone_no', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                    })
                    ->orderBy('id', 'desc')
                    ->limit(15)
                    ->get();
            $response = ['status' => true, 'data' => $users];

            return response($response, 200);
        } catch (Throwable $e) {
            return response(['status' => false, 'message' => $request->Lang->Common->Form->UserNotFound], 422);
        }
    }

}
