<x-mail::message>
# {{ __('passwords.mail.heading') }}

{{ __('passwords.mail.intro', ['name' => $user->name]) }}

<x-mail::button :url="$resetUrl">
{{ __('passwords.mail.cta') }}
</x-mail::button>

{{ __('passwords.mail.expiry', ['minutes' => $expiryMinutes]) }}

{{ __('passwords.mail.ignore') }}

{{ __('passwords.mail.sign_off') }}<br>
{{ config('app.name') }}
</x-mail::message>
