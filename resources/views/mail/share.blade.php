<x-mail::message>
{!! \Illuminate\Support\Str::markdown($body) !!}

—
{{ $senderName }}
</x-mail::message>
