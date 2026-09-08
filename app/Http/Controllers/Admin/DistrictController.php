<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use App\Models\District;
use App\Models\Division;
use App\Models\LatestNews;
use App\Models\MediaAsset;
use App\Models\NoticeBoard;
use App\Models\Upazila;
use App\Models\User;
use App\Services\SafeMediaReplacementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str as SupportStr;
use Illuminate\Validation\ValidationException;
use Throwable;

class DistrictController extends Controller {

    public function __construct(private SafeMediaReplacementService $media)
    {
    }

    public function index(Request $request) {
        $title = $request->Lang->DistrictTitle;
        $search = $request->search;

        $districts = District::with('division')->where('name', 'like', '%' . $search . '%')->paginate(15);

        $divisions = Division::where('status', 1)->orderBy('name', 'DESC')->get();
        $mediaAssets = $this->imageAssets();

        return view('admin.district.index')->with(compact('title', 'districts', 'divisions', 'mediaAssets', 'search'));
    }

    public function create() {
        return redirect()->route('district.index');
    }

    public function store(Request $request) {
        $validated = $request->validate($this->rules($request));
        $this->validateImageIntent($request, $validated);
        $staged = null;
        $committed = false;
        try {
            if ($request->hasFile('hero_image_upload')) {
                $staged = $this->media->stageResizedPublicImage(
                    $request->file('hero_image_upload'),
                    'districts',
                    1400,
                );
            }
            $libraryImage = $this->selectedMediaImage($validated['hero_image_media_uuid'] ?? null);

            $district = DB::transaction(fn (): District => District::create([
                'name' => trim((string) $validated['name']),
                'slug' => SupportStr::slug((string) $validated['name']),
                'division_id' => (int) $validated['division_id'],
                'description' => $this->nullableNarrative($validated['description'] ?? null),
                'description_bn' => $this->nullableNarrative($validated['description_bn'] ?? null),
                'hero_image' => $staged?->databaseValue ?? $libraryImage,
                'hero_image_alt' => ($staged || $libraryImage)
                    ? $this->nullableText($validated['hero_image_alt'] ?? null)
                    : null,
                'hero_image_alt_bn' => ($staged || $libraryImage)
                    ? $this->nullableText($validated['hero_image_alt_bn'] ?? null)
                    : null,
                'status' => 0,
            ]));
            $committed = true;
            $notification = array(
                'message' => $request->Lang->Common->Form->AddedSuccessfully,
                'alert-type' => 'success'
            );
            return back()->with($notification);
        } catch (Throwable $e) {
            if (!$committed && $staged) {
                $this->media->discardMany([$staged]);
            }
            $notification = array(
                'message' => $request->Lang->Common->Form->NotCreate,
                'alert-type' => 'error'
            );
            return back()->with($notification);
        }
    }


    public function edit($id = null, Request $request) {
        try {
            $district = District::select(
                'id',
                'name',
                'slug',
                'division_id',
                'description',
                'description_bn',
                'hero_image',
                'hero_image_alt',
                'hero_image_alt_bn'
            )->where('id', $id)->first();
            if ($district === null) {
                return response([ 'message' => $request->Lang->Common->Form->DataNotFound], 404);
            }
            $district->setAttribute('hero_image_url', $this->districtHeroUrl($district->hero_image));
            $district->setAttribute('hero_image_media_uuid', $this->selectedMediaUuid($district->hero_image));
            $response = [ 'data' => $district];
            return response($response, 200);
        } catch (Throwable $e) {
            return response([ 'message' => $request->Lang->Common->Form->DataNotFound], 404);
        }
    }

