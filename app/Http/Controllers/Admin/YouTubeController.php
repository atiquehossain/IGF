<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

use App\Models\YouTube;

use App\Rules\ValidateUniqueRule;

use App\Helper\MyLogs;
use App\Helper\MyYoutube;
use App\Helper\Seq;
use App\Helper\Translation;
use App\Support\RequestFingerprint;

use Exception;

class YouTubeController extends Controller
{

    public function index(Request $request)
    {
        $title = $request->Lang->YoutubeTitle;
        $search = $request->search;
        $youtubes = YouTube::where('name', 'like', '%' . $search . '%')
            ->where('language', app()->getLocale())
            ->orderBy('order_by', 'asc')
            ->paginate(15);
        return view('admin.youtube.index')->with(compact('title', 'youtubes', 'search'));
    }

    public function create(Request $request)
    {
        $title = $request->Lang->Common->New . " " . $request->Lang->YoutubeTitle;
        $translations = Translation::languageList();
        return view('admin.youtube.add')->with(compact('title', 'translations'));
    }

    public function store(Request $request)
    {
        $this->validate(request(), [
            'name.*' =>  ['required', new ValidateUniqueRule('you_tubes'),'nullable'],
            'video_id.*' =>  ['required', 'regex:/\A[A-Za-z0-9_-]{6,20}\z/', new ValidateUniqueRule('you_tubes'),'nullable'],
            'activision_time.*' => ['required', 'numeric', 'min:0.1', 'max:1440'],
            'duration_time.*' => ['required', 'numeric', 'min:0.1', 'max:1440'],
            'order_by.*' => ['nullable', 'integer', 'min:0'],
        ]);
        $this->validateWatchThresholds($request);

        try {
            DB::beginTransaction();
            $uuid = Seq::uuidV4();

            MyLogs::front($request, 'Youtube Meta');
            foreach ($request->language as $language) {
                $singleVideo = MyYoutube::_singleVideo($request->video_id[$language]);
                if (empty($singleVideo->items[0])) {
                    DB::rollBack();
                    $notification = array('message' => 'Video not found!', 'alert-type' => 'error');
                    return back()->with($notification);
                }
                $youtubeData = $singleVideo->items[0]->snippet;
                $published_at = date('Y-m-d H:i:s', strtotime($youtubeData->publishedAt));

                $data = [
                    'uuid' => $uuid,
                    'name' => $request->name[$language],
                    'video_id' => $request->video_id[$language],
                    'activision_time' => $request->activision_time[$language],
                    'duration_time' => $request->duration_time[$language],
                    'ip' => RequestFingerprint::for($request),
                    'order_by' => $request->order_by[$language],
                    'published_at' => $published_at,
                    'title' => $youtubeData->title,
                    'description' => strval($youtubeData->description),
                    'image' => $youtubeData->thumbnails->medium->url,
                    'language' => $language,
                    'status' => 0
                ];
                $youtube = YouTube::create($data);
            }
            DB::commit();

            $notification = array(
                'message' => $request->Lang->Common->Form->AddedSuccessfully,
                'alert-type' => 'success',
            );

            if (@$request->save_and_update) {
                return redirect(route('youtube.edit', $uuid))->with($notification);
            } else {
                return redirect(route('youtube.index'))->with($notification);
            }
        } catch (Exception $e) {
            DB::rollback();
            $notification = array(
                'message' => $request->Lang->Common->Form->NotCreate,
                'alert-type' => 'error',
            );
            return back()->with($notification);
        }
    }

    public function edit($uuid = null, Request $request)
    {
        try {
            $title = $request->Lang->Common->Edit. " " . $request->Lang->YoutubeTitle;
            $translations = Translation::languageList();
            $youtubes = YouTube::Where('uuid', $uuid)->get();
            return view('admin.youtube.edit')->with(compact('title', 'translations', 'uuid', 'youtubes'));
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
            'name.*' =>  ['required', new ValidateUniqueRule('you_tubes|uuid,' . $request->uuid),'nullable'],
            'video_id.*' =>  ['required', 'regex:/\A[A-Za-z0-9_-]{6,20}\z/', new ValidateUniqueRule('you_tubes|uuid,' . $request->uuid),'nullable'],
            'activision_time.*' => ['required', 'numeric', 'min:0.1', 'max:1440'],
            'duration_time.*' => ['required', 'numeric', 'min:0.1', 'max:1440'],
            'order_by.*' => ['nullable', 'integer', 'min:0'],
            'id.*' => 'required|integer'
        ]);
        $this->validateWatchThresholds($request);

