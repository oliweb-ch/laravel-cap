# laravel-cap

A Laravel wrapper for [Cap](https://github.com/tiagozip/cap) — the self-hosted, privacy-friendly CAPTCHA alternative based on Proof-of-Work.

Cap works without tracking, cookies, or third-party services. This package integrates server-side token verification into Laravel through a service, a facade, a middleware, a validation rule, and Blade directives.

## Requirements

- PHP **^8.2**
- Laravel **11, 12, or 13**
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
| `cap_wasm_bg.wasm` | WebAssembly binary for proof-of-work (served locally, no CDN required) |
| `cap_wasm.js` | JS loader for the WASM module |

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
| `@capScripts` | Injects `window.CAP_CUSTOM_WASM_URL` + `<script type="module">` for the widget |
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

`@capScripts` always injects `window.CAP_CUSTOM_WASM_URL` pointing to the locally published WASM, so no external CDN is contacted at runtime.

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
    @capFrame(Vite::cspNonce())
    <button type="submit">Submit</button>
</form>
```

This renders:

```html
<input type="hidden" name="cap-token" id="cap-frame-token">
<iframe src="/cap-frame" id="cap-frame"
        style="border:none;overflow:hidden;width:300px;height:58px;"
        title="Cap CAPTCHA" loading="lazy"></iframe>
<script nonce="…">
(function(){
  window.addEventListener('message', function(e) {
    if (e.origin !== window.location.origin) return;
    if (!e.data || e.data.type !== 'cap:token') return;
    document.getElementById('cap-frame-token').value = e.data.token;
  });
  window.capSolve = function() {
    document.getElementById('cap-frame').contentWindow
      .postMessage({ type: 'cap:start' }, window.location.origin);
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

**Without nonce** (if your CSP does not use nonces):

```blade
@capFrame
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

## Testing

```bash
composer install
./vendor/bin/phpunit
```

## License

MIT
