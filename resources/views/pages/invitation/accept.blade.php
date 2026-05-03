<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('invitation.page.title') }}</title>
    <link rel="stylesheet" href="{{ asset('css/coach.css') }}">
    <style>
        body { background: #fafaf7; font-family: 'Instrument Sans', system-ui, -apple-system, sans-serif; margin: 0; }
        .invite-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .invite-card { background: #fff; border: 1px solid #e5e5e5; border-radius: 12px; padding: 36px 32px; max-width: 420px; width: 100%; box-shadow: 0 6px 30px -10px rgba(0,0,0,.06); }
        .invite-brand { font-size: 28px; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 8px; }
        .invite-brand-dot { color: #d97706; }
        .invite-greet { color: #525252; margin: 0 0 26px; line-height: 1.5; }
        .invite-greet strong { color: #171717; }
        .field { margin-bottom: 16px; }
        .field label { display: block; font-size: 13px; font-weight: 500; color: #404040; margin-bottom: 6px; }
        .field input { width: 100%; padding: 10px 12px; border: 1px solid #d4d4d4; border-radius: 8px; font-size: 15px; box-sizing: border-box; transition: border-color 120ms; }
        .field input:focus { outline: none; border-color: #d97706; box-shadow: 0 0 0 3px rgba(217,119,6,.15); }
        .field input[readonly] { background: #f5f5f5; color: #737373; cursor: not-allowed; }
        .submit { width: 100%; padding: 11px; background: #171717; color: #fff; border: 0; border-radius: 8px; font-size: 15px; font-weight: 500; cursor: pointer; transition: background 120ms; margin-top: 6px; }
        .submit:hover { background: #404040; }
        .errors { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 10px 12px; border-radius: 8px; font-size: 13px; margin-bottom: 18px; }
        .errors ul { margin: 0; padding-left: 18px; }
        .hint { font-size: 12px; color: #737373; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="invite-page">
        <div class="invite-card">
            <div class="invite-brand">Coach<span class="invite-brand-dot">.</span></div>
            <p class="invite-greet">
                {!! __('invitation.page.greeting', ['name' => '<strong>'.e($user->name).'</strong>']) !!}
            </p>

            @if ($errors->any())
                <div class="errors">
                    <ul>
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('invitation.accept', $token) }}">
                @csrf

                <div class="field">
                    <label>{{ __('invitation.page.email_label') }}</label>
                    <input type="email" value="{{ $user->email }}" readonly>
                </div>

                <div class="field">
                    <label for="password">{{ __('invitation.page.password_label') }}</label>
                    <input type="password" name="password" id="password" required autocomplete="new-password" autofocus>
                    <p class="hint">{{ __('invitation.page.password_hint') }}</p>
                </div>

                <div class="field">
                    <label for="password_confirmation">{{ __('invitation.page.password_confirmation_label') }}</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password">
                </div>

                <button type="submit" class="submit">{{ __('invitation.page.submit') }}</button>
            </form>
        </div>
    </div>
</body>
</html>
