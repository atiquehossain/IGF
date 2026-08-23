@extends('admin.layouts.master')

@section('content')
@php
    $canCreate = app(\App\Http\Middleware\Permission::class)->allows(auth('admin')->user(), 'district.store');
    $canEdit = app(\App\Http\Middleware\Permission::class)->allows(auth('admin')->user(), 'district.update');
@endphp
<div class="content pb-0">
    <h1 class="sr-only">{{ $title }}</h1>

    <div class="row">
        @if($canCreate)
        <div class="col-lg-5 col-md-12">
            <div id="new_district">
                <div class="card">
                    <div class="card-header">
                        <strong class="card-title">{{ $Lang->Common->New }} {{ $Lang->DistrictTitle }}</strong>
                    </div>
                    <div class="card-body">
                        <div id="pay-invoice">
                            <div class="card-body">
                                <form action="{{route('district.store')}}" method="post" enctype="multipart/form-data">
                                    {{ csrf_field() }}

                                    <div class="form-group has-success">
                                        <label for="division_id" class="control-label mb-1">{{ $Lang->DivisionTitle }}</label>
                                        <select id="division_id" name="division_id" class="form-control">
                                            <option value="">{{ $Lang->Common->PleaseSelect }} </option>
                                            @foreach($divisions as $division)
                                            <option value="{{$division->id}}" {{ (old('division_id') == $division->id) ? "selected":"" }}>{{$division->name}}</option>
                                            @endforeach
                                        </select>
                                        @if($errors->has('division_id'))
                                        <small class="help-block form-text text-danger">{{ $errors->first('division_id') }}</small>
                                        @endif
                                    </div>

                                    <div class="form-group has-success">
                                        <label for="name" class="control-label mb-1">{{ $Lang->Common->Form->Name }} <span>*</span></label>
                                        <input id="name" name="name" type="text" value="{{old('name')}}" class="form-control" required>
                                        @if($errors->has('name'))
                                        <small class="help-block form-text text-danger">{{ $errors->first('name') }}</small>
                                        @endif
                                    </div>

                                    <div class="form-actions form-group text-right">
                                        <button type="submit" class="btn igf-btn igf-btn-primary submit_ mt-3"><i class="fa fa-plus" aria-hidden="true"></i> Create district</button>
                                        <button type="button" class="btn igf-btn igf-btn-secondary cancel mt-3"><i class="fa fa-times" aria-hidden="true"></i>&nbsp;{{ $Lang->Common->Cancel }}</button>
                                    </div>

                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        @endif
        <div class="{{ $canCreate ? 'col-lg-7' : 'col-lg-12' }} col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <strong class="card-title">{{ $Lang->DistrictTitle }} {{ $Lang->Common->List }}</strong>
                        </div>
                        <div class="col-md-6">
                            <form action="{{ route('district.index')}}" method="get">
                                <div class="input-group search-input-group">
                                    <label class="sr-only" for="district-search">Search districts</label>
                                    <input id="district-search" type="search" name="search" value="{{@$search}}" class="form-control search-form-control" aria-label="Search districts">
                                    <span class="input-group-prepend">
                                        <button type="submit" class="btn igf-btn igf-btn-secondary igf-btn-compact"><i class="fa fa-search" aria-hidden="true"></i> {{ $Lang->Common->Search }}</button>
                                    </span>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="table-stats ov-h">
                    <table class="table" id="district_table">
                        <thead>
                            <tr>
                                <th width="10%" class="serial"><strong>#{{ $Lang->Common->Form->ID }} </strong></th>
                                <th width="35%"><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                                <th width="30%"><strong>{{ $Lang->DivisionTitle }}</strong></th>
                                <th width="25%"><strong>{{ $Lang->Common->Form->Action }}</strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($districts as $district)
                            <tr id="{{ @$district->id }}">
                                <td> #{{@$district->id}} </td>
                                <td> <span class="name">{{@$district->name}}</span> </td>
                                <td> <span class="name">{{@$district->division->name}}</span> </td>
                                <td>
                                    <?=App\Link::action(@$district->id, @$district->status, 'district ' . ($district->name ?? '')) ?>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="pagination justify-content-end">
                        {{ $districts->appends(['search' => $search])->links('vendor.pagination.bootstrap-4') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal --}}
@if($canEdit)
<div class="modal fade" id="districtModal" tabindex="-1" role="dialog" data-backdrop="static" aria-labelledby="districtModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <form action="{{route('district.update')}}" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h2 class="card-title h5 mb-0" id="districtModalTitle">{{ $Lang->Common->Edit }} {{ $Lang->DistrictTitle }}</h2>
                    <button type="button" class="close cancel btn igf-btn igf-btn-tertiary" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    {{ csrf_field() }}
                    @method('PUT')
                    <input name="id" id="e_id" type="hidden" value="{{old('id')}}" class="form-control" required>

                    <div class="form-group has-success">
                        <label for="e_division_id" class="control-label mb-1">{{ $Lang->DivisionTitle }}</label>
                        <select name="division_id" type="text" class="form-control" id="e_division_id">
                            <option value="">{{ $Lang->Common->PleaseSelect }} </option>
                            @foreach($divisions as $division)
                            <option value="{{$division->id}}" {{ (old('division_id') == $division->id) ? "selected":"" }}>{{$division->name}}</option>
                            @endforeach
                        </select>
                        @if($errors->has('division_id'))
                        <small class="help-block form-text text-danger">{{ $errors->first('division_id') }}</small>
                        @endif
                    </div>

                    <div class="form-group has-success">
                        <label for="e_name" class="control-label mb-1">{{ $Lang->Common->Form->Name }}<span>*</span></label>
                        <input id="e_name" name="name" type="text" value="{{old('name')}}" class="form-control" required>
                        @if($errors->has('name'))
                        <small class="help-block form-text text-danger">{{ $errors->first('name') }}</small>
                        @endif
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn igf-btn igf-btn-primary submit_ mt-3"><i class="fa fa-save" aria-hidden="true"></i> Save district</button>
                    <button type="button" class="btn igf-btn igf-btn-secondary cancel mt-3" data-dismiss="modal"><i class="fa fa-times" aria-hidden="true"></i>&nbsp;{{ $Lang->Common->Cancel }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif



@endsection

@section('custom-js')
<script>
    itemDelete({tableId: "district_table",method: "DELETE"});
    itemStatus({tableId: "district_table",method: "PUT"});

    $(".cancel").click(function () {
        clear($(this).closest("form"));
    });

    var is_edit = "{{old('id')}}";
    if (is_edit) {
        $('#new_district .form-group .help-block').hide();
        $("#new_district input:not([type=hidden])").val("");
        $('#districtModal').modal('show');
    }

    function clear($form) {
        if ($form.length) {
            $form.get(0).reset();
            $form.find(".chosen-select").trigger("chosen:updated");
        }
    }
    $(".edit").click(function () {
        $('#districtModal').modal('show');
        $('.form-group .help-block').hide();
        var spinner = $('.spinner');
        spinner.show();
        var id = $(this).data('id');

        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            type: 'get',
            url: "{{ route('district.index')}}/" + id + "/edit",
            success: function (res) {
                if (res.data) {
                    $('.modal #e_id').val(res.data.id);
                    $('.modal #e_name').val(res.data.name);
                    $('.modal #e_division_id').val(res.data.division_id);

                }
                spinner.hide();
            },
            error: function (err) {
                toastrMsg('error', err.responseJSON.message);
                spinner.hide();
            }
        });

    });
</script>

@endsection
