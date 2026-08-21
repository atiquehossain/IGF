<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use App\Models\District;
use App\Models\Division;
use App\Models\Upazila;
use App\Models\User;
use Throwable;

class DistrictController extends Controller {

    public function index(Request $request) {
        $title = $request->Lang->DistrictTitle;
        $search = $request->search;

        $districts = District::with('division')->where('name', 'like', '%' . $search . '%')->paginate(15);

        $divisions = Division::where('status', 1)->orderBy('name', 'DESC')->get();

        return view('admin.district.index')->with(compact('title', 'districts', 'divisions', 'search'));
    }

    public function create() {
        return redirect()->route('district.index');
    }

    public function store(Request $request) {
        $this->validate(request(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('districts', 'name')->where('division_id', $request->division_id),
            ],
            'division_id' => [
                'required',
                'integer',
                Rule::exists('divisions', 'id')->where(fn ($query) => $query->where('status', 1)),
            ],
        ]);
        try {
            $district = District::create([ 'name' => $request->name, 'division_id' => $request->division_id, 'status' => 0]);
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


    public function edit($id = null, Request $request) {
        try {
            $district = District::select('id', 'name','division_id')->where('id', $id)->first();
            if ($district === null) {
                return response([ 'message' => $request->Lang->Common->Form->DataNotFound], 404);
            }
            $response = [ 'data' => $district];
            return response($response, 200);
        } catch (Throwable $e) {
            return response([ 'message' => $request->Lang->Common->Form->DataNotFound], 404);
        }
    }

    public function update(Request $request) {
         $this->validate(request(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('districts', 'name')
                    ->where('division_id', $request->division_id)
                    ->ignore($request->id),
            ],
            'id' => 'required|integer|exists:districts,id',
            'division_id' => [
                'required',
                'integer',
                Rule::exists('divisions', 'id')->where(fn ($query) => $query->where('status', 1)),
            ],
        ]);
        try {
            $district = District::find($request->id);
            if (empty($district)) {
                $notification = array(
                    'message' => $request->Lang->Common->Form->NotFound,
                    'alert-type' => 'warning'
                );
                return back()->with($notification);
            }

            if ((int) $district->division_id !== (int) $request->division_id
                && User::where('district_id', $district->id)->exists()) {
                return back()->with([
                    'message' => 'This district cannot be moved to another division while member profiles reference it.',
                    'alert-type' => 'warning',
                ]);
            }
            $district->update(['name' => $request->name, 'division_id' => $request->division_id]);


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
            $data = District::find($id);
            if ($data === null) {
                return response(['message' => $request->Lang->Common->Form->DataNotFound], 404);
            }
            if (!(bool) $data->status
                && !Division::whereKey($data->division_id)->where('status', 1)->exists()) {
                return response(['message' => 'Publish the parent division before publishing this district.'], 409);
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
            $district = District::find($id);
            if ($district === null) {
                return response(['message' => $request->Lang->Common->Form->DataNotFound], 404);
            }

            $dependencies = [
                'upazilas' => Upazila::where('district_id', $district->id)->count(),
                'users' => User::where('district_id', $district->id)->count(),
            ];
            if (array_sum($dependencies) > 0) {
                return response([
                    'message' => 'This district cannot be deleted while upazilas or member profiles still reference it.',
                    'dependencies' => $dependencies,
                ], 409);
            }

            $district->delete();
            return response(['message' => $request->Lang->Common->Form->DeleteSuccessfully], 200);
        } catch (Throwable $e) {
            return response(['message' => $request->Lang->Common->Form->NotDelete], 403);
        }
    }

}
