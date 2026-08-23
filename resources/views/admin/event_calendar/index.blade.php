@extends('admin.layouts.master')

@section('content')
@php
    $canCreate = app(\App\Http\Middleware\Permission::class)->allows(auth('admin')->user(), 'event_calendar.store');
    $canEdit = app(\App\Http\Middleware\Permission::class)->allows(auth('admin')->user(), 'event_calendar.update');
@endphp
<div class="content pb-0">
    <h1 class="sr-only">{{ $title }}</h1>

    <div class="row">
        @if($canCreate)
        <div class="col-lg-5 col-md-12">
            <div id="new_event_calendar">
                <div class="card">
                    <div class="card-header">
                        <strong class="card-title">{{ $Lang->Common->New }} {{ $Lang->EventCalendarTitle }}</strong>
                    </div>
                    <div class="card-body">
                        <div id="pay-invoice">
                            <div class="card-body">
                                <form action="{{ route('event_calendar.store') }}" method="post" enctype="multipart/form-data">
                                    {{ csrf_field() }}

                                    <div class="form-group has-success">
                                        <label for="event-title" class="control-label mb-1">{{ $Lang->Common->Form->Title }} <span>*</span></label>
                                        <input id="event-title" name="title" type="text" value="{{ old('title') }}" class="form-control" required>
                                        @if ($errors->has('title'))
                                        <small class="help-block form-text text-danger">{{ $errors->first('title') }}</small>
                                        @endif
                                    </div>

                                    <div class="form-group has-success">
                                        <label for="event-description">{{ $Lang->Common->Form->Description }}</label>
                                        <textarea id="event-description" class="form-control form-control-danger" name="description" rows="4">{{ old('description') }}</textarea>
                                        @if ($errors->has('description'))
                                        <small class="help-block form-text text-danger">{{ $errors->first('description') }}</small>
                                        @endif
                                    </div>

                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-group has-success">
                                                <label for="start_date" class="control-label mb-1">{{ $Lang->Common->Form->StartDate }}</label>
                                                <input id="start_date" name="start_date" type="datetime-local" value="{{ old('start_date') }}" class="form-control" required>
                                                @if ($errors->has('start_date'))
                                                <small class="help-block form-text text-danger">{{ $errors->first('start_date') }}</small>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-group has-success">
                                                <label for="end_date" class="control-label mb-1">{{ $Lang->Common->Form->EndDate }}</label>
                                                <input id="end_date" name="end_date" type="datetime-local" value="{{ old('end_date') }}" class="form-control" required>
                                                @if ($errors->has('end_date'))
                                                <small class="help-block form-text text-danger">{{ $errors->first('end_date') }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-group has-success">
                                                <label for="color" class="control-label mb-1">{{ $Lang->Common->Form->Color }}</label>
                                                <input id="color" name="color" type="color" value="{{ old('color') ? old('color') : '#1976d2' }}" class="form-control">
                                                @if ($errors->has('color'))
                                                <small class="help-block form-text text-danger">{{ $errors->first('color') }}</small>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-group has-success">
                                                <label for="text_color" class="control-label mb-1">{{ $Lang->Common->Form->Text }} {{ $Lang->Common->Form->Color }}</label>
                                                <input id="text_color" name="textColor" type="color" value="{{ old('textColor') ? old('textColor') : '#ffffff' }}" class="form-control">
                                                @if ($errors->has('textColor'))
                                                <small class="help-block form-text text-danger">{{ $errors->first('textColor') }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group has-success">
                                        <label for="url">Url</label>
                                        <input id="url" class="form-control" name="url" value="{{ old('url') }}">
                                        @if ($errors->has('url'))
                                        <small class="help-block form-text text-danger">{{ $errors->first('url') }}</small>
                                        @endif
                                    </div>

                                    <div class="form-actions form-group text-right">
                                        <button type="submit" class="btn igf-btn igf-btn-primary submit_ mt-3"><i class="fa fa-plus" aria-hidden="true"></i> Create calendar event</button>
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
                            <strong class="card-title">{{ $Lang->EventCalendarTitle }} {{ $Lang->Common->List }}</strong>
                        </div>
                        <div class="col-md-6">
                            <form action="{{ route('event_calendar.index') }}" method="get">
                                <div class="input-group search-input-group">
                                    <label class="sr-only" for="event-calendar-search">Search calendar events</label>
                                    <input id="event-calendar-search" type="search" name="search" value="{{ @$search }}" class="form-control search-form-control" aria-label="Search calendar events">
                                    <span class="input-group-prepend">
                                        <button type="submit" class="btn igf-btn igf-btn-secondary igf-btn-compact"><i class="fa fa-search" aria-hidden="true"></i> {{ $Lang->Common->Search }}</button>
                                    </span>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="table-stats ov-h">
                    <table class="table" id="event_calendar_table">
                        <thead>
                            <tr>
                                <th width="10%" class="serial"><strong>#{{ $Lang->Common->Form->ID }} </strong></th>
                                <th width="35%"><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                                <th width="15%"><strong>{{ $Lang->Common->Form->StartDate }}</strong></th>
                                <th width="15%"><strong>{{ $Lang->Common->Form->EndDate }}</strong></th>
                                <th width="25%"><strong>{{ $Lang->Common->Form->Action }}</strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($event_calendars as $event_calendar)
                            <tr id="{{ @$event_calendar->id }}">
                                <td> #{{ @$event_calendar->id }} </td>
                                <td>
                                    <span class="name">{{ @$event_calendar->title }}</span>
                                </td>
                                <td>
                                    {{ @$event_calendar->start_date }}
                                </td>
                                <td>
                                    {{ @$event_calendar->end_date }}
                                </td>
                                <td>
                                    <?= App\Link::action(@$event_calendar->id, @$event_calendar->status, 'event ' . ($event_calendar->title ?? '')) ?>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="pagination justify-content-end">
                        {{ $event_calendars->appends(['search' => $search])->links('vendor.pagination.bootstrap-4') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal --}}
@if($canEdit)
<div class="modal fade" id="eventCalendarModal" tabindex="-1" role="dialog" data-backdrop="static" aria-labelledby="eventCalendarModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <form action="{{ route('event_calendar.update') }}" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h2 class="card-title h5 mb-0" id="eventCalendarModalTitle">{{ $Lang->Common->Edit }} {{ $Lang->EventCalendarTitle }}</h2>
                    <button type="button" class="close cancel btn igf-btn igf-btn-tertiary" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    {{ csrf_field() }}
                    @method('PUT')
                    <input name="id" id="e_id" type="hidden" value="{{ old('id') }}" class="form-control" required>

                    <div class="form-group has-success">
                        <label for="edit-event-title" class="control-label mb-1">{{ $Lang->Common->Form->Title }} <span>*</span></label>
                        <input id="edit-event-title" name="title" type="text" value="{{ old('title') }}" class="form-control" required>
                        @if ($errors->has('title'))
                        <small class="help-block form-text text-danger">{{ $errors->first('title') }}</small>
                        @endif
                    </div>

                    <div class="form-group has-success">
                        <label for="edit-event-description">{{ $Lang->Common->Form->Description }}</label>
                        <textarea id="edit-event-description" class="form-control form-control-danger" name="description" rows="4">{{ old('description') }}</textarea>
                        @if ($errors->has('description'))
                        <small class="help-block form-text text-danger">{{ $errors->first('description') }}</small>
                        @endif
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-group has-success">
                                <label for="edit-start-date" class="control-label mb-1">{{ $Lang->Common->Form->StartDate }}</label>
                                <input id="edit-start-date" name="start_date" type="datetime-local" value="{{ old('start_date') }}" class="form-control" required>
                                @if ($errors->has('start_date'))
                                <small class="help-block form-text text-danger">{{ $errors->first('start_date') }}</small>
                                @endif
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group has-success">
                                <label for="edit-end-date" class="control-label mb-1">{{ $Lang->Common->Form->EndDate }}</label>
                                <input id="edit-end-date" name="end_date" type="datetime-local" value="{{ old('end_date') }}" class="form-control" required>
                                @if ($errors->has('end_date'))
                                <small class="help-block form-text text-danger">{{ $errors->first('end_date') }}</small>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-group has-success">
                                <label for="edit-color" class="control-label mb-1">{{ $Lang->Common->Form->Color }}</label>
                                <input id="edit-color" name="color" type="color" value="{{ old('color') }}" class="form-control">
                                @if ($errors->has('color'))
                                <small class="help-block form-text text-danger">{{ $errors->first('color') }}</small>
                                @endif
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group has-success">
                                <label for="edit-text-color" class="control-label mb-1">{{ $Lang->Common->Form->Text }} {{ $Lang->Common->Form->Color }}</label>
                                <input id="edit-text-color" name="textColor" type="color" value="{{ old('textColor') }}" class="form-control">
                                @if ($errors->has('textColor'))
                                <small class="help-block form-text text-danger">{{ $errors->first('textColor') }}</small>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="form-group has-success">
                        <label for="edit-event-url">URL</label>
                        <input id="edit-event-url" class="form-control" name="url" value="{{ old('url') }}">
                        @if ($errors->has('url'))
                        <small class="help-block form-text text-danger">{{ $errors->first('url') }}</small>
                        @endif
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn igf-btn igf-btn-primary submit_ mt-3"><i class="fa fa-save" aria-hidden="true"></i> Save calendar event</button>
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
    itemDelete({
        tableId: "event_calendar_table",
        method: "DELETE"
    });
    itemStatus({
        tableId: "event_calendar_table",
        method: "PUT"
    });

    $(".cancel").click(function() {
        clear($(this).closest("form"));
    });

    var is_edit = "{{ old('id') }}";
    if (is_edit) {
        $('#new_event_calendar .form-group .help-block').hide();
        $("#new_event_calendar input:not([type=hidden])").val("");
        $('#eventCalendarModal').modal('show');
    }

    function clear($form) {
        if ($form.length) {
            $form.get(0).reset();
            $form.find(".chosen-select").trigger("chosen:updated");
        }
    }
    $(".edit").click(function() {
        $('#eventCalendarModal').modal('show');
        $('.form-group .help-block').hide();
        var spinner = $('.spinner');
        spinner.show();
        var id = $(this).data('id');

        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            type: 'get',
            url: "{{ route('event_calendar.index') }}/" + id + "/edit",
            success: function(res) {
                if (res.data) {
                    $('.modal #e_id').val(res.data.id);
                    $(".modal input[name=title]").val(res.data.title);
                    $(".modal textarea[name=description]").val(res.data.description);
                    $(".modal input[name=start_date]").val(res.data.start_date);
                    $(".modal input[name=end_date]").val(res.data.end_date);
                    $(".modal input[name=color]").val(res.data.color);
                    $(".modal input[name=textColor]").val(res.data.textColor);
                    $(".modal input[name=url]").val(res.data.url);
                }
                spinner.hide();
            },
            error: function(err) {
                toastrMsg('error', err.responseJSON.message);
                spinner.hide();
            }
        });

    });
</script>
@endsection
