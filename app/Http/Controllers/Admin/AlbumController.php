<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use App\Models\Album;

use App\Rules\ValidateUniqueRule;

use App\Helper\Seq;
use App\Helper\Translation;

use Exception;



class AlbumController extends Controller
{

    public function index(Request $request)
    {
        $title = $request->Lang->Album;
        $search = $request->search;
        $albums = Album::where('name', 'like', '%' . $search . '%')
            ->orderBy('id', 'ASC')
            ->where('language', app()->getLocale())
            ->paginate(15);
        return view('admin.album.index')->with(compact('title', 'albums', 'search'));
    }

    public function create(Request $request)
    {
        $title = $request->Lang->Common->New . " " . $request->Lang->Album;
        $translations = Translation::languageList();
        return view('admin.album.add')->with(compact('title', 'translations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'language' => ['required', 'array', 'min:1'],
            'language.*' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9_-]+$/', 'distinct'],
            'name' => ['required', 'array'],
            'name.*' => ['required', 'string', 'max:255', new ValidateUniqueRule('albums')],
        ]);

        foreach ($validated['language'] as $language) {
            if (!array_key_exists($language, $validated['name'])) {
                throw ValidationException::withMessages([
                    'name.'.$language => 'An album name is required for every language.',
                ]);
            }
        }

        try {
            DB::beginTransaction();

            $uuid = Seq::uuidV4();

            $status = 1;
            if (@$request->save_and_update || @$request->save) {
                $status = 0;
            }

            $createdAlbums = [];
            foreach ($validated['language'] as $language) {
                $album = Album::create([
                    'uuid' => $uuid,
                    'name' => $validated['name'][$language],
                    'type' => 'albums',
                    'status' => $status,
                    'language' => $language,
                ]);
                $createdAlbums[] = [
                    'id' => $album->id,
                    'uuid' => $album->uuid,
                    'name' => $album->name,
                    'language' => $album->language,
                ];
            }
            DB::commit();
            $notification = array(
                'message' => $request->Lang->Common->Form->AddedSuccessfully,
                'alert-type' => 'success',
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $notification['message'],
                    'albums' => $createdAlbums,
                ], 201);
            }

            if (@$request->save_and_update) {
                return redirect(route('album.edit', $uuid))->with($notification);
            } else if (@$request->save) {
                return redirect(route('album.index', $uuid))->with($notification);
            } else {
                return back()->with($notification);
            }

        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $notification = array(
                'message' => $request->Lang->Common->Form->NotCreate,
                'alert-type' => 'error',
            );
            if ($request->expectsJson()) {
                return response()->json(['message' => $notification['message']], 500);
            }
            return back()->with($notification);
        }
    }

    public function show($id = null, Request $request)
    {
        return redirect()->route('album.index')->with('message', 'Album details are managed from the album list.');
    }

    public function edit($uuid = null, Request $request)
    {
        try {
            $title = $request->Lang->Common->Edit . " " . $request->Lang->Album;
            $translations = Translation::languageList();
            $albums = Album::where('uuid', $uuid)->get();
            return view('admin.album.edit')->with(compact('title', 'translations', 'uuid', 'albums'));
        } catch (Exception $e) {
            $notification = array(
                'message' => $request->Lang->Common->Form->DataNotFound,
                'alert-type' => 'error',
            );
            return back()->with($notification);
        }
    }

    public function update(Request $request)
    {
        $this->validate(request(), [
            'name.*' =>  ['required', new ValidateUniqueRule('albums|uuid,' . $request->uuid),'nullable']
        ]);

        try {

            $uuid = $request->uuid;
            $find_album = Album::where('uuid', $uuid)->where('language', 'en')->first();
            if (empty($find_album)) {
                $notification = array(
                    'message' => $request->Lang->Common->Form->NotFound,
                    'alert-type' => 'warning',
                );
                return back()->with($notification);
            }
            
            $status = 1;
            if (@$request->save_and_update || @$request->save) {
                $status = 0;
            }

            DB::beginTransaction();
            foreach ($request->language as $language) {
                $album = Album::find(@$request->id[$language]);
                if ($album) {
                    $album->update([
                        'uuid' => $uuid,
                        'name' => @$request->name[$language],
                        'language' => $language,
                    ]);

                } else {
                    Album::create([
                        'uuid' => $uuid,
                        'name' => @$request->name[$language],
                        'type' => 'albums',
                        'language' => $language,
                        'status' => $status,
                    ]);
                }
            }

            DB::commit();

            $notification = array(
                'message' => $request->Lang->Common->Form->UpdatedSuccessfully,
                'alert-type' => 'success',
            );

            if (@$request->save_and_update) {
                return redirect(route('album.edit', $uuid))->with($notification);
            } else if (@$request->save) {
                return redirect(route('album.index', $uuid))->with($notification);
            } else {
                return back()->with($notification);
            }
        } catch (Exception $e) {
            DB::rollback();
            $notification = array(
                'message' => $request->Lang->Common->Form->NotUpdate,
                'alert-type' => 'error',
            );
            return back()->with($notification);
        }
    }

    public function status(Request $request, $id = null)
    {
        try {
            if ($request->ajax()) {
                $data = Album::where('uuid', $id)->where('language', 'en')->first();
                $data->status = $data->status ^ 1;
                Album::where('uuid', $id)->update(['status' => $data->status]);
                return response(['message' => ($data->status ? $request->Lang->Common->Form->PublishSuccessfully : $request->Lang->Common->Form->UnpublishSuccessfully)], 200);
            }
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotUpdate], 403);
        }
    }

    public function destroy($id = null, Request $request)
    {
        try {
            // $albums = Album::find($id);
            // $albums->delete();
            Album::where('uuid', $id)->delete();
            return response(['message' => $request->Lang->Common->Form->DeleteSuccessfully], 200);
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotDelete], 403);
        }
    }

}
