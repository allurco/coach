<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('invitation.error.title') }}</title>
    <link rel="stylesheet" href="{{ asset('css/coach.css') }}">
    <style>
        body { background: #fafaf7; font-family: 'Instrument Sans', system-ui, -apple-system, sans-serif; margin: 0; }
        .invite-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .invite-card { background: #fff; border: 1px solid #e5e5e5; border-radius: 12px; padding: 36px 32px; max-width: 460px; width: 100%; box-shadow: 0 6px 30px -10px rgba(0,0,0,.06); text-align: center; }
        .invite-brand { font-size: 28px; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 18px; }
        .invite-brand-dot { color: #d97706; }
        .invite-icon { display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; border-radius: 50%; background: #fef3c7; color: #b45309; margin-bottom: 18px; font-size: 28px; }
        .invite-title { font-size: 18px; font-weight: 600; color: #171717; margin: 0 0 10px; }
        .invite-body { font-size: 15px; color: #525252; line-height: 1.55; margin: 0 0 26px; }
        .invite-cta { display: inline-block; padding: 11px 22px; background: #171717; color: #fff; border-radius: 8px; font-size: 15px; font-weight: 500; text-decoration: none; transition: background 120ms; }
        .invite-cta:hover { background: #404040; }
    </style>
</head>
<body>
    <div class="invite-page">
        <div class="invite-card">
            <div class="invite-brand">Coach<span class="invite-brand-dot">.</span></div>

            <div class="invite-icon" aria-hidden="true">
                @if ($status === 'used')
                    ✓
                @elseif ($status === 'expired')
                    ⏱
                @else
                    ?
                @endif
            </div>

            <h1 class="invite-title">{{ __('invitation.error.'.$status.'.title') }}</h1>
            <p class="invite-body">{{ __('invitation.error.'.$status.'.body') }}</p>

            <a href="{{ url('/') }}" class="invite-cta">
                {{ __('invitation.error.cta') }}
            </a>
        </div>
    </div>
</body>
</html>
