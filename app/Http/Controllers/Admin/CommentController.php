<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

use App\Models\Comment;
use App\Services\AdminPrivateSearch;

use Exception;


class CommentController extends Controller {

    public function __construct(private AdminPrivateSearch $privateSearch)
    {
    }

    public function index(Request $request) {
        if ($request->query->has('search')) {
            return redirect()->route('comment.index', $request->only(['order_by', 'status']));
        }

        $title = $request->Lang->Comment ?? 'Comments';

        $search = $this->privateSearch->current($request, 'comments');
        $order_by = (string) $request->query('order_by') === '1' ? '1' : '0';
        $status = in_array((string) $request->query('status'), ['1', '2'], true)
            ? (string) $request->query('status')
            : '';

        $comments = Comment::select('comments.id', 'pages.name', 'comments.text', 'comments.page_id', 'comments.user_id', 'comments.status')
                ->selectRaw('(SELECT count(id) FROM likes where comment_id = comments.id) as total_like')
                ->leftJoin('pages', 'pages.id', '=', 'comments.page_id')
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($fields) use ($search): void {
                        $fields
                            ->where('comments.text', 'like', '%' . $search . '%')
                            ->orWhere('pages.name', 'like', '%' . $search . '%');
                    });
                })
                ->when((string) $status === '1', fn ($query) => $query->where('comments.status', 1))
                ->when((string) $status === '2', fn ($query) => $query->where('comments.status', 0))
                ->where(fn ($query) => $query->whereNull('comments.is_delete')->orWhere('comments.is_delete', '!=', 1))
                ->orderBy('comments.id', $order_by === '1' ? 'ASC' : 'DESC')
                ->paginate(15);

        $canModerate = app(Permission::class)->allows($request->user('admin'), 'comment.publish');

        return view('admin.comment.index')->with(compact('title', 'comments', 'search', 'order_by', 'status', 'canModerate'));
    }

    public function destroy($id = null, Request $request) {
        try {
            $comment = Comment::find($id);
            ;
            $comment->update(['is_delete' => 1]);
            return response(['message' => $request->Lang->Common->Form->DeleteSuccessfully], 200);
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotDelete], 403);
        }
    }

}
