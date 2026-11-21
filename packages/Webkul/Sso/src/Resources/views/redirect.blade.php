<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta http-equiv="refresh" content="0;url={{ $url }}">
    <title>@lang('sso::app.redirect.title')</title>
</head>
<body style="font-family: system-ui, sans-serif; display: grid; place-items: center; min-height: 100vh; margin: 0;">
    <p>
        @lang('sso::app.redirect.title')
        <br>
        <a href="{{ $url }}">@lang('sso::app.redirect.continue')</a>
    </p>
</body>
</html>
