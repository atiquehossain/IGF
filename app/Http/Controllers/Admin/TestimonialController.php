<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\Testimonial;

use App\Rules\ValidateUniqueRule;

use App\Helper\Seq;
use App\Helper\StaticUtil;
use App\Helper\Str;
use App\Helper\Translation;
use App\Helper\IgfFile;
use App\Services\SafeMediaReplacementService;

use Exception;
use Throwable;

class TestimonialController extends Controller
{
    public function __construct(private SafeMediaReplacementService $media)
    {
    }

    public function index(Request $request)
    {
        $title = $request->Lang->Menu->Testimonial;
        $search = $request->search;

        $translations = Translation::languageList();

        $testimonials = Testimonial::where('language', app()->getLocale())
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            })
            ->orderBy('created_at', 'DESC')
            ->paginate(15);

        return view('admin.testimonial.index')->with(compact('title', 'testimonials', 'search', 'translations'));
    }

    public function create()
    {
        return redirect()->route('testimonial.index')->with('message', 'Create testimonials from the testimonial list.');
    }

    public function store(Request $request)
    {
        $request['slug'] = Str::slug(@$request->name['en']);

        $this->validate(request(), [
            'photo.*' => 'mimes:jpeg,png,jpg,webp|max:500',
            'name.*' =>  ['required', new ValidateUniqueRule('testimonials'), 'nullable']
        ]);

        $stagedAssets = [];
        $committed = false;
        try {
            DB::beginTransaction();
            $uuid = Seq::uuidV4();
            foreach ($request->language as $language) {
                $testimonial = Testimonial::create([
                    'uuid' => $uuid,
                    'name' => $request->name[$language],
                    'designation' => @$request->designation[$language],
                    'testimonial' => @$request->testimonial[$language],
                    'order_by' => @$request->order_by[$language],
                    'language' => $language,
                    'status' => 0
                ]);

                if ($request->hasFile('photo') && @$request->photo[$language]) {
                    $asset = $this->media->stageResizedPublicImage(
                        $request->file('photo')[$language],
                        'testimonial',
                        300,
                        300,
                    );
                    $stagedAssets[] = $asset;
                    $testimonial->update([
                        'photo' => $asset->databaseValue,
                        'path' => $asset->databaseValue,
                    ]);
                }
            }

            DB::commit();
            $committed = true;

            $notification = array(
                'message' => $request->Lang->Common->Form->AddedSuccessfully,
                'alert-type' => 'success',
            );
            if (@$request->save_and_update) {
                return redirect(route('testimonial.edit', $uuid))->with($notification);
            } else {
                return redirect(route('testimonial.index'))->with($notification);
            }
        } catch (Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if (!$committed) {
                $this->media->discardMany($stagedAssets);
            }
            $notification = array(
                'message' => $request->Lang->Common->Form->NotCreate,
                'alert-type' => 'error',
            );
            return back()->with($notification);
        }
    }

    public function edit($id = null, Request $request)
    {
        $stagedAssets = [];
        $oldImages = [];
        $committed = false;
        try {
            $testimonial = Testimonial::select('*')->where('uuid', $id)->get();
            $response = ['data' => $testimonial];
            return response($response, 200);
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->DataNotFound], 403);
        }
    }

    public function update(Request $request)
    {
        $this->validate(request(), [
            'uuid' => 'required|string',
            'photo.*' => 'mimes:jpeg,png,jpg,webp|max:500',
            'name.*' =>  ['required', new ValidateUniqueRule('testimonials|uuid,' . $request->uuid), 'nullable']
        ]);

        try {
            $uuid = $request->uuid;
            $find_testimonial = Testimonial::where('uuid', $uuid)->where('language', 'en')->first();
            if (empty($find_testimonial)) {
                $notification = array(
                    'message' => $request->Lang->Common->Form->NotFound,
                    'alert-type' => 'warning',
                );
                return back()->with($notification);
            }

            DB::beginTransaction();
            $logicalTestimonials = Testimonial::query()
                ->where('uuid', $uuid)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('language');
            if (!$logicalTestimonials->has('en')) {
                throw new Exception('The English source testimonial no longer exists.');
            }

            foreach ($request->language as $language) {
                $testimonial = $logicalTestimonials->get($language);

                if ($testimonial) {
                    $testimonial->update([
                        'uuid' => $uuid,
                        'name' => $request->name[$language],
                        'designation' => @$request->designation[$language],
                        'testimonial' => @$request->testimonial[$language],
                        'order_by' => @$request->order_by[$language],
                        'language' => $language,
                    ]);

                    if ($request->hasFile('photo') && @$request->photo[$language]) {
                        $asset = $this->media->stageResizedPublicImage(
                            $request->file('photo')[$language],
                            'testimonial',
                            300,
                            300,
                        );
                        $stagedAssets[] = $asset;
                        $oldImages[] = [$testimonial->photo, $testimonial->path];
                        $testimonial->update([
                            'photo' => $asset->databaseValue,
                            'path' => $asset->databaseValue,
                        ]);
                    }
                } else {
                    $testimonial = Testimonial::create([
                        'uuid' => $uuid,
                        'name' => $request->name[$language],
                        'designation' => @$request->designation[$language],
                        'testimonial' => @$request->testimonial[$language],
                        'order_by' => @$request->order_by[$language],
                        'language' => $language,
                        'status' => 0
                    ]);

                    if ($request->hasFile('photo') && @$request->photo[$language]) {
                        $asset = $this->media->stageResizedPublicImage(
                            $request->file('photo')[$language],
                            'testimonial',
                            300,
                            300,
                        );
                        $stagedAssets[] = $asset;
                        $testimonial->update([
                            'photo' => $asset->databaseValue,
                            'path' => $asset->databaseValue,
                        ]);
                    }
                    $logicalTestimonials->put($language, $testimonial);
                }
            }
            DB::commit();
            $committed = true;
            foreach ($oldImages as $names) {
                $this->media->deleteLegacyFlatImages('testimonial', $names);
            }

            $notification = array(
                'message' => $request->Lang->Common->Form->UpdatedSuccessfully,
                'alert-type' => 'success',
            );

            return back()->with($notification);
        } catch (Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if (!$committed) {
                $this->media->discardMany($stagedAssets);
            }

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
                $data = Testimonial::where('uuid', $id)->where('language', 'en')->first();
                $data->status = $data->status ^ 1;
                Testimonial::where('uuid', $id)->update(['status' => $data->status]);
                return response(['message' => ($data->status ? $request->Lang->Common->Form->PublishSuccessfully : $request->Lang->Common->Form->UnpublishSuccessfully)], 200);
            }
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotUpdate], 403);
        }
    }

    public function destroy($id = null, Request $request)
    {
        try {
            Testimonial::where('uuid', $id)->delete();
            return response(['message' => $request->Lang->Common->Form->DeleteSuccessfully], 200);
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotDelete], 403);
        }
    }

    public function photo($img = null)
    {
        return IgfFile::image('/testimonial/' . $img);
    }
}
