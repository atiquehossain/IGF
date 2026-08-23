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
                            <strong class="card-title">{{ $Lang->Common->SubmitWork }} {{ $Lang->Common->Form->Details }}</strong>
                        </div>
                        <div class="col-md-4">

                        </div>
                    </div>
                </div>
                <div class="table-stats ov-h">
                    <table class="table" id="submitwork_table">
                        <thead>
                            <tr>
                                <th width="50%"><strong>{{ $Lang->Common->Form->Title }}</strong> </th>
                                <th width="50%"><strong>{{ $Lang->Detail }}</strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td> {{ $Lang->Common->Form->Name }} : </td>
                                <td> <span class="">{{@$submitwork->user->name}}</span> </td>
                            </tr>
                            <tr>
                                <td>{{ $Lang->Common->Form->Mobile }}/{{ $Lang->Common->Form->Email }}  : </td>
                                <td> <span class="">{{@$submitwork->user->phone_no}}</span> </td>
                            </tr>

                            <tr>
                                <td>Status : </td>
                                <td> <span class="">{{@$submitwork->status}}</span> </td>
                            </tr>


                            <tr>
                                <td>Image : </td>
                                <td>
                                    <a href="{{@$submitwork->post_url}}" target="_blank" rel="noopener" aria-label="Open submitted work in a new tab">
                                        <img class="img-fluid" src="{{route('submitwork.image',@$submitwork->asset)}}" alt="Submitted work preview">
                                    </a>
                                </td>
                            </tr>

                        </tbody>

                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
