<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Division;
use App\Models\District;
use App\Models\Upazila;
use App\Models\Category;
use App\Services\SafeMediaReplacementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Helper\MyLogs;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Throwable;

class UserController extends Controller {
    public function __construct(private SafeMediaReplacementService $media)
    {
    }

    public function index(Request $request) {
        try {
            $user = User::find($request->user()->id);

            $user->avatar = $user->avatarUrl();

            $response = [
                'status' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone_no' => $user->phone_no,
                    'email' => @$user->email,
                    'gender' => @$user->gender,
                    'dob' => @$user->dob,
                    'address' => @$user->address,
                    'study_type' => @$user->study_type,
                    'institute_name' => @$user->institute_name,
                    'division_id' => @$user->division_id,
                    'district_id' => @$user->district_id,
                    'upazila_id' => @$user->upazila_id,
                    'post_code' => @$user->post_code,
                    'avatar' => @$user->avatar,
                ],
            ];
            return response($response, 200);
        } catch (Throwable $e) {
            return response(['status' => false, 'message' => 'user not found'], 422);
        }
    }

    public function store(Request $request) {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
                    'name' => ['required', 'string', 'max:50'],
                    'email' => ['required', 'email', 'max:50', Rule::unique('users', 'email')->ignore($user->id)],
                    'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
                    'dob' => ['required', 'date', 'before_or_equal:today'],
                    'address' => ['nullable', 'string', 'max:2000'],
                    'study_type' => ['required', 'string', 'max:255'],
                    'institute_name' => ['required', 'string', 'max:255'],
                    'division_id' => ['required', 'integer', 'exists:divisions,id'],
                    'district_id' => ['required', 'integer', Rule::exists('districts', 'id')->where(fn ($query) => $query->where('division_id', $request->division_id))],
                    'upazila_id' => ['required', 'integer', Rule::exists('upazilas', 'id')->where(fn ($query) => $query->where('district_id', $request->district_id))],
                    'post_code' => ['nullable', 'string', 'max:15'],
        ]);

        if ($validator->fails()) {
            return response(['status' => false, 'message' => implode(",", $validator->errors()->all())], 422);
        }

        try {
            MyLogs::front($request, 'profile update');
            $data = (array) [
                        'name' => (string) $request->name,
                        'email' => (string) $request->email,
                        'gender' => $request->gender,
                        'dob' => $request->date('dob')->format('Y-m-d'),
                        'address' => $request->address,
                        'study_type' => (string) $request->study_type,
                        'institute_name' => (string) $request->institute_name,
                        'division_id' => (int) $request->division_id,
                        'district_id' => (int) $request->district_id,
                        'upazila_id' => (int) $request->upazila_id,
                        'post_code' => $request->post_code,
            ];

            $user->update($data);

            $user = User::find($request->user()->id);

            $user->avatar = $user->avatarUrl();

            $response = [
                'status' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone_no' => $user->phone_no,
                    'email' => @$user->email,
                    'gender' => @$user->gender,
                    'dob' => @$user->dob,
                    'address' => @$user->address,
                    'study_type' => @$user->study_type,
                    'institute_name' => @$user->institute_name,
                    'division_id' => @$user->division_id,
                    'district_id' => @$user->district_id,
                    'upazila_id' => @$user->upazila_id,
                    'post_code' => @$user->post_code,
                    'avatar' => @$user->avatar,
                ],
            ];
            return response($response, 200);
        } catch (Throwable $e) {
            report($e);
            return response(['status' => false, 'message' => 'The profile could not be updated.'], 422);
        }
    }

    public function pictureUpload(Request $request)
    {
        $asset = null;
        $committed = false;
        try {
            $image = $this->validatedProfileImage($request);
            MyLogs::front($request, 'profile picture update');
            $user = User::findOrFail($request->user()->id);
            $asset = $this->media->stageUserAvatar($user->id, $image['bytes'], $image['mime']);
            $oldAvatar = DB::transaction(function () use ($user, $asset): string {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                $oldAvatar = (string) $lockedUser->avatar;
                $lockedUser->update(['avatar' => $asset->databaseValue]);

                return $oldAvatar;
            });
            $committed = true;
            $this->media->deleteLegacyUserAvatar($oldAvatar);

            $user = User::findOrFail($user->id);

            $user->avatar = $user->avatarUrl();

            $response = [
                'status' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone_no' => $user->phone_no,
                    'email' => @$user->email,
                    'gender' => @$user->gender,
                    'dob' => @$user->dob,
                    'address' => @$user->address,
                    'study_type' => @$user->study_type,
                    'institute_name' => @$user->institute_name,
                    'division_id' => @$user->division_id,
                    'district_id' => @$user->district_id,
                    'upazila_id' => @$user->upazila_id,
                    'post_code' => @$user->post_code,
                    'avatar' => @$user->avatar,
                ],
            ];
            return response($response, 200);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return response([
                'status' => false,
                'message' => $exception->validator->errors()->first('file'),
                'errors' => $exception->validator->errors(),
            ], 422);
        } catch (Throwable $e) {
            if (!$committed && $asset) {
                $this->media->discardMany([$asset]);
            }
            report($e);
            return response(['status' => false, 'message' => 'The profile image could not be uploaded.'], 422);
        }
    }

    /** @return array{bytes: string, mime: string} */
    private function validatedProfileImage(Request $request): array
    {
        $uploaded = $request->file('file');
        $inline = $request->input('file');

        if ($uploaded instanceof UploadedFile) {
            if (is_string($inline) && $inline !== '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'file' => 'Submit one profile image source only.',
                ]);
            }

            Validator::make(['file' => $uploaded], [
                'file' => ['required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
            ])->validate();
            $bytes = file_get_contents($uploaded->getRealPath());
            if ($bytes === false) {
                throw new \RuntimeException('Uploaded image could not be read.');
            }
            $declaredMime = null;
        } else {
            Validator::make(['file' => $inline], [
                'file' => ['required', 'string', 'max:2800000', 'regex:#\Adata:image/(?:jpeg|png|webp);base64,[A-Za-z0-9+/]+={0,2}\z#'],
            ], [
                'file.regex' => 'The file must be an inline JPEG, PNG, or WebP image.',
            ])->validate();

            preg_match('#\Adata:image/(jpeg|png|webp);base64,([A-Za-z0-9+/]+={0,2})\z#', $inline, $matches);
            $declaredMime = 'image/' . $matches[1];
            $bytes = base64_decode($matches[2], true);
            if ($bytes === false) {
                throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'The image data is not valid base64.']);
            }
        }

        if (strlen($bytes) > 2 * 1024 * 1024) {
            throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'The image may not be larger than 2 MB.']);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $size = @getimagesizefromstring($bytes);
        $allowed = ['image/jpeg' => IMAGETYPE_JPEG, 'image/png' => IMAGETYPE_PNG, 'image/webp' => IMAGETYPE_WEBP];
        $normalizedDeclared = $declaredMime === 'image/jpeg' ? 'image/jpeg' : $declaredMime;

        if (!$size || !isset($allowed[$mime]) || $size[2] !== $allowed[$mime]
            || ($normalizedDeclared !== null && $normalizedDeclared !== $mime)
            || $size[0] > 4096 || $size[1] > 4096 || ($size[0] * $size[1]) > 12000000) {
            throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'The file must be a valid, reasonably sized JPEG, PNG, or WebP image.']);
        }

        return ['bytes' => $this->stripTrailingImageData($bytes, $mime), 'mime' => $mime];
    }

    private function stripTrailingImageData(string $bytes, string $mime): string
    {
        $end = match ($mime) {
            'image/jpeg' => (($position = strrpos($bytes, "\xFF\xD9")) === false ? null : $position + 2),
            'image/png' => (($position = strrpos($bytes, "\x00\x00\x00\x00IEND")) === false ? null : $position + 12),
            'image/webp' => strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP'
                ? 8 + unpack('Vlength', substr($bytes, 4, 4))['length']
                : null,
            default => null,
        };

        if ($end === null || $end > strlen($bytes)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'The image container is malformed.']);
        }

        return substr($bytes, 0, $end);
    }

    public function geo(Request $request) {

        try {
            $division = Division::select('id as DivisionCode', 'name as DivisionName')
                            ->where('status', '1')->orderBy('name', 'asc')->get();

            $district = District::select('districts.id as DistrictCode', 'districts.name as DistrictName', 'districts.division_id as DivisionCode')
                            ->join('divisions', 'divisions.id', '=', 'districts.division_id')
                            ->where('districts.status', 1)
                            ->where('divisions.status', 1)
                            ->orderBy('districts.name', 'asc')->get();

            $upazila = Upazila::select('upazilas.id as UpazillaCode', 'upazilas.name as UpazillaName', 'upazilas.district_id as DistrictCode', 'divisions.id as DivisionCode')
                            ->join('districts', 'districts.id', '=', 'upazilas.district_id')
                            ->join('divisions', 'divisions.id', '=', 'districts.division_id')
                            ->where('upazilas.status', 1)
                            ->where('districts.status', 1)
                            ->where('divisions.status', 1)
                            ->orderBy('upazilas.name', 'asc')->get();

            $response = [
                'status' => true,
                'data' => [
                    'division' => $division,
                    'district' => $district,
                    'upazila' => $upazila,
                ],
            ];
            return response($response, 200);
        } catch (Throwable $e) {
            report($e);
            return response(['status' => false, 'message' => 'Geographic data is temporarily unavailable.'], 500);
        }
    }

    public function Category(Request $request) {
        try {
            $category = Category::select('id', 'name')->where('status', '1')->orderBy('name', 'asc')->get();

            $response = [
                'status' => true,
                'data' => $category,
            ];
            return response($response, 200);
        } catch (Throwable $e) {
            return response(['status' => false, 'message' => 'data not found?. Try again.'], 422);
        }
    }

}
