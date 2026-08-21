<?php

namespace App\Http\Controllers\Admin;

use App\Helper\IgfFile;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

use App\Models\Banner;
use App\Models\Category;
use App\Models\DonationType;
use App\Models\Page;

use App\Rules\ValidateUniqueRule;
use App\Services\ContentSanitizer;
use App\Services\SafeMediaReplacementService;

use App\Helper\Seq;
use App\Helper\StaticUtil;
use App\Helper\Str;
use App\Helper\Translation;
use Exception;
use Throwable;
use Illuminate\Validation\ValidationException;


class CategoryController extends Controller
{
    public function __construct(
        private ContentSanitizer $sanitizer,
        private SafeMediaReplacementService $media,
    ) {
    }

    public function index(Request $request)
    {
        $title = $request->Lang->Category;
        $search = $request->search;
        $categories = Category::where(function ($query) use ($search) {
            $query->where('name', 'like', '%' . $search . '%');
            $query->orWhere('slug', 'like', '%' . $search . '%');
        })
            ->where('categories.language', app()->getLocale())
            ->paginate(15);
        $bannerList = Banner::where('type', 'banner')->where('language', app()->getLocale())->where('status', 1)->get();
        return view('admin.category.index')->with(compact('title', 'categories', 'bannerList', 'search'));
    }

    public function create(Request $request)
    {
        $title = $request->Lang->Common->New . " " . $request->Lang->Category;
        $translations = Translation::languageList();
        $bannerList = Banner::whereIN('type', ['banner-home', 'banner-page'])->where('status', 1)->get();
        $landingPagesByLanguage = collect();

        return view('admin.category.add')->with(compact('title', 'translations', 'bannerList', 'landingPagesByLanguage'));
    }

    public function store(Request $request)
    {
        $request['slug'] = Str::slug(@$request->name['en']);

        $this->validate(request(), [
            'name.*' =>  ['required', new ValidateUniqueRule('categories'), 'nullable'],
            'slug.*' =>  ['required', new ValidateUniqueRule('categories'), 'nullable'],
            'image.*' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp', 'max:1500'],
            'display_mode' => ['nullable', 'array'],
            'display_mode.*' => ['nullable', Rule::in(['archive', 'landing_page'])],
            'landing_page_uuid' => ['nullable', 'array'],
            'landing_page_uuid.*' => ['nullable', 'string', 'max:36'],
        ]);

        $stagedAssets = [];
        $committed = false;
        try {
            $landingConfigurations = $this->validatedLandingPageAssignments($request, collect());
            $uuid = Seq::uuidV4();
            DB::transaction(function () use ($request, $uuid, $landingConfigurations, &$stagedAssets): void {
                foreach ($request->language as $language) {
                    $description = $this->sanitizer->sanitizeHtml(StaticUtil::pageRemoveNewLine(@$request->description[$language]));
                    $inline_css = $this->sanitizer->sanitizeCss(StaticUtil::pageRemoveNewLine(@$request->inline_css[$language]));
                    $image = $request->file("image.{$language}");
                    $asset = null;
                    if ($image) {
                        $asset = $this->media->stageResizedPublicImage($image, 'category', 1180, 440);
                        $stagedAssets[] = $asset;
                    }

                    Category::create([
                        'uuid' => $uuid,
                        'name' => $request->name[$language],
                        'slug' => $request->slug,
                        'language' => $language,
                        'type' => @$request->type[$language],
                        'display_mode' => $landingConfigurations[$language]['display_mode'],
                        'landing_page_uuid' => $landingConfigurations[$language]['landing_page_uuid'],
                        'banner_id' => @$request->banner_id[$language],
                        'description' => @$description,
                        'inline_css' => @$inline_css,
                        'name_enabled' => @$request->name_enabled[$language] ?? 1,
                        'status' => 0,
                    ] + ($asset ? [
                        'image' => $asset->databaseValue,
                        'path' => $asset->databaseValue,
                    ] : []));
                }
            });
            $committed = true;
            $notification = array(
                'message' => $request->Lang->Common->Form->AddedSuccessfully,
                'alert-type' => 'success',
            );
            if (@$request->save_and_update) {
                return redirect(route('category.edit', $uuid))->with($notification);
            } else {
                return redirect(route('category.index'))->with($notification);
            }
        } catch (ValidationException $e) {
            if (!$committed) {
                $this->media->discardMany($stagedAssets);
            }
            throw $e;
        } catch (Throwable $e) {
            if (!$committed) {
                $this->media->discardMany($stagedAssets);
            }
            $notification = array(
                'message' => $request->Lang->Common->Form->NotCreate ?? 'The category could not be created.',
                'alert-type' => 'error',
            );
            return back()->with($notification);
        }
    }

