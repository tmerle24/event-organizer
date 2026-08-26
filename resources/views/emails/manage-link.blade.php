<x-mail::message>
# {{ __('mail.manage_link.heading') }}

{{ __('mail.manage_link.body', ['title' => $event->title]) }}

<x-mail::button :url="$url">
{{ __('mail.manage_link.cta') }}
</x-mail::button>

{{ __('mail.manage_link.warning') }}

{{ __('mail.manage_link.share_hint') }}
[{{ $publicUrl }}]({{ $publicUrl }})
</x-mail::message>
