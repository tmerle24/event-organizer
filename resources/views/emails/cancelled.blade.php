<x-mail::message>
# {{ __('mail.cancelled.heading') }}

{{ __('mail.cancelled.body', ['title' => $event->title]) }}

<x-mail::button :url="$url">
{{ __('mail.cancelled.cta') }}
</x-mail::button>

<x-slot:subcopy>
{{ __('mail.common.why', ['title' => $event->title]) }}
[{{ __('mail.common.unsubscribe') }}]({{ $unsubscribeUrl }})
</x-slot:subcopy>
</x-mail::message>
