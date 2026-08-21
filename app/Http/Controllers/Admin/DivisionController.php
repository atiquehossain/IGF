<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

use App\Models\Division;
use App\Models\District;
use App\Models\User;
use Illuminate\Validation\Rule;
use Throwable;

class DivisionController extends Controller {

    public function index(Request $request) {
        $title = $request->Lang->DivisionTitle;
        $search = $request->search;
        $divisions = Division::where('name', 'like', '%' . $search . '%')->paginate(15);
        return view('admin.division.index')->with(compact('title', 'divisions', 'search'));
    }

    public function create() {
        return redirect()->route('division.index');
    }

    public function store(Request $request) {
        $this->validate(request(), [
            'name' => 'required|string|max:255|unique:divisions',
        ]);

        try {
            $division = Division::create([ 'name' => $request->name, 'status' => 0]);
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
        return redirect()->route('division.index');
    }

    public function edit($id = null, Request $request) {
        try {
            $division = Division::select('id', 'name')->where('id', $id)->first();
            if ($division === null) {
                return response(['message' => $request->Lang->Common->Form->DataNotFound], 404);
            }
            $response = [ 'data' => $division];
            return response($response, 200);
        } catch (Throwable $e) {
            return response([ 'message' => $request->Lang->Common->Form->DataNotFound], 404);
        }
    }

    public function update(Request $request) {
        $this->validate(request(), [
            'name' => ['required', 'string', 'max:255', Rule::unique('divisions', 'name')->ignore($request->id)],
            'id' => ['required', 'integer', 'exists:divisions,id'],
        ]);
        try {
            $division = Division::find($request->id);
            if (empty($division)) {
                $notification = array(
                    'message' => $request->Lang->Common->Form->NotFound,
                    'alert-type' => 'warning'
                );
                return back()->with($notification);
            }
            $division->update(['name' => $request->name]);


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
            $data = Division::find($id);
            if ($data === null) {
                return response(['message' => $request->Lang->Common->Form->DataNotFound], 404);
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
            $division = Division::find($id);
            if ($division === null) {
                return response(['message' => $request->Lang->Common->Form->DataNotFound], 404);
            }

            $dependencies = [
                'districts' => District::where('division_id', $division->id)->count(),
                'users' => User::where('division_id', $division->id)->count(),
            ];
            if (array_sum($dependencies) > 0) {
                return response([
                    'message' => 'This division cannot be deleted while districts or member profiles still reference it.',
                    'dependencies' => $dependencies,
                ], 409);
            }

            $division->delete();
            return response(['message' => $request->Lang->Common->Form->DeleteSuccessfully], 200);
        } catch (Throwable $e) {
            return response(['message' => $request->Lang->Common->Form->NotDelete], 403);
        }
    }

}
