@extends('admin.layouts.master')

@section('content')
<div class="content pb-0">

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-8">
                            <strong class="card-title">{{ $Lang->Common->User }} {{ $Lang->Common->Form->Details }}</strong>
                        </div>
                        <div class="col-md-4">
                            <b>{{ $Lang->Resource->Point }}</b> : {{@$user->points}}
                        </div>
                    </div>
                </div>
                <div class="table-stats ov-h">
                    <table class="table" id="submitwork_table">
                        <thead>
                            <tr>
                                <th width="20%"><strong></strong></th>
                                <th width="20%"><strong></strong></th>
                                <th width="20%"><strong></strong></th>
                                <th width="40%"><strong></strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td> {{ $Lang->Common->Form->Name }} : </td>
                                <td> <span class="">{{@$user->name}}</span> </td>

                                <td>{{ $Lang->Common->Form->Mobile }}/{{ $Lang->Common->Form->Email }}  : </td>
                                <td> <span class="">{{@$user->phone_no}}</span> </td>
                            </tr>

                            <tr>
                                <td>Gender : </td>
                                <td> <span class="">{{@$user->gender}}</span> </td>
                                <td>DBO : </td>
                                <td> <span class="">{{ date('F j, Y',strtotime(@$user->dob))}}</span> </td>
                            </tr>

                            <tr>
                                <td>Study {{ $Lang->Common->Form->Type }} : </td>
                                <td> <span class="">{{@$user->study_type}}</span> </td>
                                <td>Institute {{ $Lang->Common->Form->Name }} : </td>
                                <td> <span class="">{{@$user->institute_name}}</span> </td>
                            </tr>

                            <tr>
                                <td>{{ $Lang->DivisionTitle }} {{ $Lang->Common->Form->Name }} : </td>
                                <td> <span class="">{{@$user->division_name}}</span> </td>
                                <td>{{ $Lang->DistrictTitle }} {{ $Lang->Common->Form->Name }} : </td>
                                <td> <span class="">{{@$user->district_name}}</span> </td>
                            </tr>

                            <tr>
                                <td>{{ $Lang->UpazilaTitle }} {{ $Lang->Common->Form->Name }} : </td>
                                <td> <span class="">{{@$user->upazila_name}}</span> </td>
                                <td> {{ $Lang->Common->Form->Address }} : </td>
                                <td> <span class="">{{@$user->address}}</span> </td>
                            </tr>

                            <tr>
                                <td>{{ $Lang->Common->Form->Provider }} : </td>
                                <td> <span class="">{{@$user->provider_type}}</span> </td>
                                <td></td>
                                <td>
                                    <img class="img-fluid" src="{{ $user->avatarUrl() ?: asset('image/no-image.png') }}" alt="{{ $user->name }}">
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
