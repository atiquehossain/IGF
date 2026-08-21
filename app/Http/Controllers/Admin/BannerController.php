<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use App\Models\Banner;

use App\Rules\ValidateUniqueRule;

use App\Helper\Seq;
use App\Helper\Translation;
use App\Helper\IgfFile;
use App\Services\SafeMediaReplacementService;

use Exception;
use Throwable;

class BannerController extends Controller
{
    public function __construct(private SafeMediaReplacementService $media)
    {
    }

    public function index(Request $request)
    {
        $title = $request->Lang->BannerTitle;
        $search = $request->search;
        $banners = Banner::where('name', 'like', '%' . $search . '%')
            ->whereIN('type', ['banner-home', 'banner-page'])
            ->where('language', app()->getLocale())
            ->paginate(15);
        return view('admin.banner.index')->with(compact('title', 'banners', 'search'));
    }

    public function create(Request $request)
    {
        $title = $request->Lang->Common->New . " " . $request->Lang->BannerTitle;
        $translations = Translation::languageList();
        return view('admin.banner.add')->with(compact('title', 'translations'));
    }

    public function store(Request $request)
    {
        $this->validateBanner($request, true);
        $stagedAssets = [];
        $committed = false;

        try {
            $uuid = Seq::uuidV4();
            DB::transaction(function () use ($request, $uuid, &$stagedAssets): void {
                foreach ($request->language as $language) {
                    $banner = Banner::create([
                        'uuid' => $uuid,
                        'type' => $request->type[$language],
                        'language' => $language,
                        'status' => 0,
                    ] + $this->contentForLanguage($request, $language));

                    $image = $request->file("image.{$language}");
                    if ($image) {
                        $asset = $this->media->stageResizedPublicImage($image, 'banner', 1590, 690);
                        $stagedAssets[] = $asset;
                        $banner->update([
                            'image' => $asset->databaseValue,
                            'path' => $asset->databaseValue,
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
                return redirect(route('banner.edit', $uuid))->with($notification);
            } else {
                return redirect(route('banner.index'))->with($notification);
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
        return redirect()->route('banner.index')->with('message', 'Banner details are managed from the banner list.');
    }

    public function edit($id = null, Request $request)
    {
        try {
            $title = $request->Lang->Common->Edit . " " . $request->Lang->BannerTitle;
            $translations = Translation::languageList();
            $banners = Banner::where('uuid', $id)->get();
            return view('admin.banner.edit')->with(compact('title', 'translations', 'id', 'banners'));
        } catch (Exception $e) {
            $notification = array(
                'message' => $request->Lang->Common->Form->NotCreate,
                'alert-type' => 'error',
            );
            return back()->with($notification);
        }
    }

    public function update(Request $request)
    {
        $this->validateBanner($request);
        $stagedAssets = [];
        $oldImages = [];
        $committed = false;

        try {
            $uuid = $request->uuid;
            $find_banner = Banner::where('uuid', $uuid)->where('language', 'en')->first();
            if (empty($find_banner)) {
                $notification = array(
                    'message' => $request->Lang->Common->Form->NotFound,
                    'alert-type' => 'warning',
                );
                return back()->with($notification);
            }

            DB::transaction(function () use ($request, $uuid, &$stagedAssets, &$oldImages): void {
                $logicalBanners = Banner::query()
                    ->where('uuid', $uuid)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('language');
                if (!$logicalBanners->has('en')) {
                    throw new Exception('The English source banner no longer exists.');
                }

                foreach ($request->language as $language) {
                    $banner = $logicalBanners->get($language);
                    $image = $request->file("image.{$language}");
                    $asset = null;

                    if ($image) {
                        $asset = $this->media->stageResizedPublicImage(
                            $image,
                            'banner',
                            $banner ? 1590 : 1366,
                            $banner ? 690 : 417,
                        );
                        $stagedAssets[] = $asset;
                    }

                    if ($banner) {
                        if ($asset) {
                            $oldImages[] = [$banner->image, $banner->path];
                        }
                        $banner->update([
                            'uuid' => $uuid,
                            'type' => $request->type[$language],
                            'language' => $language,
                        ] + $this->contentForLanguage($request, $language) + ($asset ? [
                            'image' => $asset->databaseValue,
                            'path' => $asset->databaseValue,
                        ] : []));
                    } else {
                        $banner = Banner::create([
                            'uuid' => $uuid,
                            'type' => $request->type[$language],
                            'language' => $language,
                            'status' => 0,
                        ] + $this->contentForLanguage($request, $language) + ($asset ? [
                            'image' => $asset->databaseValue,
                            'path' => $asset->databaseValue,
                        ] : []));
                        $logicalBanners->put($language, $banner);
                    }
                }
            });
            $committed = true;
            foreach ($oldImages as $names) {
                $this->media->deleteLegacyFlatImages('banner', $names);
            }

            $notification = array(
                'message' => $request->Lang->Common->Form->UpdatedSuccessfully,
                'alert-type' => 'success',
            );
            if (@$request->save_and_update) {
                return redirect(route('banner.edit', $uuid))->with($notification);
            } else {
                return redirect(route('banner.index'))->with($notification);
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
                $data = Banner::where('uuid', $id)->where('language', 'en')->first();
                $data->status = $data->status ^ 1;
                Banner::where('uuid', $id)->update(['status' => $data->status]);
                return response(['message' => ($data->status ? $request->Lang->Common->Form->PublishSuccessfully : $request->Lang->Common->Form->UnpublishSuccessfully)], 200);
            }
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotUpdate], 403);
        }
    }

    public function destroy($id = null, Request $request)
    {
        try {
            $banners = Banner::where('uuid', $id)->get();
            foreach ($banners as $banner) {
                $banner->delete();
            }
            return response(['message' => $request->Lang->Common->Form->DeleteSuccessfully], 200);
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotDelete], 403);
        }
    }

    public function image($img = null)
    {
        return IgfFile::image('/banner/' . $img);
    }

    private function validateBanner(Request $request, bool $creating = false): void
    {
        $nameRules = ['nullable', 'string', 'max:255'];
        if ($creating) {
            $nameRules[] = new ValidateUniqueRule('banners');
        }

        $safeUrl = function (string $attribute, mixed $value, \Closure $fail): void {
            if (!$this->isSafePublicUrl((string) $value)) {
                $fail('Use a website path or an http(s), email, or telephone link.');
            }
        };

        $request->validate([
            'language' => ['required', 'array', 'min:1'],
            'language.*' => ['required', 'string', 'max:10'],
            'image.*' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp', 'max:500'],
            'name.*' => $nameRules,
            'eyebrow.*' => ['nullable', 'string', 'max:255'],
            'headline.*' => ['nullable', 'string', 'max:255'],
            'subheadline.*' => ['nullable', 'string', 'max:255'],
            'description.*' => ['nullable', 'string', 'max:500'],
            'image_alt.*' => ['nullable', 'string', 'max:255'],
            'cta_label.*' => ['nullable', 'string', 'max:120'],
            'cta_url.*' => ['nullable', 'string', 'max:2048', $safeUrl],
            'url.*' => ['nullable', 'string', 'max:2048', $safeUrl],
            'type.*' => ['required', 'in:banner-home,banner-page'],
        ]);

        foreach ((array) $request->input('language', []) as $language) {
            if ($this->plainText($request->input("headline.$language")) === ''
                && trim((string) $request->input("name.$language")) === '') {
                throw ValidationException::withMessages([
                    "headline.$language" => 'Enter a headline for this banner.',
                ]);
            }
        }
    }

    private function contentForLanguage(Request $request, string $language): array
    {
        $headline = $this->plainText($request->input("headline.$language"));
        $subheadline = $this->plainText($request->input("subheadline.$language"));
        $legacyName = trim((string) $request->input("name.$language"));

        if ($legacyName === '') {
            $legacyName = '<b>' . $headline . '</b>' . ($subheadline !== '' ? ' ' . $subheadline : '');
        }

        return [
            'name' => $legacyName,
            'eyebrow' => $this->plainText($request->input("eyebrow.$language")),
            'headline' => $headline,
            'subheadline' => $subheadline,
            'description' => $this->plainText($request->input("description.$language")),
            'image_alt' => $this->plainText($request->input("image_alt.$language")),
            'cta_label' => $this->plainText($request->input("cta_label.$language")),
            'cta_url' => trim((string) $request->input("cta_url.$language")),
            'url' => trim((string) $request->input("url.$language")),
        ];
    }

    private function plainText(mixed $value): string
    {
        return trim(strip_tags((string) $value));
    }

    private function isSafePublicUrl(string $value): bool
    {
        $value = trim($value);

        if ($value === '' || str_starts_with($value, '/') || str_starts_with($value, '#')) {
            return true;
        }

        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $value, $matches)) {
            return in_array(strtolower($matches[1]), ['http', 'https', 'mailto', 'tel'], true);
        }

        return !preg_match('/[\x00-\x1F\x7F]/', $value);
    }

}
