<?php

namespace App\Http\Controllers;

use App\Helper\MyLogs;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Page;
use App\Support\RequestFingerprint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CommentController extends Controller
{
    public function like(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'comment_id' => ['required', 'integer'],
            'liked' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response(['status' => false, 'message' => implode(',', $validator->errors()->all())], 422);
        }

        $comment = Comment::query()
            ->whereKey($request->integer('comment_id'))
            ->where('status', 1)
            ->where(fn (Builder $query) => $query->whereNull('is_delete')->orWhere('is_delete', '!=', 1))
            ->whereHas('page', fn (Builder $query) => $query->publiclyAvailable()->where('is_comment', 1))
            ->first();
        if ($comment === null) {
            return response(['status' => false, 'message' => 'Comment not found.'], 404);
        }

        try {
            MyLogs::front($request, 'Page Like');
            $userId = $request->user()?->getAuthIdentifier();
            $fingerprint = RequestFingerprint::for($request);
            $liked = DB::transaction(function () use ($request, $comment, $userId, $fingerprint): bool {
                Comment::query()->whereKey($comment->id)->lockForUpdate()->firstOrFail();
                $identity = Like::query()->where('comment_id', $comment->id);
                if ($userId !== null) {
                    $identity->where('user_id', $userId);
                } else {
                    $identity->whereNull('user_id')->where('ip', $fingerprint);
                }

                $existing = (clone $identity)->lockForUpdate()->first();
                $desiredState = $request->has('liked')
                    ? $request->boolean('liked')
                    : $existing === null;

                if ($desiredState && $existing === null) {
                    Like::create([
                        'comment_id' => $comment->id,
                        'user_id' => $userId,
                        'status' => 1,
                        'ip' => $fingerprint,
                    ]);
                } elseif (!$desiredState && $existing !== null) {
                    $identity->delete();
                } elseif ($desiredState && $existing !== null && !(bool) $existing->status) {
                    $existing->update(['status' => 1]);
                }

                return $desiredState;
            });

            $totalLikes = Like::query()
                ->where('comment_id', $comment->id)
                ->where('status', 1)
                ->count();

            return response([
                'status' => true,
                'message' => $liked ? 'Liked successfully.' : 'Unliked successfully.',
                'liked' => $liked,
                'total_like' => $totalLikes,
            ], 200);
        } catch (Throwable $e) {
            report($e);
            return response(['status' => false, 'message' => 'The like could not be saved.'], 500);
        }
    }

    public function comment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page_id' => ['required', 'integer'],
            'text' => ['required', 'string', 'max:2000'],
            'name' => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response(['status' => false, 'message' => implode(',', $validator->errors()->all())], 422);
        }

        $page = Page::query()
            ->publiclyAvailable()
            ->whereKey($request->integer('page_id'))
            ->where('is_comment', 1)
            ->first();
        if ($page === null) {
            return response(['status' => false, 'message' => 'Comments are not available for this page.'], 404);
        }

        try {
            MyLogs::front($request, 'Page comment');
            $user = $request->user();
            $userId = $user?->getAuthIdentifier();
            $text = trim((string) $request->text);
            $fingerprint = RequestFingerprint::for($request);
            $created = false;

            $comment = DB::transaction(function () use ($request, $page, $user, $userId, $text, $fingerprint, &$created): Comment {
                Page::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();
                $duplicate = Comment::query()
                    ->where('page_id', $page->id)
                    ->where('text', $text)
                    ->where('created_at', '>=', now()->subMinute())
                    ->when(
                        $userId !== null,
                        fn (Builder $query) => $query->where('user_id', $userId),
                        fn (Builder $query) => $query->whereNull('user_id')->where('ip', $fingerprint)
                    )
                    ->lockForUpdate()
                    ->first();

                if ($duplicate !== null) {
                    return $duplicate;
                }

                $created = true;
                return Comment::create([
                    'page_id' => $page->id,
                    'text' => $text,
                    'name' => $user?->name ?: trim((string) $request->name) ?: null,
                    'user_id' => $userId,
                    'status' => 0,
                    'is_delete' => 0,
                    'ip' => $fingerprint,
                ]);
            });

            $comments = Comment::query()
                ->select('id', 'text', 'name', 'page_id', 'user_id', 'updated_at')
                ->withCount(['likes as total_like'])
                ->where('status', 1)
                ->where(fn (Builder $query) => $query->whereNull('is_delete')->orWhere('is_delete', '!=', 1))
                ->where('page_id', $page->id)
                ->orderByDesc('id')
                ->paginate(3);

            $items = collect($comments->items())->map(static function (Comment $item): array {
                return [
                    'id' => $item->id,
                    'text' => $item->text,
                    'name' => $item->name,
                    'page_id' => $item->page_id,
                    'user_id' => $item->user_id,
                    'total_like' => $item->total_like,
                    'date_at' => optional($item->updated_at)->format('m-d-Y'),
                ];
            })->all();

            return response([
                'status' => true,
                'message' => $created ? 'Comment submitted for moderation.' : 'Comment already submitted.',
                'created' => $created,
                'comment_id' => $comment->id,
                'total_comment' => $comments->total(),
                'text' => $comment->text,
                'data' => $items,
            ], 200);
        } catch (Throwable $e) {
            report($e);
            return response(['status' => false, 'message' => 'The comment could not be submitted.'], 500);
        }
    }
}
