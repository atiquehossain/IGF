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
        const adminFlashType = @json(Session::get('alert-type', 'info'));
        const adminFlashMessage = @json(Session::get('message'));
        toastrMsg(adminFlashType, adminFlashMessage);
    @endif
</script>
</body>

</html>
