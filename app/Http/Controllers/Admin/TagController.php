<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Helper\Seq;
use App\Helper\Str;
use App\Models\Banner;
use App\Models\Tag;
use Illuminate\Validation\Rule;
use Throwable;

class TagController extends Controller
{

    public function index(Request $request)
    {
        $title = 'Project groups';
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
        $name = trim(strip_tags((string) $request->input('name')));
        $request->merge(['name' => $name, 'slug' => Str::slug($name)]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('tags', 'name')],
            'slug' => ['required', 'string', 'max:255', Rule::unique('tags', 'slug')],
            'description' => ['nullable', 'string', 'max:1000'],
            'banner_id' => ['nullable', 'integer', Rule::exists('banners', 'id')],
        ]);

        try {
            $uuid = Seq::uuidV4();

            Tag::create([
                'uuid' => $uuid,
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'banner_id' => $validated['banner_id'] ?? null,
                'description' => $this->plainDescription($validated['description'] ?? null),
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
            $tag = Tag::select('id', 'name', 'description', 'banner_id')->where('id', $id)->first();
            $response = ['data' => $tag];
            return response($response, 200);
        } catch (Throwable $e) {
            return response(['message' => $request->Lang->Common->Form->DataNotFound], 403);
        }
    }

    public function update(Request $request)
    {
        $request->merge(['name' => trim(strip_tags((string) $request->input('name')))]);
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tags', 'name')->ignore($request->input('id')),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'banner_id' => ['nullable', 'integer', Rule::exists('banners', 'id')],
            'id' => ['required', 'integer', Rule::exists('tags', 'id')],
        ]);
        try {
            $tag = Tag::find($validated['id']);
            if (empty($tag)) {
                $notification = array(
                    'message' => $request->Lang->Common->Form->NotFound,
                    'alert-type' => 'warning'
                );
                return back()->with($notification);
            }
            $tag->update([
                'name' => $validated['name'],
                'banner_id' => $validated['banner_id'] ?? null,
                'description' => $this->plainDescription($validated['description'] ?? null),
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

    private function plainDescription(?string $value): ?string
    {
        $description = trim(strip_tags((string) $value));

        return $description === '' ? null : $description;
    }
}
