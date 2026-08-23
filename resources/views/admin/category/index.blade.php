@extends('admin.layouts.master')

@section('content')
    <div class="content pb-0">
        <h1 class="sr-only">{{ $title }}</h1>
        <div class="row">
            <div class="col-lg-12 col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-5">
                                <strong class="card-title">{{ $Lang->Category }} {{ $Lang->Common->List }}</strong>
                            </div>
                            <div class="col-md-7">
                                <div class="input-group d-flex justify-content-end">
                                    <form action="{{ route('category.index') }}" method="get">
                                        <div class="input-group search-input-group">
                                            <input type="search" name="search" value="{{ @$search }}"
                                                class="form-control search-form-control" aria-label="Search categories">
                                            <span class="input-group-prepend">
                                                <button type="submit" class="btn btn-info btn-sm"><i class="fa fa-search"
                                                        aria-hidden="true"></i>  {{ $Lang->Common->Search }}</button>
                                            </span>
                                        </div>
                                    </form>
                                    <?php if (!empty($addNewLink)) { ?>
                                    <a class="btn btn-info btn-sm ml-1 pull-right" href="{{ route($addNewLink) }}">
                                        <i class="fa fa-plus-circle"></i> {{ $Lang->Common->Add }} {{ $Lang->Common->New }}
                                    </a>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-stats table-responsive" tabindex="0" aria-label="Category records">
                        <table class="table" id="category_table">
                            <thead>
                                <tr>
                                    <th width="10%" class="serial"><strong># {{ $Lang->Common->Form->ID }}</strong></th>
                                    <th width="40%"><strong>{{ $Lang->Common->Form->Name }}</strong></th>
                                    <th width="35%"><strong>{{ $Lang->Common->Form->Slug }}</strong></th>
                                    <th width="20%"><strong>{{ $Lang->Common->Form->Action }}</strong></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($categories as $category)
                                    <tr id="{{ @$category->uuid }}">
                                        <td>#{{ @$category->id }} </td>
                                        <td>
                                            <span class="name">{{ @$category->name }}</span>
                                        </td>
                                        <td>
                                            <span class="copy-text" data-route="{{ route('frontend.category', [app()->getLocale(), @$category->slug]) }}">{{@$category->slug}}</span>
                                        </td>
                                        <td>
                                            <?= App\Link::action(@$category->uuid, @$category->status, 'category ' . ($category->name ?? '')) ?>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center py-4">{{ $search ? 'No categories match this search. Clear the search and try again.' : 'No categories have been added yet.' }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        <div class="pagination justify-content-end">
                            {{ $categories->appends(['search' => $search])->links('vendor.pagination.bootstrap-4') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('custom-js')
    <script>
        itemDelete({
            tableId: "category_table",
            method: "DELETE"
        });
        itemStatus({
            tableId: "category_table",
            method: "PUT"
        });

        $(".edit").click(function() {
            var spinner = $('.spinner');
            spinner.show();
            var id = $(this).data('id');
            window.location.href = "{{ route('category.index') }}/" + id + "/edit";
        });
    </script>
@endsection
