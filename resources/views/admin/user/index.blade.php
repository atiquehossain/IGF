@extends('admin.layouts.master')

@section('content')
<div class="content pb-0">
    <h1 class="sr-only">{{ $title }}</h1>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-8">
                            <strong class="card-title">{{ $Lang->MemberTitle }} {{ $Lang->Common->List }}</strong>
                        </div>
                        <div class="col-md-4">
                            <form action="{{ route('user.search')}}" method="post" role="search">@csrf
                                <div class="input-group search-input-group">
                                    <input type="search" name="search" value="{{@$search}}" maxlength="100" autocomplete="off" required class="form-control search-form-control" aria-label="Search donors">
                                    <span class="input-group-prepend">
                                        <button type="submit" class="btn btn-info btn-sm"><i class="fa fa-search" aria-hidden="true"></i> {{ $Lang->Common->Search }}</button>
                                    </span>
                                </div>
                            </form>
                            @if($search !== '')<form action="{{ route('user.search.clear') }}" method="post" class="mt-1 text-right">@csrf<button type="submit" class="btn btn-light btn-sm">Clear private search</button></form>@endif
                        </div>
                    </div>
                </div>
                <div class="table-stats ov-h">
                    <table class="table" id="user_table">
                        <thead>
                            <tr>
                                <th width="20%"><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                                <th width="20%"><strong>{{ $Lang->Common->Form->Mobile }}/{{ $Lang->Common->Form->Email }} </strong></th>
                                <th width="15%"><strong>{{ $Lang->Resource->Image }}</strong></th>
                                <th width="15%"><strong>{{ $Lang->Common->Form->Gender }}</strong></th>
                                <th width="15%"><strong>{{ $Lang->Common->Form->Provider }}</strong></th>

                                <th width="20%"><strong>{{ $Lang->Common->Form->Action }}</strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                            <tr id="{{ $user->id }}">
                                <td> <span class="">{{@$user->name}}</span> </td>
                                <td> <span class="">{{@$user->phone_no}}</span> </td>
                                <td>
                                    <img class="rounded" src="{{ $user->avatarUrl() ?: asset('image/no-image.png') }}" alt="{{ $user->name }}">
                                </td>
                                <td> <span class="">{{@$user->gender}}</span> </td>
                                <td> <span class="">{{@$user->provider_type}}</span> </td>
                                <td>
                                    <?= App\Link::action(@$user->id, true, 'user ' . ($user->name ?? '')) ?>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>

                    </table>
                    <div class="pagination justify-content-end">
                        {{ $users->links('vendor.pagination.bootstrap-4') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
