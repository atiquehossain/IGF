@include('admin.layouts.header')
@include('admin.layouts.sidebar')
@include('admin.layouts.navbar')

<div class="container-fluid" style="min-height: 100dvh;">
  <div class="row">
    <div class="col-md-12">
      <div class="progress">
        <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar"
          style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
      </div>
      <div class="spinner" role="status" aria-live="polite" aria-hidden="true">
        <div class="blob blob-0"></div>
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
        <div class="blob blob-4"></div>
        <div class="blob blob-5"></div>
        <span class="sr-only">Loading. Please wait.</span>
      </div>
      <div id="admin-content" tabindex="-1">
        @yield('content')
      </div>

    </div>
  </div>
</div>
<!-- main container end -->
<div class="clearfix"></div>

@include('admin.layouts.footer')
