<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New volunteer registration</title>
</head>
<body style="margin:0;padding:24px;background:#f6f6f4;color:#191c1d;font-family:Arial,sans-serif;line-height:1.6">
    <main style="max-width:640px;margin:0 auto;padding:28px;border:1px solid #dedbd7;border-radius:12px;background:#ffffff">
        <h1 style="margin:0 0 16px;font-size:24px">New volunteer registration</h1>
        <p>A new application is ready for review in the protected admin dashboard.</p>
        <p><strong>Reference:</strong> {{ $registrationReference }}</p>
        <p>
            <a href="{{ $reviewUrl }}" style="display:inline-block;padding:12px 18px;border-radius:8px;background:#9c4500;color:#ffffff;text-decoration:none;font-weight:bold">Review application securely</a>
        </p>
        <p style="margin-bottom:0;color:#5f6065;font-size:13px">For privacy, personal and profile details are not included in this email.</p>
    </main>
</body>
</html>
