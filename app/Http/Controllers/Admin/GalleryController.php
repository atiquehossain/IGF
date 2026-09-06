<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\Album;
use App\Models\Gallery;

use App\Rules\ValidateUniqueRule;

use App\Helper\Seq;
use App\Helper\Translation;
use App\Helper\IgfFile;
use App\Services\AdminMediaUrlResolver;
use App\Services\SafeMediaReplacementService;

use Exception;
use Throwable;

class GalleryController extends Controller
{
    public function __construct(
        private SafeMediaReplacementService $media,
        private AdminMediaUrlResolver $mediaUrls,
    ) {
    }

    public function index(Request $request)
    {
        $title = $request->Lang->Menu->Gallery;
        $search = $request->search;
        $gallerys = Gallery::select('galleries.*', 'albums.name as album_name', 'albums.status as album_status')
            ->where('galleries.name', 'like', '%' . $search . '%')
            ->leftjoin('albums', 'albums.id', '=', 'galleries.album_id')
            ->where('galleries.type', 'gallery')
            ->where('galleries.language', app()->getLocale())
            ->orderByRaw('CASE WHEN galleries.order_by IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('galleries.order_by')
            ->orderByDesc('galleries.id')
            ->paginate(15);

        $gallerys->getCollection()->each(function (Gallery $gallery): void {
            $gallery->setAttribute('display_image_url', $this->mediaUrls->image(
                $gallery->getRawOriginal('path') ?: $gallery->getRawOriginal('image'),
                'gallery',
                (int) $gallery->id,
                '430X360',
            ));
        });

        return view('admin.gallery.index')->with(compact('title', 'gallerys', 'search'));
    }

    public function create(Request $request)
    {
        $title = $request->Lang->Common->New . " " . $request->Lang->Menu->Gallery;
        $translations = Translation::languageList();
        $albums = Album::where('status', 1)->orderBy('name')->orderBy('id')->get();
        $nextPriority = ((int) (Gallery::where('type', 'gallery')->max('order_by') ?? 0)) + 10;

        return view('admin.gallery.add')->with(compact('title', 'translations', 'albums', 'nextPriority'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'language' => 'required|array|min:1',
            'language.*' => 'required|string|max:10',
            'image' => 'required|array',
            'image.*' => 'required|mimes:jpeg,png,jpg|max:1000',
            'album_id' => 'required|array',
            'album_id.*' => 'required|integer|exists:albums,id',
            'name' => 'required|array',
            'name.*' => ['required', 'string', 'max:255', new ValidateUniqueRule('galleries')],
            'description' => 'nullable|array',
            'description.*' => 'nullable|string|max:120',
            'order_by' => 'nullable|integer|min:0|max:1000000',
        ]);

        $stagedAssets = [];
        $committed = false;
        try {
            $uuid = Seq::uuidV4();
            $orderBy = $request->filled('order_by')
                ? (int) $request->input('order_by')
                : ((int) (Gallery::where('type', 'gallery')->max('order_by') ?? 0)) + 10;
            DB::transaction(function () use ($request, $uuid, $orderBy, &$stagedAssets): void {
                foreach ($request->language as $language) {
                    $description = trim((string) data_get($request->input('description', []), $language));
                    $gallery = Gallery::create([
                        'uuid' => $uuid,
                        'name' => @$request->name[$language],
                        'description' => $description !== '' ? $description : null,
                        'type' => 'gallery',
                        'album_id' => $request->album_id[$language],
                        'url' => @$request->url[$language],
                        'language' => $language,
                        'order_by' => $orderBy,
                        'status' => 0,
                    ]);

                    $image = $request->file("image.{$language}");
                    if ($image) {
                        $asset = $this->media->stageGalleryImage($image, $gallery->id);
                        $stagedAssets[] = $asset;
                        $gallery->update([
                            'image' => $asset->databaseValue,
                            'path' => $asset->databaseValue,
                            'grid_column' => 0,
                            'grid_row' => 0,
                        ]);
                    }
                }
            });
            $committed = true;

            $notification = array(
                'message' => $request->Lang->Common->Form->AddedSuccessfully,
                'alert-type' => 'success',
            );

            if (@$request->save_and_update) {
                return redirect(route('gallery.edit', $uuid))->with($notification);
            } else {
                return redirect(route('gallery.index'))->with($notification);
            }
        } catch (Throwable $e) {
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

    public function show($id = null, Request $request)
    {
        return redirect()->route('gallery.index')->with('message', 'Gallery details are managed from the gallery list.');
    }

    public function edit($uuid = null, Request $request)
    {
        try {
            $title = $request->Lang->Common->Edit . " " . $request->Lang->Menu->Gallery;
            $translations = Translation::languageList();
            $galleries = Gallery::where('uuid', $uuid)->get();
            $galleries->each(function (Gallery $gallery): void {
                $gallery->setAttribute('display_image_url', $this->mediaUrls->image(
                    $gallery->getRawOriginal('path') ?: $gallery->getRawOriginal('image'),
                    'gallery',
                    (int) $gallery->id,
                    'main',
                ));
            });
            $currentAlbumIds = $galleries->pluck('album_id')->filter()->all();
            $albums = Album::query()
                ->where(function ($query) use ($currentAlbumIds): void {
                    $query->where('status', 1);
                    if ($currentAlbumIds !== []) {
                        $query->orWhereIn('id', $currentAlbumIds);
                    }
                })
                ->orderBy('name')
                ->orderBy('id')
                ->get();
            return view('admin.gallery.edit')->with(compact('title', 'translations', 'uuid', 'galleries', 'albums'));
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
        $request->validate([
            'language' => 'required|array|min:1',
            'language.*' => 'required|string|max:10',
            'name' => 'required|array',
            'name.*' => ['required', 'string', 'max:255', new ValidateUniqueRule('galleries|uuid,' . $request->uuid)],
            'description' => 'nullable|array',
            'description.*' => 'nullable|string|max:120',
            'album_id' => 'required|array',
            'album_id.*' => 'required|integer|exists:albums,id',
            'image.*' => 'mimes:jpeg,png,jpg|max:1500',
            'id' => 'nullable|array',
            'id.*' => 'nullable|string',
            'order_by' => 'nullable|integer|min:0|max:1000000',
        ]);

        $stagedAssets = [];
        $oldImages = [];
        $committed = false;
        try {
            $uuid = $request->uuid;
            $find_gallery = Gallery::where('uuid', $uuid)->where('language', 'en')->first();
            if (empty($find_gallery)) {
                $notification = array(
                    'message' => $request->Lang->Common->Form->NotFound,
                    'alert-type' => 'warning',
                );
                return back()->with($notification);
            }

            DB::transaction(function () use ($request, $uuid, &$stagedAssets, &$oldImages): void {
                $logicalGalleries = Gallery::query()
                    ->where('uuid', $uuid)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('language');
                if (!$logicalGalleries->has('en')) {
                    throw new Exception('The English source gallery item no longer exists.');
                }

                $requestedOrder = $request->filled('order_by')
                    ? (int) $request->input('order_by')
                    : null;
                $descriptions = $request->input('description', []);

                foreach ($request->language as $language) {
                    $gallery = $logicalGalleries->get($language);
                    if (!$gallery) {
                        $description = trim((string) data_get($descriptions, $language));
                        $gallery = Gallery::create([
                            'uuid' => $uuid,
                            'name' => @$request->name[$language],
                            'description' => $description !== '' ? $description : null,
                            'type' => 'gallery',
                            'album_id' => $request->album_id[$language],
                            'url' => @$request->url[$language],
                            'language' => $language,
                            'order_by' => $requestedOrder ?? $logicalGalleries->get('en')->order_by,
                            'status' => 0,
                        ]);
                        $logicalGalleries->put($language, $gallery);
                    }

                    $image = $request->file("image.{$language}");
                    $asset = null;
                    if ($image) {
                        $asset = $this->media->stageGalleryImage($image, $gallery->id);
                        $stagedAssets[] = $asset;
                        if ($gallery->image || $gallery->path) {
                            $oldImages[] = [$gallery->id, [$gallery->image, $gallery->path]];
                        }
                    }

                    $attributes = [
                        'uuid' => $uuid,
                        'name' => $request->name[$language],
                        'album_id' => $request->album_id[$language],
                        'url' => @$request->url[$language],
                        'language' => $language,
                    ];
                    if (array_key_exists($language, $descriptions)) {
                        $description = trim((string) $descriptions[$language]);
                        $attributes['description'] = $description !== '' ? $description : null;
                    }
                    if ($requestedOrder !== null) {
                        $attributes['order_by'] = $requestedOrder;
                    }

                    $gallery->update($attributes + ($asset ? [
                        'image' => $asset->databaseValue,
                        'path' => $asset->databaseValue,
                        'grid_column' => 0,
                        'grid_row' => 0,
                    ] : []));
                }
            });
            $committed = true;
            foreach ($oldImages as [$recordId, $names]) {
                $this->media->deleteLegacyGalleryImages($recordId, $names);
            }

            $notification = array(
                'message' => $request->Lang->Common->Form->UpdatedSuccessfully,
                'alert-type' => 'success',
            );

            if (@$request->save_and_update) {
                return redirect(route('gallery.edit', $uuid))->with($notification);
            } else {
                return redirect(route('gallery.index'))->with($notification);
            }
        } catch (Throwable $e) {
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
                $data = Gallery::where('uuid', $id)->where('language', 'en')->first();
                $data->status = $data->status ^ 1;
                Gallery::where('uuid', $id)->update(['status' => $data->status]);
                return response([
                    'message' => ($data->status ? $request->Lang->Common->Form->PublishSuccessfully : $request->Lang->Common->Form->UnpublishSuccessfully),
                    'status' => (bool) $data->status,
                ], 200);
            }
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotUpdate], 403);
        }
    }

    public function destroy($id = null, Request $request)
    {
        try {
            $galleries = Gallery::where('uuid', $id)->get();
            foreach ($galleries as $gallery) {
                $gallery->delete();
            }
            return response(['message' => $request->Lang->Common->Form->DeleteSuccessfully], 200);
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotDelete], 403);
        }
    }

    public function image($id = null, $size = null, $img = null)
    {
        return IgfFile::image('/gallery/' . $id . '/' . $size . '/' . $img);
    }
}
