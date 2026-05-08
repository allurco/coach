<x-mail::message>
# {{ __('invitation.mail.heading') }}

@if ($invitedByName)
{!! __('invitation.mail.invited_by', ['inviter' => $invitedByName]) !!}
@else
{{ __('invitation.mail.invited_anon') }}
@endif

{!! __('invitation.mail.what_it_is') !!}

{{ __('invitation.mail.privacy') }}

{{ __('invitation.mail.cta_intro') }}

<x-mail::button :url="$acceptUrl">
{{ __('invitation.mail.cta_button') }}
</x-mail::button>

**{{ __('invitation.mail.tutorial_intro') }}**

@foreach (__('invitation.mail.tutorial_steps') as $step)
{!! $step !!}

@endforeach

{{ __('invitation.mail.expiry') }}

{{ __('invitation.mail.ignore') }}

{{ __('invitation.mail.sign_off') }}<br>
{{ config('app.name') }}
</x-mail::message>
