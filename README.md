# laravel-cap

A Laravel wrapper for [Cap](https://github.com/tiagozip/cap) — the self-hosted, privacy-friendly CAPTCHA alternative based on Proof-of-Work.

Cap works without tracking, cookies, or third-party services. This package integrates server-side token verification into Laravel through a service, a facade, a middleware, a validation rule, and Blade directives.

## Requirements

- PHP **^8.2**
- Laravel **12 or 13**
- A running [Cap instance](https://trycap.dev/guide/) (self-hosted via Docker)

## Installation

```bash
composer require oliweb/laravel-cap
```

The service provider and facade are registered automatically via Laravel's package auto-discovery.

Publish the configuration file:

```bash
php artisan vendor:publish --tag=cap-config
```

Publish the JS, CSS, and WASM assets (required for `@capScripts` and `@capStyles`):

```bash
php artisan vendor:publish --tag=cap-assets
```

This publishes the following files to `public/vendor/cap/`:

| File | Description |
|------|-------------|
| `cap-widget.js` | Cap widget (custom element + programmatic API) |
| `cap-widget.css` | Default widget styles |
| `cap_wasm_bg.wasm` | WebAssembly binary for rsw proof-of-work |
| `cap_wasm.js` | JS loader for the WASM module |
| `hashwx.wasm` | WebAssembly binary for hashwx proof-of-work (GPU-resistant, default since Cap 3.1.12) |

### Upgrading assets

After `composer update oliweb/laravel-cap`, re-publish assets with `--force` to overwrite the files in `public/vendor/cap/` with the versions bundled in the new package:

```bash
php artisan vendor:publish --tag=cap-assets --force
```

Publish the translation files (optional — to override messages):

```bash
php artisan vendor:publish --tag=cap-lang
```

## Configuration

Add the following variables to your `.env` file:

```env
CAP_ENDPOINT=https://cap.example.com/your-site-key/
CAP_SECRET=your-secret-key
CAP_TOKEN_FIELD=cap-token
CAP_TIMEOUT=5
CAP_FAIL_OPEN=false
```

| Variable          | Description                                                                    | Default     |
|-------------------|--------------------------------------------------------------------------------|-------------|
| `CAP_ENDPOINT`    | Full URL of your Cap instance including the site key (trailing slash required) | —           |
| `CAP_SECRET`      | Secret key from your Cap dashboard                                             | —           |
| `CAP_TOKEN_FIELD` | Name of the hidden field injected by the Cap widget                            | `cap-token` |
| `CAP_TIMEOUT`     | HTTP timeout in seconds for the `/siteverify` request                          | `5`         |
| `CAP_FAIL_OPEN`   | When `true`, let requests through on network/server errors (see below)         | `false`     |
| `CAP_FRAME_ROUTE` | URL path for the iframe route (used by `@capFrame`)                            | `cap-frame` |

### Fail-open mode

By default, any communication error with the Cap instance (network failure, timeout, HTTP 5xx) blocks the request, just like an invalid token would.

Setting `CAP_FAIL_OPEN=true` inverts this: communication errors silently pass, so a Cap outage does not take your forms down with it.

**An explicitly invalid token (`success: false`) is always rejected regardless of this setting.** Fail-open only covers infrastructure failures, not verification failures.

## Translations

Server-side messages (validation rule, middleware) are translatable. English and French are included out of the box.

To override or add a language, publish the translation files and edit `lang/vendor/cap/{locale}/messages.php`:

```bash
php artisan vendor:publish --tag=cap-lang
```

```php
// lang/vendor/cap/fr/messages.php
return [
    'validation_failed' => 'La vérification :attribute a échoué. Veuillez réessayer.',
    'middleware_failed'  => 'La vérification Cap a échoué.',
];
```

Laravel selects the right file automatically based on `App::getLocale()`.


## Alerts & Monitoring

When the Cap server becomes unreachable or is misconfigured, `laravel-cap` dispatches
Laravel events that you can listen to in your application. An optional built-in listener
sends you an e-mail alert automatically.

### Events

#### `CapServerUnreachable`

Dispatched when the Cap server cannot be reached:
- Connection refused, timeout, DNS failure (caught `\Throwable`)
- HTTP 5xx response from `/siteverify`

| Property | Type | Description |
|---|---|---|
| `$endpoint` | `string` | The `/siteverify` URL that was called (no secret) |
| `$failOpen` | `bool` | Whether fail-open is active (CAPTCHA inactive when `true`) |
| `$exceptionMessage` | `string` | Error message or HTTP status (e.g. `HTTP 503`) |
| `$occurredAt` | `\DateTimeImmutable` | Timestamp of the failure |

#### `CapMisconfigured`

Dispatched when `/siteverify` returns an HTTP 4xx response, indicating a configuration
problem (wrong secret, wrong site key, wrong endpoint):

| Property | Type | Description |
|---|---|---|
| `$endpoint` | `string` | The URL that was called (no secret) |
| `$statusCode` | `int` | HTTP status code (401, 403, 404, ...) |

> **Never triggers an alert**: a token explicitly refused by the server (`success: false`)
> is normal operation and dispatches no event.

### Built-in mail listener

Activate by setting `CAP_ALERT_MAIL` in your `.env`:

```env
CAP_ALERT_MAIL=admin@example.com
CAP_ALERT_THROTTLE_MINUTES=15   # optional, default 15
```

The listener (`SendCapAlertMail`):
- Implements `ShouldQueue` -- processed by your queue worker
- Sends one notification per event type per throttle window (default 15 min)
- Mail is sent via `Notification::route` -- no `User` model required
- Mentions whether fail-open is active (i.e. whether CAPTCHA protection is currently off)
- Available in English and French (follows your application locale)

The listener is only registered when `cap.alerts.mail` is configured. If the key is
absent or `null`, no listener is registered and no overhead is incurred.

### Custom listeners

Listen to these events in your `AppServiceProvider` or `EventServiceProvider`:

```php
use LaravelCap\Events\CapServerUnreachable;
use LaravelCap\Events\CapMisconfigured;

// In boot():
Event::listen(CapServerUnreachable::class, function ($event) {
    // Slack, PagerDuty, custom log...
    Log::critical("Cap server unreachable", [
        "endpoint"  => $event->endpoint,
        "fail_open" => $event->failOpen,
        "error"     => $event->exceptionMessage,
    ]);
});

Event::listen(CapMisconfigured::class, function ($event) {
    Log::error("Cap misconfigured", [
        "endpoint" => $event->endpoint,
        "http"     => $event->statusCode,
    ]);
});
```

### `cap:check` command

Probes the Cap server health endpoint (`GET /health`) and exits with a meaningful code:

| Exit code | Meaning |
|---|---|
| `0` | Server healthy (`{"status":"ok"}`) |
| `1` | Server unreachable (connection error, timeout) |
| `2` | Server reachable but unhealthy (`{"status":"unavailable"}`) |

```bash
php artisan cap:check
```

With `--notify`, dispatches the same events as a live request failure (useful for testing
your alert pipeline):

```bash
php artisan cap:check --notify
```

**Scheduler integration** -- add to `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command("cap:check --notify")
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->emailOutputOnFailure("admin@example.com"); // optional fallback
```

### Configuration reference

```php
// config/cap.php
'alerts' => [
    'mail'             => env('CAP_ALERT_MAIL'),           // null = disabled
    'throttle_minutes' => (int) env('CAP_ALERT_THROTTLE_MINUTES', 15),
],
```

## Widget styling

Edit `public/vendor/cap/cap-widget.css` to override the CSS custom properties exposed by the widget:

```css
cap-widget {
    --cap-color-primary:  #6366f1;
    --cap-color-success:  #22c55e;
    --cap-border-radius:  0.5rem;
    --cap-font-family:    inherit;
    /* ... */
}
```

## Usage

### Blade directives

| Directive | Description |
|-----------|-------------|
| `@cap` | Renders `<cap-widget>` with the configured endpoint |
| `@capScripts` | Injects `window.CAP_CUSTOM_WASM_URL`, `window.CAP_CUSTOM_HASHWX_URL` + `<script type="module">` for the widget |
| `@capStyles` | `<link>` loading the theme from `public/vendor/cap/cap-widget.css` |
| `@capConfig` | `<script>` exposing `window.CAP_API_ENDPOINT` and `window.CAP_TOKEN_FIELD` |
| `@capFrame` | Renders the Cap widget in an isolated iframe with a permissive CSP — the parent page keeps a strict CSP without `'unsafe-eval'` |

#### Standard widget mode

Include the Cap widget in any Blade form:

```blade
@capStyles
@capScripts

<form method="POST" action="/contact">
    @csrf
    @cap
    <button type="submit">Submit</button>
</form>
```

The widget automatically injects a hidden `cap-token` field (or the value of `CAP_TOKEN_FIELD`) into its parent form upon successful verification.

`@capScripts` always injects `window.CAP_CUSTOM_WASM_URL` and `window.CAP_CUSTOM_HASHWX_URL` pointing to the locally published WASM files, so no external CDN is contacted at runtime.

#### Programmatic mode

Use `@capConfig` to expose the endpoint to JavaScript, then instantiate `Cap` directly without rendering a visible widget:

```blade
@capConfig
@capScripts

<form method="POST" action="/contact">
    @csrf
    <input type="hidden" name="cap-token" id="cap-token">
    <button type="submit" id="submit-btn">Submit</button>
</form>

<script type="module">
document.getElementById('submit-btn').addEventListener('click', async (e) => {
    e.preventDefault();

    const cap = new Cap({ apiEndpoint: window.CAP_API_ENDPOINT });
    const { token } = await cap.solve();

    document.getElementById('cap-token').value = token;
    e.target.closest('form').submit();
});
</script>
```

`Cap` creates a hidden `cap-widget` element in the background and exposes a `solve()` method that returns `{ token }`. No visible widget is rendered.

`window.CAP_API_ENDPOINT` and `window.CAP_TOKEN_FIELD` are set by `@capConfig` from your PHP configuration, so you never need to hard-code the endpoint in JavaScript.

#### Iframe mode (strict CSP — no `'unsafe-eval'`)

When Cap's instrumentation is enabled, the widget requires `'unsafe-eval'` in `script-src`. If your page enforces a strict CSP, use `@capFrame` instead: it serves the widget in a dedicated iframe (`/cap-frame`) with its own permissive CSP, keeping the parent page clean.

```blade
{{-- In your layout: no @capScripts or @capStyles needed --}}

<form @submit.prevent="submitWithCap">
    @csrf
    @capFrame
    <button type="submit">Submit</button>
</form>
```

This renders:

```html
<input type="hidden" name="cap-token" id="cap-frame-token">
<iframe src="/cap-frame" id="cap-frame"
        style="width:0;height:0;border:0;overflow:hidden;"
        title="Cap CAPTCHA" aria-hidden="true"></iframe>
<script>
(function(){
  window.addEventListener('message', function(e) {
    if (e.origin !== window.location.origin) return;
    var _f = document.getElementById('cap-frame');
    if (!_f || e.source !== _f.contentWindow) return;
    if (!e.data || e.data.type !== 'cap:token') return;
    document.getElementById('cap-frame-token').value = e.data.token;
  });
  window.capSolve = function() {
    document.getElementById('cap-frame').contentWindow
      .postMessage({type: 'cap:start'}, window.location.origin);
  };
})();
</script>
```

**`/cap-frame` route** is registered automatically by the service provider. Its `Content-Security-Policy` header includes `'unsafe-eval'`, `'wasm-unsafe-eval'`, `blob:`, and `img-src data:` — everything Cap needs — while `frame-ancestors 'self'` prevents embedding from external origins.

**Token flow:**

```
Parent (strict CSP)                iframe /cap-frame (permissive CSP)
      │── postMessage(cap:start) ──►│  widget.solve()
      │◄── postMessage(cap:token) ──│  e.detail.token
      │  fills #cap-frame-token     │
```

**Programmatic trigger** — `@capFrame` exposes `window.capSolve()` on the parent page:

```javascript
// Trigger Cap resolution from Alpine, Vue, React, etc.
window.capSolve();

// Listen for the token (in addition to the hidden input auto-fill)
window.addEventListener('message', (e) => {
    if (e.origin !== window.location.origin) return;
    if (!e.data || e.data.type !== 'cap:token') return;
    // e.data.token is ready — pass it to your backend
    myForm.submit(e.data.token);
});
```

**With nonce** (if your CSP uses nonces):

```blade
@capFrame(Vite::cspNonce())
```

**Customising the route path** — set `CAP_FRAME_ROUTE` in `.env`:

```env
CAP_FRAME_ROUTE=captcha/frame
```

#### Multiple `@capFrame` instances

When a page hosts several forms, pass a unique `id` as the second argument:

The custom id must satisfy these constraints:
- pattern: `^[A-Za-z][A-Za-z0-9_-]*$` (starts with a letter; only letters, digits, hyphens, underscores)
- maximum length: **64 characters**

Passing an invalid id throws an `InvalidArgumentException` immediately at template render time.

```blade
{{-- Login form --}}
@capFrame(Vite::cspNonce(), 'login-cap')
{{-- without nonce: @capFrame(null, 'login-cap') --}}

{{-- Contact form --}}
@capFrame(Vite::cspNonce(), 'contact-cap')
```

Each instance gets its own namespaced trigger function instead of `window.capSolve`:

| Directive | iframe / input ids | trigger |
|-----------|-------------------|---------|
| `@capFrame` | `cap-frame` / `cap-frame-token` | `window.capSolve()` |
| `@capFrame($nonce)` | `cap-frame` / `cap-frame-token` | `window.capSolve()` |
| `@capFrame(null, 'login-cap')` | `login-cap` / `login-cap-token` | `window['capSolve_login-cap']()` |
| `@capFrame($nonce, 'login-cap')` | `login-cap` / `login-cap-token` | `window['capSolve_login-cap']()` |

```javascript
// Trigger Cap in the login form
window['capSolve_login-cap']();

// Trigger Cap in the contact form
window['capSolve_contact-cap']();
```

The hidden `<input>` is filled automatically when Cap resolves. `@capFrame` with the default id remains fully backward-compatible.

#### CSP nonce support

All directives accept an optional nonce for strict Content Security Policies:

```blade
@capConfig(Vite::cspNonce())
@capScripts(Vite::cspNonce())
@cap(Vite::cspNonce())
```

`@cap` passes the nonce as `data-cap-csp-nonce` on the widget element, which Cap uses internally for its workers and inline scripts.

#### CSP headers

Cap's widget runs WebAssembly locally. A strict CSP must account for this:

```
Content-Security-Policy:
  script-src 'nonce-{nonce}' 'strict-dynamic' 'wasm-unsafe-eval';
  connect-src 'self' https://your-cap-instance.example.com;
```

`'wasm-unsafe-eval'` — required for the WebAssembly proof-of-work computation.
`connect-src` — must include your Cap instance origin so the widget can reach `/challenge` and `/redeem`.

> **Instrumentation and strict CSP**
>
> Cap's optional **instrumentation** feature (enabled per site key in the Cap admin dashboard) runs
> fingerprinting code inside a sandboxed iframe. Since Cap v3.x this code calls `eval()` and
> `new Function()`, which are blocked by a `script-src` without `'unsafe-eval'`.
>
> If instrumentation is enabled and `'unsafe-eval'` is absent from your CSP, the widget will
> report an `[instr_timeout]` error and the Cap server will return HTTP 429, making every
> verification attempt fail.
>
> **Recommended workaround:** use [`@capFrame`](#iframe-mode-strict-csp--no-unsafe-eval) — the widget
> runs in a dedicated iframe with its own permissive CSP, leaving the parent page CSP strict.
>
> **Alternative workarounds:**
> - Disable instrumentation for the site key in the Cap admin dashboard
>   (`PUT /keys/:siteKey/config` with `{"instrumentation": false}`).
> - Add `'unsafe-eval'` to `script-src` (weakens the CSP of the parent page).
>
> This is a known upstream issue: [tiagozip/cap#268](https://github.com/tiagozip/cap/issues/268).

### Middleware

Protect any route by applying the `cap.verify` middleware:

```php
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('cap.verify');
```

Returns HTTP `422` with the message `Cap verification failed.` if the token is missing or invalid.

### Validation rule

Use `CapRule` inside a Form Request or an inline validator:

```php
use LaravelCap\Rules\CapRule;

public function rules(): array
{
    return [
        'cap-token' => ['required', new CapRule],
        // other fields...
    ];
}
```

### Facade

```php
use LaravelCap\Facades\Cap;

if (Cap::verify($request->input('cap-token'))) {
    // token is valid
}
```

### Service (dependency injection)

```php
use LaravelCap\Cap;

class ContactController extends Controller
{
    public function __construct(private readonly Cap $cap) {}

    public function store(Request $request): RedirectResponse
    {
        $this->cap->verifyOrFail($request->input('cap-token'));
        // ...
    }
}
```

`verifyOrFail()` throws a `CapVerificationException` if the token is invalid.


## Livewire integration

> **Requirement**: `livewire/livewire ^4.0`. Livewire is an optional dependency — the
> package works fully without it.

### Installation

```bash
composer require livewire/livewire
```

The `<x-cap::livewire>` component and `WithCap` trait are registered automatically by
`CapServiceProvider` when Livewire is detected.

### Component `<x-cap::livewire>`

Drop the component in any Livewire template and bind it to a string property via
`wire:model`:

```blade
<x-cap::livewire wire:model="capToken" />
```

With a CSP nonce:

```blade
<x-cap::livewire wire:model="capToken" :nonce="$nonce" />
```

The component renders the `<cap-widget>` inside a `wire:ignore` container (survives
Livewire's DOM morphing). The resolved token is synced to the bound Livewire property
via Alpine + `$wire.set()` without triggering an intermediate network request. The
widget automatically resets when the `cap-reset` browser event is dispatched.

### `WithCap` trait

Add `WithCap` to your Livewire component to get server-side Cap verification and
automatic widget reset on failure:

```php
use Livewire\Component;
use LaravelCap\Livewire\WithCap;

class ContactForm extends Component
{
    use WithCap;

    public string $capToken = '';
    public string $name = '';
    public string $email = '';

    public function submit(): void
    {
        // Validate other fields first — avoids consuming the Cap token
        // if a simple required/email check fails
        $this->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email',
        ]);

        // Verify the Cap token server-side (single-use, cleared on success)
        $this->validateCap();

        // Process the form...
        $this->dispatch('form-submitted');
    }

    public function render()
    {
        return view('livewire.contact-form');
    }
}
```

```blade
{{-- resources/views/livewire/contact-form.blade.php --}}
<form wire:submit="submit">
    <div>
        <input type="text" wire:model="name" placeholder="Name" />
    </div>
    <div>
        <input type="email" wire:model="email" placeholder="Email" />
    </div>

    <x-cap::livewire wire:model="capToken" />

    <button type="submit">Send</button>
</form>
```

#### Available methods

| Method | Description |
|---|---|
| `validateCap(string $property = 'capToken')` | Verifies the token via `CapRule`. Throws `ValidationException` if invalid. Clears the token on success. |
| `resetCap(string $property = 'capToken')` | Clears the token server-side and dispatches `cap-reset` to reset the widget. |

#### Automatic reset on failure

The trait's `exception()` Livewire lifecycle hook automatically calls `resetCap()` on
any `ValidationException`, even if the error comes from a different field. The widget is
always reset after a failed submission, forcing the user to solve the CAPTCHA again.

> **Validation order**: call `validateCap()` **after** `$this->validate()` for other
> fields. This way, if `name` or `email` fails, the Cap token is reset by the
> `exception()` hook but was never consumed by the Cap server. The user solves the
> CAPTCHA only once per successful submission.

### Security note

> `capToken` is a public property (required for `wire:model`). Its value is always
> verified server-side against the Cap API on every submission — a client-side
> modification cannot bypass verification. Never store a boolean "captcha solved" state
> in an unprotected public property.

## Inertia integration

No package-side changes are needed for Inertia: `CapRule` and the `cap.verify`
middleware apply to Inertia requests exactly like regular form requests.

### Vue 3 setup

Declare `cap-widget` as a custom element in your Vite config to prevent Vue from
warning about an unknown component:

```js
// vite.config.js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
    plugins: [
        vue({
            template: {
                compilerOptions: {
                    isCustomElement: (tag) => tag === 'cap-widget',
                },
            },
        }),
    ],
})
```

Pass `config('cap.endpoint')` to the front end via your Inertia shared data or as a
page prop:

```php
// In your controller:
return Inertia::render('ContactForm', [
    'capEndpoint' => config('cap.endpoint'),
]);
```

**Vue 3 example** (Inertia.js v2, `@inertiajs/vue3`):

```vue
<script setup>
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({ capEndpoint: String })

const form = useForm({
    name: '',
    email: '',
    'cap-token': '',
})

const capWidget = ref(null)

function onCapSolve(event) {
    form['cap-token'] = event.detail.token
}

function submit() {
    form.post(route('contact.store'), {
        onError() {
            // Always reset — the token is single-use
            form['cap-token'] = '';
            capWidget.value?.reset()
        },
    })
}
</script>

<template>
    <form @submit.prevent="submit">
        <input v-model="form.name" type="text" placeholder="Name" />
        <input v-model="form.email" type="email" placeholder="Email" />

        <cap-widget
            ref="capWidget"
            :data-cap-api-endpoint="props.capEndpoint"
            @solve="onCapSolve"
        />

        <button type="submit" :disabled="form.processing">Send</button>
    </form>
</template>
```

**React 19 example** (Inertia.js v2, `@inertiajs/react`):

```jsx
import { useForm } from '@inertiajs/react'
import { useRef, useEffect } from 'react'

export default function ContactForm({ capEndpoint }) {
    const capRef = useRef(null)
    const { data, setData, post, processing } = useForm({
        name: '',
        email: '',
        'cap-token': '',
    })

    useEffect(() => {
        const widget = capRef.current
        if (!widget) return
        const handleSolve = (e) => setData('cap-token', e.detail.token)
        widget.addEventListener('solve', handleSolve)
        return () => widget.removeEventListener('solve', handleSolve)
    }, [])

    function submit(e) {
        e.preventDefault()
        post(route('contact.store'), {
            onError() {
                // Always reset — the token is single-use
                setData('cap-token', '')
                capRef.current?.reset()
            },
        })
    }

    return (
        <form onSubmit={submit}>
            <input value={data.name} onChange={e => setData('name', e.target.value)} placeholder="Name" />
            <input value={data.email} onChange={e => setData('email', e.target.value)} placeholder="Email" />
            <cap-widget ref={capRef} data-cap-api-endpoint={capEndpoint} />
            <button type="submit" disabled={processing}>Send</button>
        </form>
    )
}
```

> **Note on React events**: React 19 does not forward custom DOM events (`solve`,
> `reset`) through JSX props. Use `addEventListener` via a `useEffect` + ref as shown
> above instead of `onSolve={…}`.

---

> #### Single-use token — Inertia gotcha
>
> Inertia intercepts HTTP 422 responses and surfaces field errors without a full page
> reload. The Cap token submitted with the form has already been **consumed** by the
> server — even if the error is on a completely different field (`name`, `email`, …).
>
> **You must always call `widget.reset()` and clear the token field in `onError`**, or
> the next submission will fail with an invalid-token error regardless of whether the
> CAPTCHA was solved correctly.


## Testing

```bash
composer install
./vendor/bin/phpunit
```

## License

MIT
