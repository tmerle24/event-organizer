<x-mail::message>
# {{ __('mail.reopened.heading') }}

{{ __('mail.reopened.body', ['title' => $event->title]) }}

@if ($when)
{{ __('mail.decided.when', ['when' => $when]) }}
@endif

@if ($event->location)
{{ __('mail.decided.where', ['where' => $event->location]) }}
@endif

<x-mail::button :url="$url">
{{ __('mail.decided.cta') }}
</x-mail::button>

<x-slot:subcopy>
{{ __('mail.common.why', ['title' => $event->title]) }}
[{{ __('mail.common.unsubscribe') }}]({{ $unsubscribeUrl }})
</x-slot:subcopy>
</x-mail::message>
