<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Helper\Seq;
use App\Helper\Str;
use App\Models\Banner;
use App\Models\Tag;
use Throwable;

class TagController extends Controller
{

    public function index(Request $request)
    {
        $title = $request->Lang->TagTitle;
        $search = $request->search;
        $tags = Tag::where('name', 'like', '%' . $search . '%')->paginate(15);
        $banners = Banner::whereIN('type', ['banner-home', 'banner-page'])->where('status', 1)->where('language', app()->getLocale())->get();

        return view('admin.tag.index')->with(compact('title', 'tags', 'banners', 'search'));
    }

    public function create()
    {
        return redirect()->route('tag.index');
    }

    public function store(Request $request)
    {
        $request['slug'] = Str::slug(@$request->name);

        $this->validate(request(), [
            'name' => 'required|string|max:255|unique:tags',
        ]);

        try {
            $uuid = Seq::uuidV4();

            Tag::create([
                'uuid' => $uuid,
                'name' => $request->name,
                'slug' => $request->slug,
                'banner_id' => $request->banner_id,
                'description' => $request->description,
                'status' => 0
            ]);

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

    public function show($id = null, Request $request)
    {
        return redirect()->route('tag.index');
    }

    public function edit($id = null, Request $request)
    {
        try {
            $tag = Tag::select('id', 'name', 'banner_id')->where('id', $id)->first();
            $response = ['data' => $tag];
            return response($response, 200);
        } catch (Throwable $e) {
            return response(['message' => $request->Lang->Common->Form->DataNotFound], 403);
        }
    }

    public function update(Request $request)
    {
        $this->validate(request(), [
            'name' => 'required|string',
            'id' => 'required|string',
        ]);
        try {
            $tag = Tag::find($request->id);
            if (empty($tag)) {
                $notification = array(
                    'message' => $request->Lang->Common->Form->NotFound,
                    'alert-type' => 'warning'
                );
                return back()->with($notification);
            }
            $tag->update([
                'name' => $request->name,
                'banner_id' => $request->banner_id,
                'description' => $request->description
            ]);

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

    public function status(Request $request, int $id)
    {
        try {
            $data = Tag::find($id);
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

    public function destroy($id = null, Request $request)
    {
        try {
            $tag = Tag::find($id);
            $tag->delete();
            return response(['message' => $request->Lang->Common->Form->DeleteSuccessfully], 200);
        } catch (Throwable $e) {
            return response(['message' => $request->Lang->Common->Form->NotDelete], 403);
        }
    }
}
