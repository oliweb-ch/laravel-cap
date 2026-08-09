<?php

namespace LaravelCap\Tests\Unit;

use LaravelCap\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests du rendu de resources/views/frame.blade.php via la route cap.frame.
 *
 * Couvre les correctifs de sécurité 1.8.7 :
 *   M1 — Encodage JSON de CAP_ENDPOINT et CAP_CUSTOM_WASM_URL
 *   M2 — postMessage avec targetOrigin explicite (window.location.origin)
 */
class CapFrameViewTest extends TestCase
{
    // -------------------------------------------------------------------------
    // M1 — Encodage JSON (pattern identique à @capScripts)
    // -------------------------------------------------------------------------

    #[Test]
    public function frame_view_encodes_endpoint_as_json(): void
    {
        $content = $this->get(route('cap.frame'))->getContent();

        // json_encode() échappe les slashes (\/), contrairement à l'interpolation Blade naïve
        $this->assertStringContainsString(json_encode(config('cap.endpoint')), $content);
    }

    #[Test]
    public function frame_view_encodes_wasm_url_as_json(): void
    {
        $content = $this->get(route('cap.frame'))->getContent();

        // Même pattern que @capScripts : json_encode(asset(...))
        $this->assertStringContainsString(json_encode(asset('vendor/cap/cap_wasm_bg.wasm')), $content);
    }

    #[Test]
    public function frame_view_endpoint_with_apostrophe_does_not_break_js_string_context(): void
    {
        $this->app['config']->set('cap.endpoint', "https://cap.test/it's-key/");

        $content = $this->get(route('cap.frame'))->getContent();

        // JSON encode produit une chaîne double-quotée : l'apostrophe ne casse pas le contexte JS
        $this->assertStringContainsString(json_encode("https://cap.test/it's-key/"), $content);
        // La valeur brute dans un contexte single-quote NE doit PAS apparaître
        $this->assertStringNotContainsString("apiEndpoint: 'https://cap.test/it", $content);
    }

    #[Test]
    public function frame_view_endpoint_with_double_quote_is_json_encoded(): void
    {
        $this->app['config']->set('cap.endpoint', 'https://cap.test/key"x"/');

        $content = $this->get(route('cap.frame'))->getContent();

        // json_encode échappe les guillemets doubles en \"
        $this->assertStringContainsString(json_encode('https://cap.test/key"x"/'), $content);
    }

    #[Test]
    public function frame_view_endpoint_with_closing_script_tag_is_safe(): void
    {
        // Vecteur classique : </script> naïvement interpolé fermerait le bloc <script>
        $this->app['config']->set('cap.endpoint', 'https://cap.test/</script><script>alert(1)//');

        $content = $this->get(route('cap.frame'))->getContent();

        // json_encode échappe le / en \/ → </script> devient <\/script>, non reconnu comme fin de bloc
        $this->assertStringContainsString(
            json_encode('https://cap.test/</script><script>alert(1)//'),
            $content
        );
    }

    // -------------------------------------------------------------------------
    // M2 — postMessage avec origine explicite
    // -------------------------------------------------------------------------

    #[Test]
    public function frame_view_postMessage_uses_window_location_origin(): void
    {
        $content = $this->get(route('cap.frame'))->getContent();

        // window.location.origin apparaît 3 fois dans la vue :
        //   1. filtre d'origine dans le listener (e.origin !== window.location.origin)
        //   2. postMessage cap:token
        //   3. postMessage cap:error
        $this->assertSame(
            3,
            substr_count($content, 'window.location.origin'),
            'Les deux appels postMessage et le filtre d\'origine doivent utiliser window.location.origin'
        );
    }

    #[Test]
    public function frame_view_postMessage_does_not_use_wildcard_targetOrigin(): void
    {
        $content = $this->get(route('cap.frame'))->getContent();

        $this->assertStringNotContainsString("}, '*')", $content);
    }
}
