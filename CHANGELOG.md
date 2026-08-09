# Changelog

All notable changes to this project are documented in this file.

Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
Versioning: [Semantic Versioning](https://semver.org/spec/v2.0.0.html)

---
## [Unreleased]

### Fixed
- Removed a stray empty `config.policy` block left in `composer.json` after
  local testing of the 1.10.0 advisories workaround removal. No functional
  effect (an empty object doesn't change Composer's behavior), pure cleanup.

---
## [1.10.0] — 2026-08-09

### Removed
- **Laravel 11 support dropped.** Laravel 11 reached end of security support on
  12 March 2026. The minimum supported version is now Laravel 12.
  - `illuminate/support` and `illuminate/http` constraints narrowed from
    `^11.0|^12.0|^13.0` to `^12.0|^13.0`
  - `orchestra/testbench` (require-dev) narrowed from `^9.0|^10.0|^11.0` to
    `^10.0|^11.0`
  - CI matrix: `laravel: 11` column and its `testbench: "^9.0"` include entry
    removed; matrix now covers Laravel 12 and 13 only
  - The PHP 8.2 + Laravel 13 exclusion is unchanged (Laravel 13 still requires
    PHP ^8.3)
- **`policy.advisories.block false` workaround removed from CI.** After dropping
  Laravel 11, both remaining target versions (Laravel 12 / framework v12.65.0 and
  Laravel 13 / framework v13.24.0) were tested locally with `policy.advisories.block`
  set to `true` — `composer update` completed successfully with no security advisory
  blocking for either. The workaround has been removed; this was observed directly
  and not merely inferred from the Laravel 11 removal.

### No functional change
No file under `src/`, `config/`, `resources/`, or `tests/` was modified.
Existing installations on Laravel 11 are unaffected: Composer will continue to
resolve `oliweb/laravel-cap` to `1.9.1` (the last version compatible with
Laravel 11) without any error or action required on their part. Future releases
of this package will no longer be offered to Laravel 11 installations.

---
## [1.9.1] — 2026-08-09

### Fixed
- **`CapFrameController`** — `connectSrc()` now restricts the extracted scheme to
  `http` or `https` only. Any other scheme (`ftp:`, `javascript:`, `data:`, empty
  string, etc.) falls back to `connect-src 'self'` alone, consistent with the
  existing fallback for a missing or malformed host. `http` remains accepted
  (legitimate local-development endpoints).
- **`@capFrame` directive** — The custom `$id` argument (second parameter) is now
  validated at template render time against the pattern `^[A-Za-z][A-Za-z0-9_-]*$`
  with a maximum length of 64 characters. An invalid value throws an
  `\InvalidArgumentException` immediately with a message that includes the received
  value and the expected constraint. No silent normalisation is performed. The
  default id `'cap-frame'` satisfies the constraint and is unaffected.

### Tests
- `tests/Unit/CapFrameControllerTest.php`: +3 tests for the scheme restriction
  (`ftp:` fallback, `javascript:` fallback, `http:` accepted normally)
- `tests/Unit/CapFrameDirectiveTest.php`: +7 tests for the id validation (valid ids
  with underscore/digit, `'foo bar'` / `'../../etc'` / `'1login'` throw exception,
  64-character id passes, 65-character id throws); 2 existing XSS-escaping tests
  adapted — payloads that formerly reached the HTML/JS escaping layer now fail
  validation first (stronger guarantee)

---
## [1.9.0] — 2026-08-09

### Added
- `@capFrame` now accepts an optional second argument `$id` (default `'cap-frame'`),
  enabling multiple independent Cap widgets on the same page:
  - `@capFrame` / `@capFrame($nonce)` — unchanged behavior (backward-compatible)
  - `@capFrame(null, 'login-cap')` / `@capFrame($nonce, 'login-cap')` — renders an
    iframe with `id="login-cap"`, a hidden input with `id="login-cap-token"`, and
    registers `window['capSolve_login-cap']()` instead of `window.capSolve()`
- The `$id` value is escaped via `e()` in HTML attributes and `json_encode()` in
  JavaScript contexts, consistent with the existing security hardening in 1.8.7

### Tests
- `tests/Unit/CapFrameDirectiveTest.php`: +12 tests covering the new `$id` argument
  (non-regression snapshots, custom id without/with nonce, prefixed `capSolve` function,
  listener targeting, XSS escaping of `$id` in HTML and JS, two-instance scenario)

### Documentation
- `README.md`: added "Multiple `@capFrame` instances" section with usage table and JS
  examples

---
## [1.8.7] — 2026-08-09

### Security

- **M1** — `resources/views/frame.blade.php`: replaced naïve Blade interpolation
  (`{{ }}`) with `json_encode()` for `CAP_CUSTOM_WASM_URL` and `cap.endpoint`,
  consistent with the existing `@capScripts` directive. An endpoint or WASM URL
  containing `'`, `"`, or `</script>` could break the JavaScript string context
  or prematurely close the `<script>` block.
- **M2** — `resources/views/frame.blade.php`: replaced the wildcard `'*'`
  `targetOrigin` in both `postMessage` calls (`cap:token`, `cap:error`) with
  `window.location.origin`, preventing the Cap proof-of-work token from leaking
  to any cross-origin parent that might embed the iframe.
- **L1** — `@capFrame` directive (both nonce and no-nonce branches): the parent-
  side `message` listener now verifies `e.source === document.getElementById('cap-frame').contentWindow`
  before accepting a message, preventing a rogue same-origin frame from spoofing
  a `cap:token` event.
- **M4** — `CapFrameController`: `connect-src` in the iframe's
  `Content-Security-Policy` is now built dynamically from the scheme, host, and
  optional port of `config('cap.endpoint')` via `parse_url()`, combined with
  `'self'`. Falls back to `'self'` alone if the endpoint is absent or malformed.
  Replaces the previous `connect-src *`.

### Tests

- `tests/Unit/CapFrameViewTest.php` (new): 7 tests covering M1 JSON encoding
  (apostrophe, double-quote, `</script>` in endpoint) and M2 postMessage origin
- `tests/Unit/CapFrameDirectiveTest.php`: +2 tests for L1 `e.source` check
  (with and without nonce)
- `tests/Unit/CapFrameControllerTest.php`: +5 tests for M4 connect-src
  (includes `'self'`, includes endpoint origin, port handling, invalid endpoint
  fallback, no wildcard); updated 2 existing body-content assertions to
  `json_encode()` to match the M1 output format

---

## [1.8.6] — 2026-08-09

### Fixed
- Corrected `CHANGELOG.md`: v1.7.2 and v1.8.1–v1.8.4 had been collapsed into
  a single, mislabeled entry, and several tags were incorrectly marked as
  "no code change". Each tag's actual content is now documented separately
  (see note below)
- Bumped `actions/checkout` (v4 → v5) and `actions/cache` (v4 → v5) in CI to
  meet GitHub Actions' Node.js 24 runtime requirement

### Note
v1.8.5 was tagged one commit too early: the CHANGELOG correction above and
the CI action version bumps were merged into `main` immediately after the
tag was cut, and were therefore left out of the v1.8.5 release. Per this
project's policy of never amending a published tag, v1.8.6 exists
specifically to ship that missed content. No file under `src/`, `config/`,
or `resources/` was modified in this release either.

## [1.8.5] — 2026-08-09

### Added
- Unit tests for `CapFrameController`: `cap.frame` route, CSP headers,
  `X-Frame-Options`, `Cache-Control`, custom route via `cap.frame_route`
- Unit tests for the `@capFrame` directive: rendering without nonce,
  rendering with nonce, nonce XSS escaping, verification that the generated
  script checks `e.origin` and exposes `window.capSolve`
- Unit tests for the `@capConfig` directive: rendering without nonce,
  rendering with nonce, nonce XSS escaping (added to `CapBladeDirectivesTest`)
- Edge case tests for `Cap::verify()`: endpoint without trailing slash,
  200 response missing the `success` key, 200 response with
  `"success": null`, 200 response with a non-JSON body
- GitHub Actions CI: PHP 8.2/8.3/8.4 × Laravel 11/12/13 matrix (PHP 8.2 +
  Laravel 13 excluded, since Laravel 13 requires PHP 8.3+), triggered on
  every push and pull request

### No functional change
No file under `src/`, `config/` or `resources/` was modified in this
release. Note: as shipped, this tag's own `CHANGELOG.md` still contains the
pre-correction history described above; see v1.8.6.

---

## [1.8.4] — 2026-06-16

### Added
- Headless programmatic mode inside the iframe: the Cap widget now runs
  invisibly and resolves via `postMessage` from the parent page
  (`window.capSolve()`), with no visible UI inside the iframe

## [1.8.3] — 2026-06-16

### Fixed
- Replaced the iframe's inline style with `<style nonce>` for compatibility
  with strict `style-src` policies

### Documentation
- Documented `@capFrame`, iframe mode, bidirectional postMessage, and
  `CAP_FRAME_ROUTE`

## [1.8.2] — 2026-06-16

### Fixed
- Added `img-src data:` to the frame's CSP (the widget's inline SVG was
  being blocked)
