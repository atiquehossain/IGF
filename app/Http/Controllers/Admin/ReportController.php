<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\YouTubeWatch;
use App\Services\AdminPrivateSearch;

class ReportController extends Controller {

    public function __construct(private AdminPrivateSearch $privateSearch)
    {
    }

    public function youTubeMeta(Request $request) {
        if ($request->query->has('q') || $request->query->has('id') || $request->query->has('search')) {
            return redirect()->route('report.youtubeMeta');
        }

        $title = $request->Lang->YoutubeMetaTitle ?? 'YouTube watch report';
        $search = $this->privateSearch->current($request, 'youtube-report');

        $YouTubeWatchs = YouTubeWatch::select('you_tube_watches.*', 'users.name', 'users.phone_no', 'you_tubes.title', 'you_tubes.duration_time as yt_duration_time')
                ->leftJoin('users', 'users.id', '=', 'you_tube_watches.user_id')
                ->leftJoin('you_tubes', 'you_tubes.video_id', '=', 'you_tube_watches.video_id')
                ->when($search !== '', function ($query) use ($search): void {
                    $pattern = '%' . $search . '%';
                    $query->where(function ($fields) use ($pattern): void {
                        $fields->where('users.name', 'like', $pattern)
                            ->orWhere('users.phone_no', 'like', $pattern)
                            ->orWhere('users.email', 'like', $pattern);
                    });
                }, fn ($query) => $query->whereRaw('1 = 0'))
                ->orderBy('you_tube_watches.id', 'desc')
                ->get();
        return view('admin.report.youtubemeta')->with(compact('title', 'YouTubeWatchs', 'search'));
    }

}