    public function edit(Request $request, $uuid = null)
    {
        try {
            $title = $request->Lang->Common->Edit . " " . $request->Lang->Category;
            $translations = Translation::languageList();
            $categories = Category::where('uuid', $uuid)->get();
            $bannerList = Banner::whereIN('type', ['banner-home', 'banner-page'])->where('status', 1)->get();
            $landingPagesByLanguage = collect();
            foreach ($categories as $category) {
                $categoryKeys = array_values(array_unique(array_filter([
                    (string) $category->id,
                    trim((string) $category->uuid),
                ])));
                $landingPagesByLanguage->put($category->language, Page::query()
                    ->where('language', $category->language)
                    ->whereIn('category_id', $categoryKeys)
                    ->orderBy('name')
                    ->get(['uuid', 'name', 'publication_status', 'status', 'language'])
                    ->unique('uuid')
                    ->values());
            }

            return view('admin.category.edit')->with(compact(
                'title', 'translations', 'uuid', 'categories', 'bannerList', 'landingPagesByLanguage'
            ));
        } catch (Exception $e) {
            $notification = array(
                'message' => $request->Lang->Common->Form->NotFound ?? 'The category could not be found.',
                'alert-type' => 'error',
            );
            return back()->with($notification);
        }
    }

    public function update(Request $request)
    {
        $this->validate(request(), [
            'name.*' =>  ['required', new ValidateUniqueRule('categories|uuid,' . $request->uuid), 'nullable'],
            'slug.*' =>  ['required', new ValidateUniqueRule('categories|uuid,' . $request->uuid), 'nullable'],
            'image.*' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp', 'max:1500'],
            'display_mode' => ['nullable', 'array'],
            'display_mode.*' => ['nullable', Rule::in(['archive', 'landing_page'])],
            'landing_page_uuid' => ['nullable', 'array'],
            'landing_page_uuid.*' => ['nullable', 'string', 'max:36'],
        ]);

        $stagedAssets = [];
        $oldImages = [];
        $committed = false;
        try {
            $uuid = $request->uuid;
            $find_category = Category::where('uuid', $uuid)->where('language', 'en')->first();
            if (empty($find_category)) {
                $notification = array(
                    'message' => $request->Lang->Common->Form->NotFound,
                    'alert-type' => 'warning',
                );
                return back()->with($notification);
            }
            $categoryTranslations = Category::where('uuid', $uuid)->get()->keyBy('language');
            $landingConfigurations = $this->validatedLandingPageAssignments($request, $categoryTranslations);
            DB::transaction(function () use ($request, $uuid, $landingConfigurations, &$stagedAssets, &$oldImages): void {
                $logicalCategories = Category::query()
                    ->where('uuid', $uuid)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('language');
                $source = $logicalCategories->get('en');
                if (!$source) {
                    throw new Exception('The English source category no longer exists.');
                }

                foreach ($request->language as $language) {
                    $description = $this->sanitizer->sanitizeHtml(StaticUtil::pageRemoveNewLine(@$request->description[$language]));
                    $inline_css = $this->sanitizer->sanitizeCss(StaticUtil::pageRemoveNewLine(@$request->inline_css[$language]));
                    $category = $logicalCategories->get($language);
                    $image = $request->file("image.{$language}");
                    $asset = null;
                    if ($image) {
                        $asset = $this->media->stageResizedPublicImage($image, 'category', 1180, 440);
                        $stagedAssets[] = $asset;
                    }

                    $attributes = [
                        'uuid' => $uuid,
                        'name' => $request->name[$language],
                        'language' => $language,
                        'type' => @$request->type[$language],
                        'display_mode' => $landingConfigurations[$language]['display_mode'],
                        'landing_page_uuid' => $landingConfigurations[$language]['landing_page_uuid'],
                        'banner_id' => @$request->banner_id[$language],
                        'description' => @$description,
                        'inline_css' => @$inline_css,
                        'name_enabled' => @$request->name_enabled[$language] ?? ($category ? null : 1),
                    ] + ($asset ? [
                        'image' => $asset->databaseValue,
                        'path' => $asset->databaseValue,
                    ] : []);

                    if ($category) {
                        if ($asset) {
                            $oldImages[] = [$category->image, $category->path];
                        }
                        $category->update($attributes);
                    } else {
                        $category = Category::create($attributes + [
                            'slug' => $source->slug,
                            'status' => 0,
                        ]);
                        $logicalCategories->put($language, $category);
                    }
                }
            });
            $committed = true;
            foreach ($oldImages as $names) {
                $this->media->deleteLegacyFlatImages('category', $names);
            }
            $notification = array(
                'message' => $request->Lang->Common->Form->UpdatedSuccessfully,
                'alert-type' => 'success',
            );
            if (@$request->save_and_update) {
                return redirect(route('category.edit', $uuid))->with($notification);
            } else {
                return redirect(route('category.index'))->with($notification);
            }
        } catch (ValidationException $e) {
            if (!$committed) {
                $this->media->discardMany($stagedAssets);
            }
            throw $e;
        } catch (Throwable $e) {
            if (!$committed) {
                $this->media->discardMany($stagedAssets);
            }
            $notification = array(
                'message' => $request->Lang->Common->Form->NotUpdate ?? 'The category could not be updated.',
                'alert-type' => 'error',
            );
            return back()->with($notification);
        }
    }

