<?php

namespace App\Http\Controllers\Api;

use App\Helper\MyLogs;
use App\Helper\MyYoutube;
use App\Http\Controllers\Controller;
use App\Models\YouTube;
use App\Models\YouTubeWatch;
use App\Support\RequestFingerprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class YouTubeController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response(['status' => false, 'message' => implode(',', $validator->errors()->all())], 422);
        }

        try {
            $search = trim((string) $request->input('search', ''));
            $perPage = (int) $request->input('per_page', 50);
            $locale = (string) data_get($request, 'share.locale', app()->getLocale());
            $youtubes = YouTube::select('id', 'name', 'video_id', 'duration_time', 'activision_time', 'image', 'title', 'description', 'published_at')
                ->when($search !== '', fn ($query) => $query->where('name', 'like', '%' . $search . '%'))
                ->where('status', 1)
                ->where('language', $locale)
                ->orderBy('order_by', 'asc')
                ->paginate($perPage);

            $existingIds = MyYoutube::existingVideoIds(
                collect($youtubes->items())->pluck('video_id')->all()
            );
            $userId = (int) $request->user()->getAuthIdentifier();
            $items = collect($youtubes->items())
                ->when(
                    $existingIds !== null,
                    fn ($items) => $items->filter(fn (YouTube $video) => in_array($video->video_id, $existingIds, true))
                )
                ->values()
                ->map(function (YouTube $video) use ($userId): YouTube {
                    $video->setAttribute('watch_token', Crypt::encryptString(json_encode([
                        'user_id' => $userId,
                        'video_id' => $video->video_id,
                        'issued_at' => now()->timestamp,
                    ], JSON_THROW_ON_ERROR)));

                    return $video;
                });

            return response([
                'status' => true,
                'page_no' => $youtubes->currentPage(),
                'data' => $items,
                'total' => $youtubes->total(),
                'provider_verified' => $existingIds !== null,
            ], 200);
        } catch (Throwable $e) {
            report($e);
            return response(['status' => false, 'message' => 'Video content is temporarily unavailable.'], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'video_id' => ['required', 'string', 'max:30', 'regex:/\A[A-Za-z0-9_-]{6,20}\z/'],
            'duration_time' => ['required', 'numeric', 'min:0', 'max:1440'],
            'watch_token' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response(['status' => false, 'message' => implode(',', $validator->errors()->all())], 422);
        }

        try {
            MyLogs::front($request, 'Youtube Meta');

            $userId = (int) $request->user()->getAuthIdentifier();
            $locale = (string) data_get($request, 'share.locale', app()->getLocale());
            $youtube = YouTube::query()
                ->where('video_id', $request->video_id)
                ->where('language', $locale)
                ->where('status', 1)
                ->first();

            if ($youtube === null) {
                return response(['status' => false, 'message' => 'Video not found.'], 404);
            }

            $submittedMinutes = (float) $request->duration_time;
            $activationMinutes = max(0.0, (float) $youtube->activision_time);
            $videoMinutes = max($activationMinutes, (float) $youtube->duration_time);
            $verifiedElapsedMinutes = $this->verifiedElapsedMinutes(
                (string) $request->input('watch_token', ''),
                $userId,
                (string) $youtube->video_id
            );
            $acceptedMinutes = min(
                $submittedMinutes,
                $videoMinutes > 0 ? $videoMinutes : $submittedMinutes,
                $verifiedElapsedMinutes ?? 0.0
            );
            $completed = $activationMinutes > 0
                && $verifiedElapsedMinutes !== null
                && $submittedMinutes >= $activationMinutes
                && $verifiedElapsedMinutes >= max(0, $activationMinutes - (5 / 60));

            DB::transaction(function () use ($userId, $youtube, $acceptedMinutes, $completed, $request): void {
                $watch = YouTubeWatch::query()
                    ->where('user_id', $userId)
                    ->where('video_id', $youtube->video_id)
                    ->lockForUpdate()
                    ->first() ?? new YouTubeWatch([
                        'user_id' => $userId,
                        'video_id' => $youtube->video_id,
                    ]);

                $watch->duration_time = max((float) ($watch->duration_time ?? 0), $acceptedMinutes);
                $watch->status = (bool) $watch->status || $completed;
                $watch->ip = RequestFingerprint::for($request);
                $watch->save();
            });

            $completedVideoCount = YouTubeWatch::query()
                ->where('user_id', $userId)
                ->where('status', 1)
                ->distinct('video_id')
                ->count('video_id');

            return response([
                'status' => true,
                'data' => [
                    'submitworkisactive' => $completedVideoCount >= 3,
                    'watch_verified' => $verifiedElapsedMinutes !== null,
                    'completed' => $completed,
                    'accepted_duration_time' => round($acceptedMinutes, 2),
                ],
                'message' => $verifiedElapsedMinutes === null
                    ? 'Progress saved but cannot complete without a valid watch token.'
                    : 'Video progress saved successfully.',
            ], 200);
        } catch (Throwable $e) {
            report($e);
            return response(['status' => false, 'message' => 'Video progress could not be saved.'], 500);
        }
    }

    private function verifiedElapsedMinutes(string $token, int $userId, string $videoId): ?float
    {
        if ($token === '') {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, 8, JSON_THROW_ON_ERROR);
            $issuedAt = (int) ($payload['issued_at'] ?? 0);
            $matches = (int) ($payload['user_id'] ?? 0) === $userId
                && hash_equals((string) ($payload['video_id'] ?? ''), $videoId)
                && $issuedAt <= now()->timestamp + 5
                && $issuedAt >= now()->subDay()->timestamp;

            return $matches ? max(0, (now()->timestamp - $issuedAt) / 60) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