- Bidirectional `postMessage`: the frame now listens for `cap:start` from
  the parent before calling `widget.solve()`

## [1.8.1] — 2026-06-16

### Fixed
- Added `'unsafe-inline'` to the frame's `script-src`, required for the
  widget's inline scripts

## [1.8.0] — 2026-06-16

### Added
- `@capFrame` directive: iframe mode for strict CSP policies that forbid
  `unsafe-eval` on the parent page; the iframe ships its own permissive CSP
- Bidirectional `postMessage` communication between the parent page and the
  iframe (`cap:start` → `cap:token`)
- `cap.frame_route` config option (env `CAP_FRAME_ROUTE`) to customize the
  iframe route path
- `CapFrameController` with `Content-Security-Policy`, `X-Frame-Options`
  and `Cache-Control` headers

## [1.7.2] — 2026-06-16

### Documentation
- Documented the strict CSP restriction and Cap v3.x instrumentation's
  incompatibility with `unsafe-eval` (`eval()` and `new Function()` blocked),
  with a documented workaround and a reference to tiagozip/cap#268
- Corrected the published assets table (added `cap_wasm.js`)
- Corrected the CSP headers section in the README (removed the unnecessary
  `worker-src blob:`, repositioned `'wasm-unsafe-eval'`, fixed `connect-src`)

