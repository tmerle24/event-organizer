<x-mail::message>
# {{ __('mail.cancelled.heading') }}

{{ __('mail.cancelled.body', ['title' => $event->title]) }}

<x-mail::button :url="$url">
{{ __('mail.cancelled.cta') }}
</x-mail::button>
</x-mail::message>
