<?php

namespace LaravelCap\Livewire;

use Illuminate\Validation\ValidationException;
use LaravelCap\Cap;
use LaravelCap\Rules\CapRule;

trait WithCap
{
    /**
     * Validates the Cap token in the submit action.
     * Throws ValidationException if the token is invalid or missing.
     * Clears the token after use (single-use).
     */
    protected function validateCap(string $tokenProperty = 'capToken'): void
    {
        $this->validate(
            [$tokenProperty => ['required', new CapRule(app(Cap::class))]],
        );

        // Token consumed — clear immediately
        $this->{$tokenProperty} = '';
    }

    /**
     * Resets the Cap token server-side and dispatches a browser event to reset the widget.
     */
    protected function resetCap(string $tokenProperty = 'capToken'): void
    {
        $this->{$tokenProperty} = '';
        $this->dispatch('cap-reset');
    }

    /**
     * Livewire lifecycle hook: resets the cap on any ValidationException.
     * Covers the case where other fields fail after the token has been consumed.
     */
    public function exception(\Throwable $e, callable $stopPropagation): void
    {
        if ($e instanceof ValidationException) {
            $this->resetCap();
        }
    }
}