        try {
            $uuid = $request->uuid;
            $find_youtube = YouTube::where('uuid', $uuid)->where('language', 'en')->first();
            if (empty($find_youtube)) {
                $notification = array(
                    'message' => $request->Lang->Common->Form->NotFound,
                    'alert-type' => 'warning',
                );
                return back()->with($notification);
            }
            DB::beginTransaction();
            foreach ($request->language as $language) {
                $singleVideo = MyYoutube::_singleVideo($request->video_id[$language]);
                if (empty($singleVideo->items[0])) {
                    DB::rollBack();
                    $notification = array('message' => 'Video not found!', 'alert-type' => 'error');
                    return back()->with($notification);
                }
                $youtubeData = $singleVideo->items[0]->snippet;
                $published_at = date('Y-m-d H:i:s', strtotime($youtubeData->publishedAt));

                MyLogs::front($request, 'Youtube Meta');
                $youtube = YouTube::find(@$request->id[$language]);
                if ($youtube) {
                    $youtube->update([
                        'uuid' => $uuid,
                        'name' => $request->name[$language],
                        'video_id' => $request->video_id[$language],
                        'activision_time' => $request->activision_time[$language],
                        'duration_time' => $request->duration_time[$language],
                        'ip' => RequestFingerprint::for($request),
                        'order_by' => $request->order_by[$language],
                        'published_at' => $published_at,
                        'title' => $youtubeData->title,
                        'description' => strval($youtubeData->description),
                        'image' => $youtubeData->thumbnails->medium->url,
                        'language' => $language,
                    ]);
                } else {
                    YouTube::create([
                        'uuid' => $uuid,
                        'name' => $request->name[$language],
                        'video_id' => $request->video_id[$language],
                        'activision_time' => $request->activision_time[$language],
                        'duration_time' => $request->duration_time[$language],
                        'ip' => RequestFingerprint::for($request),
                        'order_by' => $request->order_by[$language],
                        'published_at' => $published_at,
                        'title' => $youtubeData->title,
                        'description' => strval($youtubeData->description),
                        'image' => $youtubeData->thumbnails->medium->url,
                        'language' => $language,
                        'status' => 0,
                    ]);
                }
            }
            DB::commit();
            $notification = array(
                'message' => $request->Lang->Common->Form->UpdatedSuccessfully,
                'alert-type' => 'success',
            );
            if (@$request->save_and_update) {
                return redirect(route('youtube.edit', $uuid))->with($notification);
            } else {
                return redirect(route('youtube.index'))->with($notification);
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
                $data = YouTube::where('uuid', $id)->where('language', 'en')->first();
                $data->status = $data->status ^ 1;
                YouTube::where('uuid', $id)->update(['status' => $data->status]);
                return response([
                    'status' => (bool) $data->status,
                    'message' => ($data->status ? $request->Lang->Common->Form->PublishSuccessfully : $request->Lang->Common->Form->UnpublishSuccessfully),
                ], 200);
            }
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotUpdate], 403);
        }
    }

    public function destroy($id = null, Request $request)
    {
        try {
            YouTube::where('uuid', $id)->delete();
            return response(['message' => $request->Lang->Common->Form->DeleteSuccessfully], 200);
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotDelete], 403);
        }
    }

    private function validateWatchThresholds(Request $request): void
    {
        foreach ((array) $request->input('language', []) as $language) {
            $activation = (float) data_get($request->input('activision_time', []), $language, 0);
            $duration = (float) data_get($request->input('duration_time', []), $language, 0);
            if ($activation > $duration) {
                throw ValidationException::withMessages([
                    "activision_time.{$language}" => 'The completion threshold cannot exceed the video duration.',
                ]);
            }
        }
    }

}