    public function status(Request $request, $id = null)
    {
        try {
            if ($request->ajax()) {
                return DB::transaction(function () use ($request, $id) {
                    $categories = Category::query()
                        ->where('uuid', $id)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();
                    $data = $categories->firstWhere('language', 'en') ?? $categories->first();
                    if (!$data) {
                        return response(['message' => $request->Lang->Common->Form->NotFound], 404);
                    }
                    if ($data->status && $this->hasActiveDonationCause((string) $data->uuid, true)) {
                        return response([
                            'message' => 'An active donation cause uses this program. Reassign or unpublish the cause before unpublishing the program.',
                        ], 422);
                    }
                    $nextStatus = !$data->status;
                    Category::where('uuid', $id)->update(['status' => $nextStatus]);
                    return response(['message' => ($nextStatus ? $request->Lang->Common->Form->PublishSuccessfully : $request->Lang->Common->Form->UnpublishSuccessfully)], 200);
                });
            }
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotUpdate ?? 'The category could not be updated.'], 403);
        }
    }

    public function destroy(Request $request, $id = null)
    {
        try {
            return DB::transaction(function () use ($request, $id) {
                $categories = Category::query()
                    ->where('uuid', $id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                if ($categories->isEmpty()) {
                    return response(['message' => $request->Lang->Common->Form->NotFound], 404);
                }
                if ($this->hasActiveDonationCause((string) $id, true)) {
                    return response([
                        'message' => 'An active donation cause uses this program. Reassign or unpublish the cause before deleting the program.',
                    ], 422);
                }
                Category::where('uuid', $id)->delete();

                return response(['message' => $request->Lang->Common->Form->DeleteSuccessfully], 200);
            });
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotDelete ?? 'The category could not be deleted.'], 403);
        }
    }

    public function image($img = null)
    {
        return IgfFile::image('/category/' . $img);
    }

    private function validatedLandingPageAssignments(Request $request, $categories): array
    {
        $configurations = [];

        foreach ((array) $request->input('language', []) as $language) {
            if (!is_string($language)) {
                continue;
            }

            $displayMode = (string) $request->input("display_mode.$language", 'archive');
            if ($displayMode !== 'landing_page') {
                $configurations[$language] = [
                    'display_mode' => 'archive',
                    'landing_page_uuid' => null,
                ];
                continue;
            }

            $landingPageUuid = trim((string) $request->input("landing_page_uuid.$language"));
            $category = $categories->get($language);
            $categoryKeys = $category ? array_values(array_unique(array_filter([
                (string) $category->id,
                trim((string) $category->uuid),
            ]))) : [];

            $isValidLandingPage = $landingPageUuid !== ''
                && $category
                && Page::query()
                    ->where('uuid', $landingPageUuid)
                    ->where('language', $language)
                    ->whereIn('category_id', $categoryKeys)
                    ->exists();

            if (!$isValidLandingPage) {
                throw ValidationException::withMessages([
                    "landing_page_uuid.$language" => 'Choose a landing page assigned to this category and language.',
                ]);
            }

            $configurations[$language] = [
                'display_mode' => 'landing_page',
                'landing_page_uuid' => $landingPageUuid,
            ];
        }

        return $configurations;
    }

    private function hasActiveDonationCause(string $uuid, bool $lock = false): bool
    {
        $query = DonationType::query()
            ->where('status', 1)
            ->where('destination_type', 'category')
            ->where('destination_category_uuid', $uuid);

        return $lock
            ? $query->orderBy('id')->lockForUpdate()->get(['id'])->isNotEmpty()
            : $query->exists();
    }
}
