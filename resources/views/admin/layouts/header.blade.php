<!doctype html>
<html class="no-js" lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>{{ $Lang->Admin }} - {{ $title }}</title>
        <meta name="description" content="Ignite Global Foundation content management platform">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="apple-touch-icon" sizes="180x180" href="{{asset('image/favicon/apple-touch-icon.png')}}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{asset('image/favicon/favicon-32x32.png')}}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{asset('image/favicon/favicon-16x16.png')}}">
        <link rel="manifest" href="{{asset('image/favicon/site.webmanifest')}}">

        <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/assets/css/normalize.css') }}">
        <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/assets/css/bootstrap.min.css') }}">
        <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/assets/css/font-awesome.min.css') }}">
        <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/assets/css/themify-icons.css') }}">
        <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/assets/css/pe-icon-7-filled.css') }}">
        <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/assets/calendar/fullcalendar.css') }}">
        <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/assets/css/style.css') }}">
        <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/assets/css/charts/chartist.min.css') }}">
        <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/assets/css/lib/vector-map/jqvmap.min.css') }}">
        <link href="{{ asset('admin-assets/assets/js/datepicker/dist/css/bootstrap-datepicker.min.css') }}" rel="stylesheet" />

        <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/assets/css/toastr.min.css')}}"/>

        @yield('custom-css')

        <style>
            @import url('https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&family=Literata:opsz,wght@7..72,500;600;700&display=swap');
            :root {
                --igf-primary:#9c4500;
                --igf-orange:#ff7500;
                --igf-tertiary:#f07f2d;
                --igf-ink:#191c1d;
                --igf-muted:#5e5d66;
                --igf-surface:#f8f9fa;
                --igf-surface-low:#f3f4f5;
                --igf-line:rgba(25,28,29,.08);
                --igf-sidebar:256px;
                --igf-topbar:64px;
            }
            * { box-sizing:border-box; }
            html { max-width:100%; overflow-x:hidden; overflow-x:clip; }
            body.layout-wrapper { display:block; width:100%; min-height:100vh; max-width:100%; margin:0; overflow-x:hidden; overflow-x:clip; background:var(--igf-surface); color:var(--igf-ink); font-family:'Hanken Grotesk',sans-serif; font-size:16px; -webkit-font-smoothing:antialiased; }
            body.layout-wrapper button, body.layout-wrapper input, body.layout-wrapper select, body.layout-wrapper textarea { font-family:inherit; }
            .igf-skip-link { position:fixed; z-index:2000; top:8px; left:8px; padding:10px 14px; border-radius:7px; background:#191c1d; color:#fff; font-weight:800; transform:translateY(-150%); transition:transform .15s; }
            .igf-skip-link:focus { color:#fff; transform:translateY(0); }

            aside.left-panel { position:fixed!important; z-index:1050; top:0!important; bottom:0; left:0; display:flex; flex-direction:column; width:var(--igf-sidebar)!important; max-width:none!important; height:100vh; overflow:hidden!important; border-right:1px solid var(--igf-line); background:#fff; box-shadow:none; transition:width .2s ease; }
            .igf-sidebar-brand { display:flex; align-items:center; flex:0 0 auto; min-height:91px; padding:20px 24px 17px; }
            .igf-brand-home { display:flex; align-items:center; min-width:0; gap:12px; color:var(--igf-primary); text-decoration:none!important; }
            .igf-brand-mark { display:flex; align-items:center; justify-content:center; flex:0 0 34px; width:34px; height:38px; overflow:hidden; }
            .igf-brand-mark img { width:84px; max-width:none; height:96px; object-fit:contain; transform:translateY(29px); }
            .igf-brand-copy { min-width:0; line-height:1.05; }
            .igf-brand-copy strong { display:block; font:700 19px/1.1 'Literata',serif; white-space:nowrap; }
            .igf-brand-copy small { display:block; margin-top:7px; color:var(--igf-muted); font-size:10px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; white-space:nowrap; }
            .igf-sidebar-close { display:none; align-items:center; justify-content:center; flex:0 0 44px; width:44px; height:44px; margin-left:auto; padding:0; border:0; border-radius:50%; background:transparent; color:var(--igf-muted); cursor:pointer; }
            .igf-sidebar-close:hover { background:var(--igf-surface-low); color:var(--igf-primary); }
            aside.left-panel .navbar { display:block; flex:1 1 auto; width:100%; min-height:0; margin:0; padding:0 8px 18px; overflow-x:hidden; overflow-y:auto; background:#fff; }
            aside.left-panel .main-menu, aside.left-panel .navbar-collapse { display:block!important; width:100%; padding:0; }
            aside.left-panel .navbar-nav { display:block; width:100%; margin:0; padding:0; }
            aside.left-panel .navbar-nav > li { float:none; width:100%; padding:0; }
            aside.left-panel .navbar-nav > li > a { display:flex; align-items:center; gap:13px; min-height:44px; margin:2px 0; padding:10px 14px; border:0; border-right:2px solid transparent; border-radius:0 8px 8px 0; color:var(--igf-muted); font-size:13px; font-weight:700; line-height:1.2; letter-spacing:.02em; text-decoration:none; transition:background .18s,color .18s,border-color .18s; }
            aside.left-panel .navbar-nav > li > a .menu-icon { flex:0 0 20px; width:20px; margin:0; color:inherit; font-size:16px; text-align:center; }
            aside.left-panel .navbar-nav > li.active > a, aside.left-panel .navbar-nav > li.show > a, aside.left-panel .navbar-nav a.active { border-right-color:var(--igf-primary); background:rgba(156,69,0,.055); color:var(--igf-primary); }
            aside.left-panel .navbar-nav a:hover { background:var(--igf-surface-low); color:var(--igf-primary); }
            aside.left-panel .sub-menu { position:static!important; width:100%; margin:0!important; padding:3px 0 5px 36px!important; border:0; background:transparent; box-shadow:none; }
            aside.left-panel .sub-menu li { position:relative; padding:0!important; }
            aside.left-panel .sub-menu li > i { display:none; }
            aside.left-panel .sub-menu li > a { display:block; padding:8px 11px!important; color:#6c6865; font-size:12px; font-weight:600; line-height:1.25; text-decoration:none; }
            aside.left-panel .sub-menu li > a.active { color:var(--igf-primary); font-weight:800; }
            .igf-nav-group,.igf-all-tools { margin-top:5px; }
            .igf-all-tools { border-top:1px solid var(--igf-line); padding-top:7px; }
            .igf-nav-group summary,.igf-all-tools summary { display:flex; align-items:center; gap:13px; min-height:44px; padding:9px 14px; border-radius:8px; color:#77777c; font-size:12px; font-weight:800; letter-spacing:.03em; cursor:pointer; list-style:none; }
            .igf-nav-group summary::-webkit-details-marker,.igf-all-tools summary::-webkit-details-marker { display:none; }
            .igf-nav-group summary i,.igf-all-tools summary i { flex:0 0 20px; width:20px; text-align:center; }
            .igf-nav-group summary:hover,.igf-all-tools summary:hover { background:var(--igf-surface-low); color:var(--igf-primary); }
            .igf-nav-group[open] summary,.igf-all-tools[open] summary { color:var(--igf-primary); }
            .igf-nav-group > ul,.igf-all-tools > ul { margin:3px 0 7px!important; padding-left:12px!important; }
            .igf-nav-group > ul > li > a { min-height:44px!important; padding-block:8px!important; font-size:12px!important; }
            .igf-sidebar-footer { flex:0 0 auto; padding:15px 20px 20px; border-top:1px solid var(--igf-line); }
            .igf-sidebar-footer a, .igf-sidebar-footer button { display:flex; align-items:center; gap:12px; width:100%; min-height:44px; padding:8px 10px; border:0; background:transparent; color:var(--igf-muted); font-size:12px; font-weight:700; text-align:left; text-decoration:none; cursor:pointer; }
            .igf-sidebar-footer i { width:17px; text-align:center; }
            .igf-sidebar-footer .igf-visit-site { justify-content:center; margin-bottom:9px; border:1px solid var(--igf-line); border-radius:8px; background:var(--igf-surface-low); color:var(--igf-ink); }

            .right-panel { width:auto; min-width:0; max-width:calc(100vw - var(--igf-sidebar)); min-height:100vh; margin-left:var(--igf-sidebar)!important; overflow-x:hidden; overflow-x:clip; padding-top:var(--igf-topbar)!important; background:var(--igf-surface); transition:margin-left .2s ease; }
            .right-panel header.header.igf-topbar { position:fixed; z-index:1040; top:0; right:0; left:var(--igf-sidebar); display:flex; align-items:center; justify-content:space-between; width:auto; height:var(--igf-topbar); padding:0 32px; border-bottom:1px solid var(--igf-line); background:rgba(248,249,250,.97); box-shadow:none; transition:left .2s ease; }
            .igf-topbar-left, .igf-topbar-right { display:flex; align-items:center; min-width:0; }
            .right-panel header.header.igf-topbar .igf-topbar-left { position:static!important; float:none; flex:1; width:auto; padding:0; border:0; background:transparent; gap:24px; }
            .right-panel header.header.igf-topbar .igf-topbar-right { position:static!important; float:none; flex:0 0 auto; width:auto; padding:0; background:transparent; gap:11px; }
            .right-panel .menutoggle { display:none; align-items:center; justify-content:center; float:none!important; width:44px!important; height:44px; padding:0; border:0; border-radius:8px; background:transparent; color:var(--igf-muted); cursor:pointer; }
            .igf-admin-search { position:relative; flex:0 1 264px; max-width:264px; }
            .igf-admin-search i { position:absolute; top:50%; left:13px; color:#77777c; font-size:12px; transform:translateY(-50%); pointer-events:none; }
            .igf-admin-search input { width:100%; height:44px; padding:8px 14px 8px 36px; border:1px solid var(--igf-line); border-radius:999px; background:#fff; color:var(--igf-ink); font-size:13px; }
            .igf-admin-search input:focus { border-color:var(--igf-orange); box-shadow:0 0 0 3px rgba(255,117,0,.1); }
            .igf-quick-create { min-width:44px; min-height:44px; box-shadow:0 4px 12px rgba(120,51,0,.2); letter-spacing:.02em; }
            .igf-language > button, .user-area > button { display:flex; align-items:center; justify-content:center; width:44px; height:44px; padding:0; border:0; border-radius:50%; background:transparent; color:var(--igf-muted); cursor:pointer; }
            .igf-language > button:after, .user-area > button:after { display:none; }
            .igf-language > button:hover { background:#e7e8e9; color:var(--igf-primary); }
            .user-area { float:none!important; margin:0!important; }
            .user-area .user-avatar { width:36px; height:36px; border:1px solid var(--igf-line); object-fit:cover; }
            .user-menu.dropdown-menu { top:42px!important; min-width:220px; padding:8px; border:1px solid var(--igf-line); border-radius:10px; box-shadow:0 12px 32px rgba(36,36,43,.1); }
            .user-menu .nav-link { display:flex; align-items:center; gap:9px; width:100%; min-height:44px; padding:9px 10px; color:var(--igf-muted); font-size:13px; }
            .user-menu .igf-user-name { color:var(--igf-ink); font-weight:800; }
            .right-panel > .container-fluid { padding:0; }
            .right-panel > .container-fluid > .row { margin:0; }
            .right-panel > .container-fluid > .row > .col-md-12 { padding:0; }
            .right-panel > .container-fluid > .row > .col-md-12 > .progress { display:none; }
            #admin-content, .right-panel > .container-fluid, .right-panel > .container-fluid > .row, .right-panel > .container-fluid > .row > .col-md-12 { min-width:0; max-width:100%; }
            .progress, .spinner { z-index:1100; }
            footer.site-footer .footer-inner { border-top:1px solid var(--igf-line); background:#fff!important; color:#6b6967; }

            body.open aside.left-panel { width:83px!important; max-width:83px!important; }
            body.open .right-panel { max-width:calc(100vw - 83px); margin-left:83px!important; }
            body.open .right-panel header.header.igf-topbar { left:83px; }
            body.open .igf-brand-copy, body.open .igf-sidebar-footer span { display:none; }
            body.open .igf-nav-group summary span, body.open .igf-all-tools summary span, body.open .igf-nav-label { display:none; }
            body.open .igf-sidebar-brand { justify-content:center; padding-inline:16px; }
            body.open .igf-sidebar-footer { padding-inline:11px; }

            @media(max-width:1010px) {
                aside.left-panel { width:83px!important; max-width:83px!important; }
                .right-panel { max-width:calc(100vw - 83px); margin-left:83px!important; }
                .right-panel header.header.igf-topbar { left:83px; }
                .right-panel .menutoggle { display:inline-flex; }
                .igf-brand-copy, .igf-sidebar-footer span { display:none; }
                .igf-nav-group summary span, .igf-all-tools summary span, .igf-nav-label { display:none; }
                .igf-sidebar-brand { justify-content:center; padding-inline:16px; }
                .igf-sidebar-footer { padding-inline:11px; }
                aside.left-panel.open-menu { width:256px!important; max-width:256px!important; }
                aside.left-panel.open-menu .igf-brand-copy, aside.left-panel.open-menu .igf-sidebar-footer span { display:block; }
                aside.left-panel.open-menu .igf-nav-group summary span, aside.left-panel.open-menu .igf-all-tools summary span, aside.left-panel.open-menu .igf-nav-label { display:block; }
                aside.left-panel.open-menu .igf-sidebar-close { display:flex; }
                aside.left-panel.open-menu .igf-sidebar-brand { justify-content:flex-start; padding-inline:24px; }
                aside.left-panel.open-menu .igf-sidebar-footer { padding-inline:20px; }
            }
            @media(max-width:760px) {
                aside.left-panel { display:none; width:256px!important; max-width:256px!important; }
                .right-panel, body.open .right-panel { max-width:100vw; margin-left:0!important; }
                .right-panel header.header.igf-topbar, body.open .right-panel header.header.igf-topbar { left:0; padding:0 16px; }
                .igf-brand-copy, .igf-sidebar-footer span { display:block; }
                .igf-nav-group summary span, .igf-all-tools summary span, .igf-nav-label { display:block; }
                .igf-sidebar-close { display:flex; }
                .igf-sidebar-brand { justify-content:flex-start; padding-inline:24px; }
                .igf-sidebar-footer { padding-inline:20px; }
                .igf-admin-search { flex-basis:min(52vw,264px); }
                .igf-quick-create { width:44px; padding:0; justify-content:center; }
                .igf-quick-create span { display:none; }
            }
            @media(max-width:480px) {
                .igf-admin-search { display:none; }
                .right-panel .igf-topbar-left { flex:0 0 auto; }
                .igf-mobile-search { display:block; position:relative; }
                .igf-mobile-search summary { display:flex; width:44px; height:44px; align-items:center; justify-content:center; cursor:pointer; border-radius:10px; }
                .igf-mobile-search summary::-webkit-details-marker { display:none; }
                .igf-mobile-search form { position:absolute; top:46px; left:0; z-index:1100; display:flex; gap:8px; width:min(88vw,340px); padding:12px; background:#fff; border:1px solid #ddd5ce; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.16); }
                .igf-mobile-search input { min-width:0; flex:1; border:1px solid #b8aea5; border-radius:8px; padding:8px 10px; }
                .igf-mobile-search button { min-width:44px; min-height:44px; border:0; border-radius:8px; padding:8px 12px; background:#9c4500; color:#fff; font-weight:700; }
            }
            @media(min-width:481px) { .igf-mobile-search { display:none; } }
            .right-panel .btn-sm1,
            .right-panel button.status,
            .right-panel button.trash,
            .right-panel button.edit { min-width:44px; min-height:44px; display:inline-flex; align-items:center; justify-content:center; }
            .right-panel .table-responsive { overflow-x:auto; -webkit-overflow-scrolling:touch; }
            #weatherWidget .currentDesc {
                color: #ffffff!important;
            }
            .traffic-chart {
                min-height: 335px;
            }
            #flotPie1  {
                height: 150px;
            }
            #flotPie1 td {
                padding:3px;
            }
            #flotPie1 table {
                top: 20px!important;
                right: -10px!important;
            }
            .chart-container {
                display: table;
                min-width: 270px ;
                text-align: left;
                padding-top: 10px;
                padding-bottom: 10px;
            }
            #flotLine5  {
                height: 105px;
            }

            #flotBarChart {
                height: 150px;
            }
            #cellPaiChart{
                height: 160px;
            }

            .ck-editor__editable_inline {
                height: 450px;
            }

        </style>
    </head>

    <body class="layout-wrapper">
        <a class="igf-skip-link" href="#admin-content">Skip to main content</a>
