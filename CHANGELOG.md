# Changelog

All notable changes to this project are documented in this file.

Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
Versioning: [Semantic Versioning](https://semver.org/spec/v2.0.0.html)

---

## [Unreleased] — 1.8.5

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
- GitHub Actions CI: PHP 8.2/8.3/8.4 × Laravel 11/12/13 matrix, triggered on
  every push and pull request

### No functional change
No file under `src/`, `config/` or `resources/` was modified in this
release.

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

[Unreleased]: https://github.com/oliweb-ch/laravel-cap/compare/v1.8.4...HEAD
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