## [1.7.1] — 2026-06-16

### Changed
- Updated JS/WASM assets for Cap v3.x compatibility (`@cap.js/widget`
  0.1.56, `@cap.js/wasm` 0.0.7)
- Adapted the service provider to Cap v3.x API changes (`->asJson()` for
  `/siteverify`)
- Injected `window.CAP_SCRIPT_NONCE` in `@capScripts` for widget 0.1.56's
  native nonce support

## [1.6.1] — 2026-05-14

### Documentation
- Complete README rewrite: programmatic mode, local WASM, Laravel 13,
  `@capConfig`

## [1.6.0] — 2026-05-14

### Added
- `@capConfig` directive: injects `window.CAP_API_ENDPOINT` and
  `window.CAP_TOKEN_FIELD` as JSON in a `<script>` tag (supports CSP nonce)
- Injection of `window.CAP_CUSTOM_WASM_URL` pointing to the local WASM asset
  (`vendor/cap/cap_wasm_bg.wasm`) before the widget module loads
- Local WASM support in `@capScripts` for programmatic mode

## [1.5.6] — 2026-05-13

### Fixed
- Fixed widget display width for French-language rendering

## [1.5.5] — 2026-05-07

### Fixed
- Removed `!important` from `.credits` `display` to allow customization via
  `::part(attribution)`

## [1.5.4] — 2026-05-07

### Documentation
- Added a commented-out option to hide the Cap attribution link in the
  custom stylesheet

## [1.5.3] — 2026-05-07

### Added
- URG color scheme in the custom CSS (red `#ed1a23` + gray)

## [1.5.2] — 2026-05-07

### Fixed
- Fixed widget CSS variable names

## [1.5.1] — 2026-05-07

### Changed
- Removed the static `version` field from `composer.json` (Packagist relies
  on Git tags)

## [1.5.0] — 2026-05-07

> This tag was deleted from Packagist and GitHub after being found corrupted.
> Its content is documented here for historical accuracy; no comparison link
> is available.

### Added
- English and French i18n translations (`cap::messages`) for middleware and
  validation rule error messages
- Customizable CSS stylesheet (`vendor/cap/cap-widget.css`)
- Translation publishing via `php artisan vendor:publish --tag=cap-lang`
- View publishing via `php artisan vendor:publish --tag=cap-views`

## [1.4.0] — 2026-05-07

### Added
- Bundled a patched Cap widget locally (`vendor/cap/cap-widget.js`), patched
  from the npm widget (CSP nonce on srcdoc, `white-space: normal`,
  `min-height: auto`)
