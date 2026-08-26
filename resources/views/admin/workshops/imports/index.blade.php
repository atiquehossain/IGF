@extends('admin.layouts.master')
@section('content')
    @include('admin.shared.application-import.index', ['listingTitle' => fn ($item) => $item->translations->firstWhere('locale', 'en')?->title ?: $item->translations->first()?->title ?: 'Untitled workshop'])
@endsection
