@extends('admin.layouts.master')

@section('content')
<div class="content pb-0">

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-8">
                            <strong class="card-title">YouTube {{ $Lang->Common->Meta }}</strong>
                        </div>
                        <div class="col-md-4">
                            <form action="{{ route('report.youtubeMeta.search')}}" method="post" role="search">@csrf
                                <div class="input-group search-input-group">
                                    <label class="sr-only" for="youtube-member-search">Search by member name, phone or email</label>
                                    <input class="form-control search-form-control" id="youtube-member-search" value="{{ $search }}" placeholder="Member name, phone or email" name="search" maxlength="100" autocomplete="off" type="search" required>
                                    <span class="input-group-prepend">
                                        <button type="submit" class="btn btn-info btn-sm"><i class="fa fa-search" aria-hidden="true"></i> {{ $Lang->Common->Search }}</button>
                                    </span>
                                </div>
                            </form>
                            @if($search !== '')<form action="{{ route('report.youtubeMeta.search.clear') }}" method="post" class="mt-1 text-right">@csrf<button type="submit" class="btn btn-light btn-sm">Clear private search</button></form>@endif
                        </div>
                    </div>
                </div>

                <hr>
                <div class="row p-2">
                    <div class="col-md-5"></div>
                    <div class="col-md-7 d-flex justify-content-between">
                        <div></div>
                        <div>
                            <button type="button" class="btn btn-warning mr-2" onclick="printDiv('youtubeReport')"><i class="fa fa-print mr-1" aria-hidden="true"></i>Print report</button>
                        </div>
                    </div>
                </div>

                <div class="table-stats_order-table"  id="youtubeReport">
                    <table class="table table-bordered" id="youtube_table">
                        <thead>
                            <tr>
                                <th width="20%"><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                                <th width="20%"><strong>{{ $Lang->Common->Form->Mobile }}/{{ $Lang->Common->Form->Email }} </strong></th>
                                <th width="30%"><strong>{{ $Lang->Common->Form->Title }} </strong></th>
                                <th width="15%"><strong>{{ $Lang->Resource->Hour }}/{{ $Lang->Resource->Minute}}</strong></th>
                                <th width="15%"><strong>{{ $Lang->Resource->Watch }} <strong>{{ $Lang->Resource->Hr }}/<strong>{{ $Lang->Resource->Min }}</strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($YouTubeWatchs as $watchs)
                            <tr id="{{ $watchs->id }}">
                                <td>{{@$watchs->name}}</td>
                                <td>{{@$watchs->phone_no}}</td>
                                <td>{{@$watchs->title}}</td>
                                <td>{{@$watchs->yt_duration_time}} <strong>{{ $Lang->Resource->Min }}</td>
                                <td>{{@$watchs->duration_time}} <strong>{{ $Lang->Resource->Min }}</td>
                            </tr>
                            @endforeach
                        </tbody>

                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


@endsection
