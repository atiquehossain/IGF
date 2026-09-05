@extends('admin.layouts.master')

@section('content')
<div class="content pb-0">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <h4 class="card-title">{{ $title }}</h4>
                        </div>
                        <div class="col-md-6">
                            @if(app(\App\Http\Middleware\Permission::class)->allows(auth('admin')->user(), 'page.menu.index'))<a class="btn igf-btn igf-btn-secondary float-right" href="{{ route('page.menu.index') }}" id="go-back">
                                <i class="fa fa-arrow-left" aria-hidden="true"></i> {{ $Lang->Common->GoBack }}
                            </a>@endif
                        </div>
                    </div>
                </div>

                <div class="modal-body">
                    <form class="form-horizontal" action="{{ route('page.menu.store') }}" method="post" enctype="multipart/form-data">
                        @csrf
                        @if($isLocalization)
                        <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                        @foreach ($translations as $translation)
                            <?php
                            $isActive = '';
                            if ($translation->id == 'en') {
                                $isActive = 'active';
                            }
                            ?>
                            <li class="nav-item" data-id="{{$translation->id}}">
                                <a class="nav-link {{ $isActive }}" id="{{$translation->id}}-tab" data-toggle="pill" href="#{{$translation->id}}" role="tab" aria-controls="{{$translation->id}}" aria-selected="true">{{$translation->name}}</a>
                            </li>
                        @endforeach
                        </ul>
                        @endif
                        <div class="tab-content" id="new_pageMenu">
                        @foreach ($translations as $translation)
                            <?php
                                $isActive = '';
                                $lang = $translation->id;
                                if ($translation->id == 'en') {
                                    $isActive = 'show active';
                                }
                                $bannerList = $bannerList->where('language', $lang);
                                ?>
                            <div class="tab-pane fade {{ $isActive }}" id="{{$translation->id}}" role="tabpanel" aria-labelledby="{{$translation->id}}-tab">

                                <input name="language[{{$lang}}]" type="hidden" class="form-control" value="{{$lang}}">
                                <div class="form-group has-success">
                                    <label for="type" class="control-label mb-1">{{ $Lang->MenuTitle }} type</label>
                                    <select name="type[{{$lang}}]" type="text" class="form-control menu_type" data-lang="{{$lang}}" required data-e2e="page-menu-type-{{ $lang }}">
                                        <option value="">{{ $Lang->Common->PleaseSelect }} </option>
                                            <option value="main"
                                                {{ old('type.'. $lang) == 'main' ? 'selected' : '' }}>{{ $Lang->Common->Form->Main }}</option>
                                            <option value="middle"
                                                {{ old('type.'. $lang) == 'middle' ? 'selected' : '' }}>{{ $Lang->Common->Form->Middle }}</option>
                                            <option value="utility"
                                                {{ old('type.'. $lang) == 'utility' ? 'selected' : '' }}>Utility bar</option>
                                            <option value="footer"
                                                {{ old('type.'. $lang) == 'footer' ? 'selected' : '' }}>{{ $Lang->Common->Form->Footer }}</option>

                                    </select>
                                    @if ($errors->has('type.'. $lang))
                                        <small
                                            class="help-block form-text text-danger">{{ $errors->first('type.'. $lang) }}</small>
                                    @endif
                                </div>
                                <div class="form-group has-success">
                                    <label for="parent" class="control-label mb-1">{{ $Lang->Common->Form->Parent }}</label>
                                    <select name="parent[{{$lang}}]" type="text" class="form-control parent_link_{{$lang}}" data-e2e="page-menu-parent-{{ $lang }}">
                                        <option value="">{{ $Lang->Common->Form-> Select }} {{ $Lang->Common->Form->Parent }}</option>
                                    </select>
                                    @if ($errors->has('parent.'. $lang))
                                        <small
                                            class="help-block form-text text-danger">{{ $errors->first('parent.'. $lang) }}</small>
                                    @endif
                                </div>

                                <div class="form-group has-success">
                                    <label for="name" class="control-label mb-1">{{ $Lang->Common->Form->Name }} <span>*</span></label>
                                    <input name="name[{{$lang}}]" type="text" value="{{ old('name.'. $lang) }}" class="form-control"
                                        required data-e2e="page-menu-name-{{ $lang }}">
                                    @if ($errors->has('name.'. $lang))
                                        <small
                                            class="help-block form-text text-danger">{{ $errors->first('name.'. $lang) }}</small>
                                    @endif
                                </div>

                                <div class="form-group has-success">
                                    <label for="description-{{$lang}}" class="control-label mb-1">Short submenu description</label>
                                    <textarea id="description-{{$lang}}" name="description[{{$lang}}]" class="form-control" maxlength="255" rows="2" placeholder="Optional helper text shown below this menu link">{{ old('description.'. $lang) }}</textarea>
                                </div>

                                <div class="form-group has-success">
                                    <label for="link" class="control-label mb-1">{{ $Lang->Common->Form->Route }} <span>*</span></label>
                                    <select name="link[{{$lang}}]" type="text" class="form-control route_link" data-lang="{{$lang}}" required data-e2e="page-menu-link-{{ $lang }}">
                                        @foreach ($pageRoute as $route)
                                            <option value="{{ $route->id }}" data-type="{{ $route->type }}"
                                                {{ old('link.'. $lang) == $route->id ? 'selected' : '' }}>
                                                {{ $route->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if ($errors->has('link.'. $lang))
                                        <small
                                            class="help-block form-text text-danger">{{ $errors->first('link.'. $lang) }}</small>
                                    @endif
                                </div>

                                <div class="form-group has-success">
                                    <label for="slug" class="control-label mb-1">{{ $Lang->Common->Form-> Select }} {{ $Lang->Common->Page }}</label>
                                    <select name="slug[{{$lang}}]" type="text" class="form-control slug_link_{{$lang}}">
                                        <option value="">{{ $Lang->Common->Form-> Select }} {{ $Lang->Common->Page }}</option>
                                    </select>
                                    @if ($errors->has('slug.'. $lang))
                                        <small
                                            class="help-block form-text text-danger">{{ $errors->first('slug.'. $lang) }}</small>
                                    @endif
                                </div>

                                <div class="form-group has-success" style="display: none">
                                    <label for="banner_id" class="control-label mb-1">{{ $Lang->Common->Form-> Select }} {{ $Lang->BannerTitle }}</label>
                                    <select name="banner_id[{{$lang}}]" type="text" class="form-control">
                                        <option value="">{{ $Lang->Common->Form-> Select }} {{ $Lang->Common->Page }}</option>
                                        @foreach ($bannerList as $banner)
                                            <option value="{{ $banner->id }}"
                                                {{ old('banner_id.'. $lang) == $banner->id ? 'selected' : '' }}>
                                                {{ $banner->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if ($errors->has('banner_id.'. $lang))
                                        <small
                                            class="help-block form-text text-danger">{{ $errors->first('banner_id.'. $lang) }}</small>
                                    @endif
                                </div>

                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="icon" class="control-label mb-1">{{ $Lang->Common->Form->Icon }}</label>
                                            <input name="icon[{{$lang}}]" type="text" class="form-control"
                                                placeholder="fa fa-edit">
                                            @if ($errors->has('icon.'. $lang))
                                                <small class="help-block form-text text-danger">{{ $errors->first('icon.'. $lang) }}</small>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="order_by" class="control-label mb-1">{{ $Lang->Common->Form->OrderBy }}</label>
                                            <input name="order_by[{{$lang}}]" type="number" class="form-control" data-e2e="page-menu-order-by-{{ $lang }}">
                                            @if ($errors->has('order_by.'. $lang))
                                                <small
                                                    class="help-block form-text text-danger">{{ $errors->first('order_by.'. $lang) }}</small>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                         @endforeach
                        </div>

                        <div class="col-md-12 m-b-20 text-right">
                            <button type="submit" class="btn btn-success btn-sm" name="save">
                                <i class="fa fa-save"></i> {{ $Lang->Common->Save }}
                            </button>
                                <button type="submit" name="save_and_update" value="1" class="btn igf-btn igf-btn-secondary igf-btn-compact">
                                    <i class="fa fa-save" aria-hidden="true"></i> Save and continue editing
                            </button>
                        </div>
                    </form>

                </div>
            </div>


        </div>
    </div>
</div>
@endsection

@section('custom-js')
@include('admin.layouts.tinymce')
<script>
    var spinner = $('.spinner');
    itemDelete({
        tableId: "pageMenu_table",
        method: "DELETE"
    });
    itemStatus({
        tableId: "pageMenu_table",
        method: "PUT"
    });

    $(".cancel").click(function() {
        clear();
    });

    function clear() {
        $("input").val("");
    }

    $("#new_pageMenu .menu_type").change(function() {
        var type = $(this).find(':selected').val();
        var lang = $(this).data('lang');
        myParent(type, lang, "#new_pageMenu");
    });

    function myParent(type, lang, newOrEdit) {
        var parent_link = $(`${newOrEdit} .parent_link_${lang}`);
        spinner.show();
        $(`${newOrEdit} .parent_link_${lang} option`).remove();
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            type: 'get',
            url: "{{ route('page.menu.index') }}/" + type + "/type/" + lang,
            success: function(res) {
                parent_link.append(new Option('Select Parent', ''));
                if (res.data) {
                    $.each(res.data, function(index, value) {
                        parent_link.append(new Option(value.name, value.id));
                    });
                }
                spinner.hide();
            },
            error: function(err) {
                slug_link.append(new Option('Select Slug', ''));
                toastrMsg('error', err.responseJSON.message);
                spinner.hide();
            }
        });
    }

    $("#new_pageMenu .route_link").change(function() {
        var type = $(this).find(':selected').data('type');
        var lang = $(this).data('lang');
        myPage(type, lang, "#new_pageMenu");
    });

    function myPage(type, lang, newOrEdit) {
        var slug_link = $(`${newOrEdit} .slug_link_${lang}`);
        spinner.show();
        $(`${newOrEdit} .slug_link_${lang} option`).remove();
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            type: 'get',
            url: "{{ route('page.menu.index') }}/" + type + "/slug/" + lang,
            success: function(res) {
                slug_link.append(new Option('Select Slug', ''));
                if (res.data) {
                    $.each(res.data, function(index, value) {
                        slug_link.append(new Option(value.name, value.slug));
                    });
                }
                spinner.hide();
            },
            error: function(err) {
                slug_link.append(new Option('Select Slug', ''));
                toastrMsg('error', err.responseJSON.message);
                spinner.hide();
            }
        });
    }
</script>
@endsection
