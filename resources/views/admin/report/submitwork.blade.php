@extends('admin.layouts.master')

@section('content')
<div class="content pb-0">

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <strong class="card-title">{{ $Lang->Common->SubmitWork }} {{ $Lang->Common->List }}</strong>
                        </div>
                        <div class="col-md-6">

                        </div>
                    </div>
                </div>
                <div class="card-body_ pl-4 pt-2">
                    <form class="form-horizontal" action="{{ route('report.submitwork') }}" method="get" enctype="multipart/form-data">

                        <div class="row justify-content-sm-start">
                            <div class="col-md-8">
                                <div class="row">
                                    <div class="col-md-5 form-group">
                                        <label for="from_date">{{ $Lang->Common->Form->FromDate }}</label>
                                        <input  type="text" class="form-control datepicker" value="<?=date('d-m-Y', strtotime($fromDate))?>" name="from_date" placeholder="Select Date From">
                                    </div>
                                    <div class="col-md-5 form-group">
                                        <label for="to_date">{{ $Lang->Common->Form->ToDate }}</label>
                                        <input  type="text" class="form-control datepicker" value="<?=date('d-m-Y', strtotime($toDate))?>" name="to_date">
                                    </div>
                                    <div class="col-md-2 form-group">
                                        <label for="to_date">{{ $Lang->Common->Form->Is }} {{ $Lang->Common->Form->Details }}</label><br>
                                        <input type="checkbox"
                                            <?php if(!empty($is_details)) { echo 'checked';} ?>
                                         class="form-control_checked" name="is_details" value="1">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="col-md-2 form-group">
                                    <label for="">&nbsp;</label>
                                    <button type="submit" class="btn btn-info"><i class="fa fa-search"></i> {{ $Lang->Common->Search }}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <hr>
                <div class="row p-2">
                    <div class="col-md-5"></div>
                    <div class="col-md-7 d-flex justify-content-between">
                        <div></div>
                        <div>
                            <button type="button" class="btn btn-warning mr-2" onclick="printDiv('submitworkReport')" ><i class="fa fa-print mr-1"></i>Print</button>
                        </div>
                    </div>
                </div>

                <div class="table-stats_order-table"  id="submitworkReport">
                    <table class="table table-bordered" id="submitwork_table">
                        <thead>
                            <tr>
                                <th width="30%"><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                                <th width="30%"><strong>{{ $Lang->Common->Form->Mobile }}/{{ $Lang->Common->Form->Email }} </strong></th>
                                <th width="20%"><strong>{{ $Lang->Common->Form->TotalPoints }}</strong></th>
                                <th width="20%"><strong>{{ $Lang->Common->Form->Total }} {{ $Lang->Common->Submit }}</strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($submitworks as $submitwork)
                            <tr id="{{ $submitwork->id }}">
                                <td>{{@$submitwork->user->name}}</td>
                                <td>{{@$submitwork->user->phone_no}}</td>
                                <td>{{@$submitwork->total_points}}</td>
                                <td>{{@$submitwork->total_count}}</td>
                            </tr>
                            <?php if(!empty($is_details)) { ?>
                            <tr>
                                <td class="report_td" colspan="4" width="100%">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th width="10%"><strong>{{ $Lang->Resource->Image }}</strong></th>
                                            <th width="15%"><strong>{{ $Lang->Resource->Points }}</strong></th>
                                            <th width="15%"><strong>{{ $Lang->Resource->Status }}</strong></th>
                                            <th width="20%"><strong>{{ $Lang->Category }}</strong></th>
                                            <th width="20%"><strong>{{ $Lang->Resource->CreateDate }}</strong></th>
                                            <th width="20%"><strong>{{ $Lang->Resource->CreateDate }}</strong></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($submitwork->children as $work)
                                    <tr id="{{ $submitwork->id }}">
                                        <td>
                                            <img class="rounded" src="{{route('submitwork.image',@$work->asset)}}" alt="{{@$work->asset}}" height="30px">
                                        </td>
                                        <td>{{@$work->points}}</td>
                                        <td>{{@$work->status}}</td>
                                        <td>{{@$work->c_name}} </td>
                                        <td><span class="text-nowrap">{{ Date('d-m-Y',strtotime($work->created_at)) }}</span>  {{ Date('g:iA',strtotime($work->created_at)) }}</td>
                                        <td><span class="text-nowrap">{{ Date('d-m-Y',strtotime($work->verify_date)) }}</span>  {{ Date('g:iA',strtotime($work->verify_date)) }}</td>
                                    </tr>
                                    @endforeach
                                    <tbody>
                                    </table>
                                </td>
                            </tr>
                            <?php } ?>
                            @endforeach
                        </tbody>

                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .report_td {
        padding: 0px !important;
    }

    .form-control_checked {
        height: 30px !important;
        width: 30px !important;
    }
</style>

@endsection

@section('custom-js')
<script>


</script>

@endsection
