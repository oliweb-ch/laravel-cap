# Changelog

Tous les changements notables de ce projet sont documentés dans ce fichier.

Format : [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
Versioning : [Semantic Versioning](https://semver.org/spec/v2.0.0.html)

---

## [Unreleased] — 1.8.5

### Ajouté
- Tests unitaires pour `CapFrameController` : route `cap.frame`, headers CSP,
  `X-Frame-Options`, `Cache-Control`, route personnalisée via `cap.frame_route`
- Tests unitaires pour la directive `@capFrame` : rendu sans nonce, rendu avec
  nonce, échappement XSS du nonce, vérification de la présence des contrôles
  `e.origin` et `window.capSolve` dans le script généré
- Tests unitaires pour la directive `@capConfig` : rendu sans nonce, rendu avec
  nonce, échappement XSS du nonce (ajoutés dans `CapBladeDirectivesTest`)
- Tests de cas limites pour `Cap::verify()` : endpoint sans slash final,
  réponse 200 sans clé `success`, réponse 200 avec `"success": null`,
  réponse 200 avec corps non-JSON (ajoutés dans `CapServiceTest`)

### Aucun changement fonctionnel
Aucun fichier de `src/`, `config/` ou `resources/` n'a été modifié dans
cette version.

---

## [1.8.4] — 2026-06-16

Pas de changement de code. Re-tag de publication.

## [1.8.3] — 2026-06-16

Pas de changement de code. Re-tag de publication.

## [1.8.2] — 2026-06-16

Pas de changement de code. Re-tag de publication.

## [1.8.1] — 2026-06-16

### Ajouté
- Mode programmatique iframe : Cap s'exécute en mode headless dans l'iframe
  (widget invisible), permettant d'initier `cap.solve()` par `postMessage`
  depuis la page parente sans afficher d'interface visuelle

## [1.8.0] — 2026-06-16

### Ajouté
- Directive `@capFrame` : mode iframe pour les politiques CSP strictes
  interdisant `unsafe-eval` dans la page parente ; l'iframe possède sa propre
  CSP permissive
- Communication bidirectionnelle par `postMessage` entre la page parente et
  l'iframe (`cap:start` → `cap:token`)
- Config `cap.frame_route` (env `CAP_FRAME_ROUTE`) pour personnaliser le
  chemin de la route iframe
- `CapFrameController` avec headers `Content-Security-Policy`, `X-Frame-Options`
  et `Cache-Control`

### Corrigé
- Ajout de `'unsafe-inline'` dans `script-src` de l'iframe pour les scripts
  inline du widget
- Ajout de `img-src data:` dans la CSP de l'iframe
- Remplacement du `style` inline de l'iframe par `<style nonce>` pour la
  compatibilité avec les politiques `style-src` strictes

## [1.7.2] — 2026-06-16

Pas de changement de code. Re-tag de publication.

## [1.7.1] — 2026-06-16

### Modifié
- Mise à jour des assets JS/WASM pour la compatibilité Cap v3.x
- Adaptation du service provider aux changements d'API Cap v3.x

### Documentation
- Documentation de la restriction CSP stricte et de l'incompatibilité avec
  l'instrumentation JS

## [1.6.1] — 2026-05-14

Pas de changement de code. Re-tag de publication.

## [1.6.0] — 2026-05-14

### Ajouté
- Directive `@capConfig` : injecte `window.CAP_API_ENDPOINT` et
  `window.CAP_TOKEN_FIELD` en JSON dans un `<script>` (supporte le nonce CSP)
- Injection de `window.CAP_CUSTOM_WASM_URL` pointant vers l'asset WASM local
  (`vendor/cap/cap_wasm_bg.wasm`) avant le chargement du module widget
- Directive `@capScripts` : nouvelle option de WASM local pour mode programmatique

### Documentation
- README complet : mode programmatique, WASM local, Laravel 13, `@capConfig`

## [1.5.6] — 2026-05-13

### Corrigé
- Correction de l'affichage CSS du widget en langue française

## [1.5.5] — 2026-05-07

### Corrigé
- Suppression du `!important` sur `display` de `.credits` pour permettre
  la personnalisation via `::part(attribution)`

## [1.5.4] — 2026-05-07

### Documentation
- Ajout d'une option commentée pour masquer le lien d'attribution Cap dans
  la feuille de styles personnalisée

## [1.5.3] — 2026-05-07

### Ajouté
- Charte graphique URG dans la CSS personnalisée (rouge `#ed1a23` + gris)

## [1.5.2] — 2026-05-07

### Corrigé
- Correction des noms de variables CSS du widget

## [1.5.1] — 2026-05-07

### Modifié
- Suppression du champ `version` statique dans `composer.json` (Packagist
  utilise les tags Git)

## [1.5.0] — 2026-05-07

### Ajouté
- Traductions i18n en anglais et en français (`cap::messages`) pour les
  messages d'erreur du middleware et de la règle de validation
- Feuille de styles CSS personnalisable (`vendor/cap/cap-widget.css`)
- Publication des traductions via `php artisan vendor:publish --tag=cap-lang`
- Publication des vues via `php artisan vendor:publish --tag=cap-views`

## [1.4.0] — 2026-05-07

### Ajouté
- Widget Cap patché bundlé localement (`vendor/cap/cap-widget.js`) avec
  support des politiques CSP strictes

## [1.3.0] — 2026-05-07

### Ajouté
- Support du nonce CSP sur la directive `@cap` : attribut
  `data-cap-csp-nonce` ajouté au `<cap-widget>` quand un nonce est fourni

## [1.2.0] — 2026-05-07

### Ajouté
- Support de Laravel 13

## [1.1.0] — 2026-05-07

### Ajouté
- Support du nonce CSP dans la directive `@capScripts` : attribut `nonce`
  sur les balises `<script>` générées ; injection de `window.CAP_SCRIPT_NONCE`

## [1.0.0] — 2026-05-07

### Ajouté
- `Cap` : service de vérification de token via `POST /siteverify`, avec
  `verify(string $token): bool` et `verifyOrFail(string $token): void`
- `CapServiceProvider` : enregistrement du singleton, des directives Blade
  et du middleware
- Directives Blade : `@cap`, `@capScripts`, `@capStyles`
- Middleware `cap.verify` (`VerifyCap`) : rejette les requêtes avec un token
  invalide (HTTP 422)
- Règle de validation `CapRule`
- Façade `Cap`
- Exception `CapVerificationException`
- Configuration `cap.php` : `endpoint`, `secret`, `token_field`, `timeout`,
  `fail_open`
- Mode `fail_open` : laisse passer les requêtes en cas d'erreur réseau ou
  5xx, tout en refusant les tokens explicitement invalides
- Suite de tests : `CapServiceTest`, `CapRuleTest`, `VerifyCapTest`
- Support Laravel 11 et 12, PHP 8.2+

---

[Unreleased]: https://github.com/oliweb/laravel-cap/compare/v1.8.4...HEAD
[1.8.4]: https://github.com/oliweb/laravel-cap/compare/v1.8.3...v1.8.4
[1.8.3]: https://github.com/oliweb/laravel-cap/compare/v1.8.2...v1.8.3
[1.8.2]: https://github.com/oliweb/laravel-cap/compare/v1.8.1...v1.8.2
[1.8.1]: https://github.com/oliweb/laravel-cap/compare/v1.8.0...v1.8.1
[1.8.0]: https://github.com/oliweb/laravel-cap/compare/v1.7.2...v1.8.0
[1.7.2]: https://github.com/oliweb/laravel-cap/compare/v1.7.1...v1.7.2
[1.7.1]: https://github.com/oliweb/laravel-cap/compare/v1.6.1...v1.7.1
[1.6.1]: https://github.com/oliweb/laravel-cap/compare/v1.6.0...v1.6.1
[1.6.0]: https://github.com/oliweb/laravel-cap/compare/v1.5.6...v1.6.0
[1.5.6]: https://github.com/oliweb/laravel-cap/compare/v1.5.5...v1.5.6
[1.5.5]: https://github.com/oliweb/laravel-cap/compare/v1.5.4...v1.5.5
[1.5.4]: https://github.com/oliweb/laravel-cap/compare/v1.5.3...v1.5.4
[1.5.3]: https://github.com/oliweb/laravel-cap/compare/v1.5.2...v1.5.3
[1.5.2]: https://github.com/oliweb/laravel-cap/compare/v1.5.1...v1.5.2
[1.5.1]: https://github.com/oliweb/laravel-cap/compare/v1.5.0...v1.5.1
[1.5.0]: https://github.com/oliweb/laravel-cap/compare/v1.4.0...v1.5.0
[1.4.0]: https://github.com/oliweb/laravel-cap/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/oliweb/laravel-cap/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/oliweb/laravel-cap/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/oliweb/laravel-cap/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/oliweb/laravel-cap/releases/tag/v1.0.0
