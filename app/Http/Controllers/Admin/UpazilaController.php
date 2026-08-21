<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

use App\Models\Upazila;
use App\Models\District;
use App\Models\User;
use Throwable;

class UpazilaController extends Controller {

    public function index(Request $request) {
        $title = $request->Lang->UpazilaTitle;
        $search = $request->search;

        $upazilas = Upazila::with('district')->where('name', 'like', '%' . $search . '%')->paginate(15);

        $districts = District::where('status', 1)->orderBy('name', 'DESC')->get();

        return view('admin.upazila.index')->with(compact('title', 'upazilas', 'districts', 'search'));
    }

    public function create() {
        return redirect()->route('upazila.index');
    }

    public function store(Request $request) {
        $this->validate(request(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('upazilas', 'name')->where('district_id', $request->district_id),
            ],
            'district_id' => [
                'required',
                'integer',
                Rule::exists('districts', 'id')->where(fn ($query) => $query->where('status', 1)),
            ],
        ]);
        $this->ensureDistrictIsPublishable((int) $request->district_id);

        try {
            $upazila = Upazila::create([ 'name' => $request->name, 'district_id' => $request->district_id, 'status' => 0]);
            $notification = array(
                'message' => $request->Lang->Common->Form->AddedSuccessfully,
                'alert-type' => 'success'
            );
            return back()->with($notification);
        } catch (Throwable $e) {
            $notification = array(
                'message' => $request->Lang->Common->Form->NotCreate,
                'alert-type' => 'error'
            );
            return back()->with($notification);
        }
    }

    public function show($id = null, Request $request) {
        return redirect()->route('upazila.index');
    }

    public function edit($id = null, Request $request) {
        try {
            $upazila = Upazila::select('id', 'name', 'district_id')->where('id', $id)->first();
            if ($upazila === null) {
                return response([ 'message' => $request->Lang->Common->Form->DataNotFound], 404);
            }
            $response = [ 'data' => $upazila];
            return response($response, 200);
        } catch (Throwable $e) {
            return response([ 'message' => $request->Lang->Common->Form->DataNotFound], 404);
        }
    }

    public function update(Request $request) {

        $this->validate(request(), [
            'id' => 'required|integer|exists:upazilas,id',
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('upazilas', 'name')
                    ->where('district_id', $request->district_id)
                    ->ignore($request->id),
            ],
            'district_id' => [
                'required',
                'integer',
                Rule::exists('districts', 'id')->where(fn ($query) => $query->where('status', 1)),
            ],
        ]);
        $this->ensureDistrictIsPublishable((int) $request->district_id);
        try {
            $upazila = Upazila::find($request->id);
            if (empty($upazila)) {
                $notification = array(
                    'message' => $request->Lang->Common->Form->NotFound,
                    'alert-type' => 'warning'
                );
                return back()->with($notification);
            }

            if ((int) $upazila->district_id !== (int) $request->district_id
                && User::where('upazila_id', $upazila->id)->exists()) {
                return back()->with([
                    'message' => 'This upazila cannot be moved to another district while member profiles reference it.',
                    'alert-type' => 'warning',
                ]);
            }
            $upazila->update(['name' => $request->name, 'district_id' => $request->district_id]);


            $notification = array(
                'message' => $request->Lang->Common->Form->UpdatedSuccessfully,
                'alert-type' => 'success'
            );
            return back()->with($notification);
        } catch (Throwable $e) {
            $notification = array(
                'message' => $request->Lang->Common->Form->NotUpdate,
                'alert-type' => 'error'
            );
            return back()->with($notification);
        }
    }

    public function status(Request $request, int $id) {
        try {
            $data = Upazila::find($id);
            if ($data === null) {
                return response(['message' => $request->Lang->Common->Form->DataNotFound], 404);
            }
            if (!(bool) $data->status
                && !District::query()
                    ->whereKey($data->district_id)
                    ->where('districts.status', 1)
                    ->whereHas('division', fn ($query) => $query->where('status', 1))
                    ->exists()) {
                return response(['message' => 'Publish the parent district and division before publishing this upazila.'], 409);
            }

            $data->status = !((bool) $data->status);
            $data->save();

            return response([
                'status' => (bool) $data->status,
                'message' => ($data->status ? $request->Lang->Common->Form->PublishSuccessfully : $request->Lang->Common->Form->UnpublishSuccessfully),
            ], 200);
        } catch (Throwable $e) {
            return response(['message' => $request->Lang->Common->Form->NotUpdate], 403);
        }
    }

    public function destroy($id = null, Request $request) {
        try {
            $upazila = Upazila::find($id);
            if ($upazila === null) {
                return response(['message' => $request->Lang->Common->Form->DataNotFound], 404);
            }

            $dependencies = [
                'users' => User::where('upazila_id', $upazila->id)->count(),
            ];
            if (array_sum($dependencies) > 0) {
                return response([
                    'message' => 'This upazila cannot be deleted while member profiles still reference it.',
                    'dependencies' => $dependencies,
                ], 409);
            }

            $upazila->delete();
            return response(['message' => $request->Lang->Common->Form->DeleteSuccessfully], 200);
        } catch (Throwable $e) {
            return response(['message' => $request->Lang->Common->Form->NotDelete], 403);
        }
    }

    private function ensureDistrictIsPublishable(int $districtId): void
    {
        $valid = District::query()
            ->whereKey($districtId)
            ->where('districts.status', 1)
            ->whereHas('division', fn ($query) => $query->where('status', 1))
            ->exists();

        if (!$valid) {
            throw ValidationException::withMessages([
                'district_id' => 'Select a published district whose parent division is also published.',
            ]);
        }
    }

}
