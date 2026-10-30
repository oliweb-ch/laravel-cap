<?php

namespace LaravelCap\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use LaravelCap\Cap;
use LaravelCap\Livewire\WithCap;
use LaravelCap\Rules\CapRule;
use LaravelCap\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Stub Livewire component for testing the WithCap trait in isolation.
 */
class StubComponent
{
    use WithCap;

    public string $capToken = '';
    public string $name = '';
    public array $dispatchedEvents = [];

    public function validate(array $rules, array $messages = [], array $attributes = []): array
    {
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['capToken' => $this->capToken],
            $rules,
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function dispatch(string $event, mixed ...$params): void
    {
        $this->dispatchedEvents[] = $event;
    }

    public function callValidateCap(string $prop = 'capToken'): void
    {
        $this->validateCap($prop);
    }

    public function callResetCap(string $prop = 'capToken'): void
    {
        $this->resetCap($prop);
    }
}

class CapLivewireTest extends TestCase
{
    private function makeStub(): StubComponent
    {
        return new StubComponent();
    }

    #[Test]
    public function validate_cap_passes_with_valid_token(): void
    {
        Http::fake(['*' => Http::response(['success' => true])]);

        $stub = $this->makeStub();
        $stub->capToken = 'valid-token';

        // Should not throw
        $stub->callValidateCap();

        // Token cleared after use
        $this->assertSame('', $stub->capToken);
    }

    #[Test]
    public function validate_cap_throws_on_refused_token(): void
    {
        Http::fake(['*' => Http::response(['success' => false])]);

        $stub = $this->makeStub();
        $stub->capToken = 'bad-token';

        $this->expectException(ValidationException::class);
        $stub->callValidateCap();
    }

    #[Test]
    public function validate_cap_throws_on_empty_token(): void
    {
        $stub = $this->makeStub();
        $stub->capToken = '';

        $this->expectException(ValidationException::class);
        $stub->callValidateCap();
    }

    #[Test]
    public function reset_cap_clears_token_and_dispatches_browser_event(): void
    {
        $stub = $this->makeStub();
        $stub->capToken = 'some-token';

        $stub->callResetCap();

        $this->assertSame('', $stub->capToken);
        $this->assertContains('cap-reset', $stub->dispatchedEvents);
    }

    #[Test]
    public function exception_hook_resets_cap_on_validation_exception(): void
    {
        $stub = $this->makeStub();
        $stub->capToken = 'some-token';

        $validator = \Illuminate\Support\Facades\Validator::make([], ['name' => 'required']);
        $e = new ValidationException($validator);

        $stub->exception($e, fn() => null);

        $this->assertSame('', $stub->capToken);
        $this->assertContains('cap-reset', $stub->dispatchedEvents);
    }

    #[Test]
    public function exception_hook_does_not_reset_cap_on_other_exceptions(): void
    {
        $stub = $this->makeStub();
        $stub->capToken = 'some-token';

        $stub->exception(new \RuntimeException('other'), fn() => null);

        $this->assertSame('some-token', $stub->capToken);
        $this->assertEmpty($stub->dispatchedEvents);
    }

    #[Test]
    public function validate_cap_uses_custom_token_property(): void
    {
        Http::fake(['*' => Http::response(['success' => true])]);

        // Extend stub to add 'myCapToken' property
        $stub = new class extends StubComponent {
            public string $myCapToken = 'valid-token';

            public function validate(array $rules, array $messages = [], array $attributes = []): array
            {
                $validator = \Illuminate\Support\Facades\Validator::make(
                    ['myCapToken' => $this->myCapToken],
                    $rules,
                );
                if ($validator->fails()) {
                    throw new ValidationException($validator);
                }
                return $validator->validated();
            }
        };

        $stub->callValidateCap('myCapToken');
        $this->assertSame('', $stub->myCapToken);
    }

    #[Test]
    public function livewire_component_is_not_registered_without_livewire(): void
    {
        // \Livewire\Component does not exist in this test environment
        $this->assertFalse(class_exists(\Livewire\Component::class));

        // The component should not be registered
        $aliases = \Illuminate\Support\Facades\Blade::getClassComponentAliases();
        $this->assertArrayNotHasKey('cap::livewire', $aliases);
    }
}
