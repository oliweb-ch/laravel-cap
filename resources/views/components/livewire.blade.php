@props(['nonce' => null])

@php
    // Extrait le nom de la propriété depuis wire:model="propName"
    $wireModel = $attributes->whereStartsWith('wire:model')->first();
@endphp

<div
    wire:ignore
    x-data="{ token: '' }"
    x-init="
        var widget = $el.querySelector('cap-widget');

        widget.addEventListener('solve', function(e) {
            token = e.detail.token;
            @if($wireModel)
            $wire.set({{ json_encode($wireModel) }}, e.detail.token);
            @endif
        });

        widget.addEventListener('reset', function() {
            token = '';
            @if($wireModel)
            $wire.set({{ json_encode($wireModel) }}, '');
            @endif
        });

        $wire.$on('cap-reset', function() {
            widget.reset();
        });
    "
>
    <cap-widget
        data-cap-api-endpoint="{{ config('cap.endpoint') }}"
        @if($nonce) data-cap-csp-nonce="{{ $nonce }}" @endif
    ></cap-widget>
</div>
