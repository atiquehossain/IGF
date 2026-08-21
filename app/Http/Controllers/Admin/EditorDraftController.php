<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\EditorDraft;

use Exception;

class EditorDraftController extends Controller {

    public function index(Request $request) {
        $title = $request->Lang->EditorDraftTitle;
        $search = $request->search;
        $editorDrafts = EditorDraft::where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%');
                })
                ->paginate(15);
        return view('admin.editor-draft.index')->with(compact('title', 'editorDrafts', 'search'));
    }

    public function create() {
        return redirect()->route('editorDraft.index')->with('message', 'Create editor drafts from the draft list.');
    }

    public function store(Request $request) {
        $this->validate(request(), [
            'name' => 'required|string|max:255|unique:editor_drafts',
        ]);

        try {
            $editorDraft = EditorDraft::create([
                        'name' => $request->name,
                        'description' => @$request->description,
                        'status' => 0
            ]);
            $notification = array(
                'message' => $request->Lang->Common->Form->AddedSuccessfully,
                'alert-type' => 'success'
            );
            return back()->with($notification);
        } catch (Exception $e) {
            $notification = array(
                'message' => $request->Lang->Common->Form->NotCreate,
                'alert-type' => 'error'
            );
            return back()->with($notification);
        }
    }

    public function edit($id = null, Request $request) {
        try {
            $editorDraft = EditorDraft::select('id', 'name', 'description')->where('id', $id)->first();
            $response = [ 'data' => $editorDraft];
            return response($response, 200);
        } catch (Exception $e) {
            return response([ 'message' => $request->Lang->Common->Form->DataNotFound], 403);
        }
    }

    public function update(Request $request) {
        $this->validate(request(), [
            'name' => 'required|string',
            'id' => 'required|string',
        ]);
        try {
            $editorDraft = EditorDraft::find($request->id);
            if (empty($editorDraft)) {
                $notification = array(
                    'message' => $request->Lang->Common->Form->NotFound,
                    'alert-type' => 'warning'
                );
                return back()->with($notification);
            }
            $editorDraft->update([
                'name' => $request->name,
                'description' => @$request->description,
            ]);


            $notification = array(
                'message' => $request->Lang->Common->Form->UpdatedSuccessfully,
                'alert-type' => 'success'
            );
            return back()->with($notification);
        } catch (Exception $e) {
            $notification = array(
                'message' => $request->Lang->Common->Form->NotUpdate,
                'alert-type' => 'error'
            );
            return back()->with($notification);
        }
    }

    public function status(Request $request) {
        try {
            if ($request->ajax()) {
                $data = EditorDraft::find($request->id);
                $data->status = $data->status ^ 1;
                $data->update();
                return response(['message' => ($data->status ? $request->Lang->Common->Form->PublishSuccessfully : $request->Lang->Common->Form->UnpublishSuccessfully)], 200);
            }
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotUpdate], 403);
        }
    }

    public function destroy($id = null, Request $request) {
        try {
            $editorDraft = EditorDraft::find($id);
            $editorDraft->delete();
            return response(['message' => $request->Lang->Common->Form->DeleteSuccessfully], 200);
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotDelete], 403);
        }
    }

    public function getEditor(Request $request) {
        try {
            $editorDraft = EditorDraft::select('name as text')
                    ->selectRaw("REPLACE(`description`,'\r\n','') as value")
                    ->where('status', 1)
                    ->get();
            $response = [ 'data' => $editorDraft];
            return response($response, 200);
        } catch (Exception $e) {
            return response([ 'message' => $request->Lang->Common->Form->DataNotFound], 403);
        }
    }

}
