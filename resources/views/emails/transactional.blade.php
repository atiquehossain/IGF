<!DOCTYPE html>
<html lang="{{ $messageLocale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $messageSubject }}</title>
    <style>
        .igf-email-content a{display:inline-block;padding:10px 16px;border-radius:7px;background:{{ $emailDesign['button_color'] }};color:{{ $emailDesign['button_text_color'] }};font-weight:700;text-decoration:none}
    </style>
</head>
<body style="margin:0;padding:24px;background:{{ $emailDesign['background_color'] }};color:{{ $emailDesign['text_color'] }};font-family:Arial,sans-serif;">
    <div style="max-width:{{ $emailDesign['content_width'] }};margin:0 auto;">
        @if($emailDesign['show_brand_name'])
            <header style="padding:0 4px 14px;color:{{ $emailDesign['text_color'] }};font-size:17px;font-weight:700;line-height:1.4;">
                {{ $emailDesign['brand_heading'] }}
            </header>
        @endif
        <main class="igf-email-content" style="padding:28px;background:{{ $emailDesign['panel_color'] }};border:1px solid {{ $emailDesign['border_color'] }};border-radius:{{ $emailDesign['corner_radius'] }};color:{{ $emailDesign['text_color'] }};line-height:1.6;">
            {!! $htmlBody !!}
        </main>
        @if($emailDesign['footer_text'] !== '')
            <footer style="padding:14px 4px 0;color:{{ $emailDesign['text_color'] }};font-size:12px;line-height:1.55;opacity:.82;">
                {!! nl2br(e($emailDesign['footer_text'])) !!}
            </footer>
        @endif
    </div>
</body>
</html>