- `@capScripts` now serves the local widget
  (`public/vendor/cap/cap-widget.js`) instead of jsDelivr, no external CDN
  dependency
- Asset publishing via `vendor:publish --tag=cap-assets`

## [1.3.0] — 2026-05-07

### Added
- CSP nonce support on the `@cap` directive: `data-cap-csp-nonce` attribute
  added to `<cap-widget>` when a nonce is provided

## [1.2.0] — 2026-05-07

### Added
- Laravel 13 support

## [1.1.0] — 2026-05-07

### Added
- CSP nonce support in the `@capScripts` directive: `nonce` attribute on the
  generated `<script>` tags; injection of `window.CAP_SCRIPT_NONCE`

## [1.0.0] — 2026-05-07

### Added
- `Cap`: token verification service via `POST /siteverify`, with
  `verify(string $token): bool` and `verifyOrFail(string $token): void`
- `CapServiceProvider`: registers the singleton, Blade directives, and
  middleware
- Blade directives: `@cap`, `@capScripts`, `@capStyles`
- `cap.verify` middleware (`VerifyCap`): rejects requests with an invalid
  token (HTTP 422)
- `CapRule` validation rule
- `Cap` facade
- `CapVerificationException`
- `cap.php` configuration: `endpoint`, `secret`, `token_field`, `timeout`,
  `fail_open`
- `fail_open` mode: lets requests through on network or 5xx errors, while
  still rejecting explicitly invalid tokens
- Test suite: `CapServiceTest`, `CapRuleTest`, `VerifyCapTest`
- Laravel 11 and 12 support, PHP 8.2+

---

[Unreleased]: https://github.com/oliweb-ch/laravel-cap/compare/v1.10.0...HEAD
[1.10.0]: https://github.com/oliweb-ch/laravel-cap/compare/v1.9.1...v1.10.0
[1.9.1]: https://github.com/oliweb-ch/laravel-cap/compare/v1.9.0...v1.9.1
[1.9.0]: https://github.com/oliweb-ch/laravel-cap/compare/v1.8.7...v1.9.0
[1.8.7]: https://github.com/oliweb-ch/laravel-cap/compare/v1.8.6...v1.8.7
[1.8.6]: https://github.com/oliweb-ch/laravel-cap/compare/v1.8.5...v1.8.6
[1.8.5]: https://github.com/oliweb-ch/laravel-cap/compare/v1.8.4...v1.8.5
[1.8.4]: https://github.com/oliweb-ch/laravel-cap/compare/v1.8.3...v1.8.4
[1.8.3]: https://github.com/oliweb-ch/laravel-cap/compare/v1.8.2...v1.8.3
[1.8.2]: https://github.com/oliweb-ch/laravel-cap/compare/v1.8.1...v1.8.2
[1.8.1]: https://github.com/oliweb-ch/laravel-cap/compare/v1.8.0...v1.8.1
[1.8.0]: https://github.com/oliweb-ch/laravel-cap/compare/v1.7.2...v1.8.0
[1.7.2]: https://github.com/oliweb-ch/laravel-cap/compare/v1.7.1...v1.7.2
[1.7.1]: https://github.com/oliweb-ch/laravel-cap/compare/v1.6.1...v1.7.1
[1.6.1]: https://github.com/oliweb-ch/laravel-cap/compare/v1.6.0...v1.6.1
[1.6.0]: https://github.com/oliweb-ch/laravel-cap/compare/v1.5.6...v1.6.0
[1.5.6]: https://github.com/oliweb-ch/laravel-cap/compare/v1.5.5...v1.5.6
[1.5.5]: https://github.com/oliweb-ch/laravel-cap/compare/v1.5.4...v1.5.5
[1.5.4]: https://github.com/oliweb-ch/laravel-cap/compare/v1.5.3...v1.5.4
[1.5.3]: https://github.com/oliweb-ch/laravel-cap/compare/v1.5.2...v1.5.3
[1.5.2]: https://github.com/oliweb-ch/laravel-cap/compare/v1.5.1...v1.5.2
[1.5.1]: https://github.com/oliweb-ch/laravel-cap/compare/v1.5.0...v1.5.1
[1.4.0]: https://github.com/oliweb-ch/laravel-cap/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/oliweb-ch/laravel-cap/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/oliweb-ch/laravel-cap/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/oliweb-ch/laravel-cap/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/oliweb-ch/laravel-cap/releases/tag/v1.0.0
