<x-mail::message>
# {{ __('mail.invite.heading', ['title' => $event->title]) }}

{{ __('mail.invite.body') }}

<x-mail::button :url="$url">
{{ __('mail.invite.cta') }}
</x-mail::button>

@if ($event->description)
{{ $event->description }}
@endif

{{ __('mail.common.link_hint') }}
[{{ $url }}]({{ $url }})

{{ __('mail.common.no_account') }}
</x-mail::message>
