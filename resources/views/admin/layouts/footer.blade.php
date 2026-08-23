<footer class="site-footer mt-auto">
    <div class="footer-inner bg-white footer-background">
        <div class="row">
            <div class="col-12 footer-copy-right">
                Copyright &copy; {{ now()->year }} Ignite Global Foundation
            </div>
        </div>
    </div>
</footer>
</div>

@include('admin.layouts.scripts')
@yield('custom-js')

<script>
    @if (Session::has('message'))
            var type = "{{ Session::get('alert-type', 'info') }}";
    switch (type){
    case 'info':
            toastr.info("{{ Session::get('message') }}");
            break;
            case 'warning':
            toastr.warning("{{ Session::get('message') }}");
            break;
            case 'success':
            toastr.success("{{ Session::get('message') }}");
            break;
            case 'error':
            toastr.error("{{ Session::get('message') }}");
            break;
    }
    @endif
</script>
</body>

</html>
