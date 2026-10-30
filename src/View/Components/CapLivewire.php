<?php

namespace LaravelCap\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class CapLivewire extends Component
{
    public function __construct(
        public readonly ?string $nonce = null,
    ) {}

    public function render(): View
    {
        return view('cap::components.livewire');
    }
}
