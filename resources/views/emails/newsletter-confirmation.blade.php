<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <title>{{ $copy['title'] }}</title>
</head>
<body style="margin:0;padding:24px;background:#f5f5f5;color:#202124;font-family:Arial,sans-serif;">
    <div style="max-width:600px;margin:0 auto;padding:28px;background:#ffffff;border-radius:12px;">
        <h1 style="margin:0 0 16px;font-size:24px;">{{ $copy['title'] }}</h1>
        <p style="line-height:1.6;">{{ $copy['body'] }}</p>
        <p style="margin:24px 0;">
            <a href="{{ $confirmationUrl }}" style="display:inline-block;padding:12px 20px;border-radius:8px;background:#b04d00;color:#ffffff;text-decoration:none;font-weight:700;">{{ $copy['button'] }}</a>
        </p>
        <p style="line-height:1.6;color:#5f6065;">{{ $copy['expiry'] }}</p>
    </div>
</body>
</html>