    public function update(Request $request) {
        $district = District::find($request->integer('id'));
        $validated = $request->validate($this->rules($request, $district) + [
            'id' => 'required|integer|exists:districts,id',
        ]);
        $this->validateImageIntent($request, $validated);
        $staged = null;
        $committed = false;
        try {
            if (empty($district)) {
                $notification = array(
                    'message' => $request->Lang->Common->Form->NotFound,
                    'alert-type' => 'warning'
                );
                return back()->with($notification);
            }

            if ((int) $district->division_id !== (int) $request->division_id
                && (User::where('district_id', $district->id)->exists()
                    || LatestNews::where('type', 'our-members')->where('district_id', $district->id)->exists()
                    || NoticeBoard::where('district_id', $district->id)->exists())) {
                return back()->with([
                    'message' => 'This district cannot be moved while registrations, team members, or activities reference it.',
                    'alert-type' => 'warning',
                ]);
            }

            if ($request->hasFile('hero_image_upload')) {
                $staged = $this->media->stageResizedPublicImage(
                    $request->file('hero_image_upload'),
                    'districts',
                    1400,
                );
            }

            $oldImage = $district->hero_image;
            $heroImage = $oldImage;
            if ($request->boolean('remove_hero_image')) {
                $heroImage = null;
            } elseif ($staged) {
                $heroImage = $staged->databaseValue;
            } elseif (!empty($validated['hero_image_media_uuid'])) {
                $heroImage = $this->selectedMediaImage($validated['hero_image_media_uuid']);
            }

            DB::transaction(function () use ($district, $validated, $heroImage): void {
                $district->update([
                    'name' => trim((string) $validated['name']),
                    // Keep the original slug so links already shared with visitors remain valid.
                    'division_id' => (int) $validated['division_id'],
                    'description' => array_key_exists('description', $validated)
                        ? $this->nullableNarrative($validated['description'])
                        : $district->description,
                    'description_bn' => array_key_exists('description_bn', $validated)
                        ? $this->nullableNarrative($validated['description_bn'])
                        : $district->description_bn,
                    'hero_image' => $heroImage,
                    'hero_image_alt' => $heroImage === null
                        ? null
                        : (array_key_exists('hero_image_alt', $validated)
                            ? $this->nullableText($validated['hero_image_alt'])
                            : $district->hero_image_alt),
                    'hero_image_alt_bn' => $heroImage === null
                        ? null
                        : (array_key_exists('hero_image_alt_bn', $validated)
                            ? $this->nullableText($validated['hero_image_alt_bn'])
                            : $district->hero_image_alt_bn),
                ]);
            });
            $committed = true;

            if ((string) $oldImage !== (string) $heroImage) {
                $this->media->deleteLegacyFlatImages('districts', $oldImage);
            }


            $notification = array(
                'message' => $request->Lang->Common->Form->UpdatedSuccessfully,
                'alert-type' => 'success'
            );
            return back()->with($notification);
        } catch (Throwable $e) {
            if (!$committed && $staged) {
                $this->media->discardMany([$staged]);
            }
            $notification = array(
                'message' => $request->Lang->Common->Form->NotUpdate,
                'alert-type' => 'error'
            );
            return back()->with($notification);
        }
    }

