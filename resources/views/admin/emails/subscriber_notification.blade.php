<!DOCTYPE html>
<html>
<head>
    <title>Notification</title>
</head>
<body>
    <div style="white-space: pre-line;">
        {{ $body }}
    </div>
    @if($signatureImageUrl)
    <div style="margin-top: 20px;">
        <img src="{{ $signatureImageUrl }}" alt="Email Signature" style="max-width: 300px; height: auto;">
    </div>
    @endif
</body>
</html>
