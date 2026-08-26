<x-mail::message>
# {{ __('mail.decided.heading') }}

**{{ $event->title }}**

@if ($when)
{{ __('mail.decided.when', ['when' => $when]) }}
@endif

@if ($event->location)
{{ __('mail.decided.where', ['where' => $event->location]) }}
@endif

<x-mail::button :url="$url">
{{ __('mail.decided.cta') }}
</x-mail::button>

{{ __('mail.decided.planning_hint') }}

{{ __('mail.common.link_hint') }}
[{{ $url }}]({{ $url }})
</x-mail::message>