    public function status(Request $request, int $id) {
        try {
            $data = District::find($id);
            if ($data === null) {
                return response(['message' => $request->Lang->Common->Form->DataNotFound], 404);
            }
            if (!(bool) $data->status
                && !Division::whereKey($data->division_id)->where('status', 1)->exists()) {
                return response(['message' => 'Publish the parent division before publishing this district.'], 409);
            }

            if ((bool) $data->status) {
                $dependencies = [
                    'published_team_members' => LatestNews::query()
                        ->where('type', 'our-members')
                        ->where('district_id', $data->id)
                        ->where('status', 1)
                        ->count(),
                    'published_activities' => NoticeBoard::query()
                        ->where('district_id', $data->id)
                        ->where('status', 1)
                        ->count(),
                ];
                if (array_sum($dependencies) > 0) {
                    return response([
                        'message' => 'Unpublish or reassign this district’s team profiles and activities first.',
                        'dependencies' => $dependencies,
                    ], 409);
                }
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
            $district = District::find($id);
            if ($district === null) {
                return response(['message' => $request->Lang->Common->Form->DataNotFound], 404);
            }

            $dependencies = [
                'upazilas' => Upazila::where('district_id', $district->id)->count(),
                'users' => User::where('district_id', $district->id)->count(),
                'team_members' => LatestNews::where('type', 'our-members')->where('district_id', $district->id)->count(),
                'publications' => NoticeBoard::where('district_id', $district->id)->count(),
            ];
            if (array_sum($dependencies) > 0) {
                return response([
                    'message' => 'This district cannot be deleted while upazilas, registrations, team members, or activities still reference it.',
                    'dependencies' => $dependencies,
                ], 409);
            }

            $oldImage = $district->hero_image;
            $district->delete();
            $this->media->deleteLegacyFlatImages('districts', $oldImage);
            return response(['message' => $request->Lang->Common->Form->DeleteSuccessfully], 200);
        } catch (Throwable $e) {
            return response(['message' => $request->Lang->Common->Form->NotDelete], 403);
        }
    }

    /** @return array<string, mixed> */
    private function rules(Request $request, ?District $district = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('districts', 'name')
                    ->where('division_id', $request->input('division_id'))
                    ->ignore($district?->id),
                function (string $attribute, mixed $value, \Closure $fail) use ($district): void {
                    if ($district) {
                        return;
                    }
                    $slug = SupportStr::slug(trim((string) $value));
                    if ($slug === '') {
                        $fail('Enter a district name that can create a readable website address.');

                        return;
                    }
                    if (!$district && District::query()->where('slug', $slug)->exists()) {
                        $fail('Another district already uses this website address.');
                    }
                },
            ],
            'division_id' => [
                'required',
                'integer',
                Rule::exists('divisions', 'id')->where(fn ($query) => $query->where('status', 1)),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'description_bn' => ['nullable', 'string', 'max:5000'],
            'hero_image_media_uuid' => [
                'nullable',
                'string',
                'max:36',
                Rule::exists('media_assets', 'uuid')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->where('disk', 'public')
                    ->where('mime_type', 'like', 'image/%')),
            ],
            'hero_image_upload' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp', 'max:3000'],
            'hero_image_alt' => ['nullable', 'string', 'max:255'],
            'hero_image_alt_bn' => ['nullable', 'string', 'max:255'],
            'remove_hero_image' => ['nullable', 'boolean'],
        ];
    }

    /** @param array<string, mixed> $validated */
    private function validateImageIntent(Request $request, array $validated): void
    {
        $hasUpload = $request->hasFile('hero_image_upload');
        $hasLibraryImage = !empty($validated['hero_image_media_uuid']);
        $remove = $request->boolean('remove_hero_image');
        $errors = [];

        if ($hasUpload && $hasLibraryImage) {
            $errors['hero_image_upload'] = 'Choose either an uploaded file or a Media Library image, not both.';
        }
        if ($remove && ($hasUpload || $hasLibraryImage)) {
            $errors['remove_hero_image'] = 'Remove the current image or choose a replacement, not both.';
        }
        if (($hasUpload || $hasLibraryImage) && blank($validated['hero_image_alt'] ?? null)) {
            $errors['hero_image_alt'] = 'Describe the image for visitors who cannot see it.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function selectedMediaImage(mixed $uuid): ?string
    {
        $uuid = trim((string) $uuid);
        if ($uuid === '') {
            return null;
        }

        return MediaAsset::query()
            ->where('uuid', $uuid)
            ->where('disk', 'public')
            ->where('mime_type', 'like', 'image/%')
            ->firstOrFail()
            ->url;
    }

    private function selectedMediaUuid(mixed $image): ?string
    {
        $image = trim((string) $image);
        if ($image === '') {
            return null;
        }

        return MediaAsset::withTrashed()
            ->where('disk', 'public')
            ->where('mime_type', 'like', 'image/%')
            ->get()
            ->first(fn (MediaAsset $asset): bool => hash_equals($asset->url, $image))
            ?->uuid;
    }

    private function districtHeroUrl(mixed $image): string
    {
        $image = trim((string) $image);
        if ($image === '') {
            return '';
        }
        if (str_starts_with($image, '/') || preg_match('#\Ahttps?://#i', $image)) {
            return $image;
        }

        return asset('storage/photos/1/districts/' . rawurlencode(basename($image)));
    }

    private function imageAssets()
    {
        return MediaAsset::query()
            ->where('disk', 'public')
            ->where('mime_type', 'like', 'image/%')
            ->latest()
            ->limit(150)
            ->get();
    }

    private function nullableNarrative(mixed $value): ?string
    {
        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace("/\r\n?/", "\n", $value) ?? '';

        return $this->nullableText($value);
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

}
