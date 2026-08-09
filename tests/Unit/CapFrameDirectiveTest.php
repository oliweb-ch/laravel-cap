<?php

namespace LaravelCap\Tests\Unit;

use Illuminate\Support\Facades\Blade;
use LaravelCap\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CapFrameDirectiveTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Rendu sans argument
    // -------------------------------------------------------------------------

    #[Test]
    public function capFrame_renders_hidden_input_with_configured_token_field(): void
    {
        $html = Blade::render('@capFrame');

        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('name="' . config('cap.token_field') . '"', $html);
        $this->assertStringContainsString('id="cap-frame-token"', $html);
    }

    #[Test]
    public function capFrame_renders_iframe_with_correct_id_and_src(): void
    {
        $html = Blade::render('@capFrame');

        $this->assertStringContainsString('id="cap-frame"', $html);
        $this->assertStringContainsString('src="' . route('cap.frame') . '"', $html);
    }

    #[Test]
    public function capFrame_renders_iframe_with_accessibility_attributes(): void
    {
        $html = Blade::render('@capFrame');

        $this->assertStringContainsString('title="Cap CAPTCHA"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    #[Test]
    public function capFrame_without_argument_contains_no_nonce_attribute(): void
    {
        $html = Blade::render('@capFrame');

        $this->assertStringNotContainsString('nonce=', $html);
    }

    // -------------------------------------------------------------------------
    // Vérification de la sécurité côté parent (postMessage)
    // -------------------------------------------------------------------------

    #[Test]
    public function capFrame_script_checks_message_origin(): void
    {
        $html = Blade::render('@capFrame');

        $this->assertStringContainsString('e.origin!==window.location.origin', $html);
    }

    #[Test]
    public function capFrame_script_exposes_capSolve_function(): void
    {
        $html = Blade::render('@capFrame');

        $this->assertStringContainsString('window.capSolve', $html);
    }

    // -------------------------------------------------------------------------
    // Rendu avec nonce
    // -------------------------------------------------------------------------

    #[Test]
    public function capFrame_with_nonce_adds_nonce_to_style_tag(): void
    {
        $html = Blade::render('@capFrame("abc123")');

        $this->assertStringContainsString('<style nonce="abc123">', $html);
    }

    #[Test]
    public function capFrame_with_nonce_adds_nonce_to_script_tag(): void
    {
        $html = Blade::render('@capFrame("abc123")');

        $this->assertStringContainsString('<script nonce="abc123">', $html);
    }

    #[Test]
    public function capFrame_with_nonce_still_renders_iframe_and_input(): void
    {
        $html = Blade::render('@capFrame("abc123")');

        $this->assertStringContainsString('id="cap-frame"', $html);
        $this->assertStringContainsString('type="hidden"', $html);
    }

    #[Test]
    public function capFrame_with_nonce_script_still_checks_origin_and_exposes_capSolve(): void
    {
        $html = Blade::render('@capFrame("abc123")');

        $this->assertStringContainsString('e.origin!==window.location.origin', $html);
        $this->assertStringContainsString('window.capSolve', $html);
    }

    // -------------------------------------------------------------------------
    // L1 — Vérification de event.source côté parent (1.8.7)
    // -------------------------------------------------------------------------

    #[Test]
    public function capFrame_listener_verifies_event_source_against_iframe_contentWindow(): void
    {
        $html = Blade::render('@capFrame');

        $this->assertStringContainsString('e.source!==', $html);
        // La référence à l'iframe est récupérée dynamiquement par getElementById
        $this->assertStringContainsString("getElementById('cap-frame')", $html);
        $this->assertStringContainsString('contentWindow', $html);
    }

    #[Test]
    public function capFrame_with_nonce_listener_also_verifies_event_source(): void
    {
        $html = Blade::render('@capFrame("abc123")');

        $this->assertStringContainsString('e.source!==', $html);
        $this->assertStringContainsString("getElementById('cap-frame')", $html);
        $this->assertStringContainsString('contentWindow', $html);
    }

    // -------------------------------------------------------------------------
    // Échappement du nonce (XSS)
    // -------------------------------------------------------------------------

    #[Test]
    public function capFrame_escapes_malicious_nonce_value(): void
    {
        $html = Blade::render('@capFrame("<script>alert(1)</script>")');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // -------------------------------------------------------------------------
    // Non-régression 1.9.0 — snapshot byte-identique au rendu 1.8.7
    // -------------------------------------------------------------------------

    private const SNAPSHOT_NO_ARGS =
        '<input type="hidden" name="cap-token" id="cap-frame-token">'
        . '<iframe src="http://localhost/cap-frame" id="cap-frame"'
        . ' style="width:0;height:0;border:0;overflow:hidden;"'
        . ' title="Cap CAPTCHA" aria-hidden="true"></iframe>'
        . '<script>(function(){'
        . 'window.addEventListener(\'message\',function(e){'
        . 'if(e.origin!==window.location.origin)return;'
        . 'var _f=document.getElementById(\'cap-frame\');'
        . 'if(!_f||e.source!==_f.contentWindow)return;'
        . 'if(!e.data||e.data.type!==\'cap:token\')return;'
        . 'document.getElementById(\'cap-frame-token\').value=e.data.token;'
        . '});'
        . 'window.capSolve=function(){'
        . 'document.getElementById(\'cap-frame\').contentWindow'
        . '.postMessage({type:\'cap:start\'},window.location.origin);'
        . '};'
        . '})();</script>';

    private const SNAPSHOT_WITH_NONCE =
        '<input type="hidden" name="cap-token" id="cap-frame-token">'
        . '<style nonce="nonce123">#cap-frame{width:0;height:0;border:0;overflow:hidden;}</style>'
        . '<iframe src="http://localhost/cap-frame" id="cap-frame"'
        . ' title="Cap CAPTCHA" aria-hidden="true"></iframe>'
        . '<script nonce="nonce123">(function(){'
        . 'window.addEventListener(\'message\',function(e){'
        . 'if(e.origin!==window.location.origin)return;'
        . 'var _f=document.getElementById(\'cap-frame\');'
        . 'if(!_f||e.source!==_f.contentWindow)return;'
        . 'if(!e.data||e.data.type!==\'cap:token\')return;'
        . 'document.getElementById(\'cap-frame-token\').value=e.data.token;'
        . '});'
        . 'window.capSolve=function(){'
        . 'document.getElementById(\'cap-frame\').contentWindow'
        . '.postMessage({type:\'cap:start\'},window.location.origin);'
        . '};'
        . '})();</script>';

    #[Test]
    public function capFrame_no_args_output_is_byte_identical_to_1_8_7_snapshot(): void
    {
        $this->assertSame(self::SNAPSHOT_NO_ARGS, Blade::render('@capFrame'));
    }

    #[Test]
    public function capFrame_with_nonce_only_output_is_byte_identical_to_1_8_7_snapshot(): void
    {
        $this->assertSame(self::SNAPSHOT_WITH_NONCE, Blade::render('@capFrame("nonce123")'));
    }

    // -------------------------------------------------------------------------
    // Identifiant personnalisé — @capFrame(null, 'login-cap')
    // -------------------------------------------------------------------------

    #[Test]
    public function capFrame_with_custom_id_uses_custom_id_on_iframe(): void
    {
        $html = Blade::render('@capFrame(null, \'login-cap\')');

        $this->assertStringContainsString('id="login-cap"', $html);
        $this->assertStringNotContainsString('id="cap-frame"', $html);
    }

    #[Test]
    public function capFrame_with_custom_id_uses_custom_id_on_input(): void
    {
        $html = Blade::render('@capFrame(null, \'login-cap\')');

        $this->assertStringContainsString('id="login-cap-token"', $html);
        $this->assertStringNotContainsString('id="cap-frame-token"', $html);
    }

    #[Test]
    public function capFrame_with_custom_id_does_not_register_window_capSolve_directly(): void
    {
        $html = Blade::render('@capFrame(null, \'login-cap\')');

        // La fonction globale window.capSolve (legacy) ne doit PAS apparaître
        $this->assertStringNotContainsString('window.capSolve=', $html);
    }

    #[Test]
    public function capFrame_with_custom_id_registers_prefixed_capSolve_function(): void
    {
        $html = Blade::render('@capFrame(null, \'login-cap\')');

        // window['capSolve_' + "login-cap"] doit apparaître
        $this->assertStringContainsString("window['capSolve_'+", $html);
        $this->assertStringContainsString(json_encode('login-cap'), $html);
    }

    #[Test]
    public function capFrame_with_custom_id_listener_targets_custom_iframe_by_id(): void
    {
        $html = Blade::render('@capFrame(null, \'login-cap\')');

        $this->assertStringContainsString('getElementById(' . json_encode('login-cap') . ')', $html);
    }

    // -------------------------------------------------------------------------
    // Identifiant personnalisé avec nonce — @capFrame('nonce123', 'contact-cap')
    // -------------------------------------------------------------------------

    #[Test]
    public function capFrame_with_custom_id_and_nonce_renders_nonce_on_style_and_script(): void
    {
        $html = Blade::render('@capFrame(\'nonce123\', \'contact-cap\')');

        $this->assertStringContainsString('<style nonce="nonce123">', $html);
        $this->assertStringContainsString('<script nonce="nonce123">', $html);
    }

    #[Test]
    public function capFrame_with_custom_id_and_nonce_uses_custom_id(): void
    {
        $html = Blade::render('@capFrame(\'nonce123\', \'contact-cap\')');

        $this->assertStringContainsString('id="contact-cap"', $html);
        $this->assertStringContainsString('id="contact-cap-token"', $html);
    }

    // -------------------------------------------------------------------------
    // Encodage XSS de l'identifiant personnalisé
    // -------------------------------------------------------------------------

    #[Test]
    public function capFrame_custom_id_is_html_escaped_in_attribute(): void
    {
        $html = Blade::render('@capFrame(null, \'<img src=x onerror=alert(1)>\')');

        // L'attribut id doit être HTML-échappé (e())
        $this->assertStringNotContainsString('id="<img', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    #[Test]
    public function capFrame_custom_id_closing_script_tag_is_safe_in_js_context(): void
    {
        $html = Blade::render('@capFrame(null, \'</script>\')');

        // json_encode échappe le / en \\/ dans les séquences </
        // → </script> ne peut pas fermer prématurément le bloc <script>
        $this->assertStringContainsString(json_encode('</script>'), $html);
        // Le tag </script> brut ne doit pas apparaître dans le bloc JS
        // (assertStringNotContainsString compte aussi ce qui est dans le HTML-encoded id,
        //  mais json_encode produit "<\/script>" donc pas de balise nue)
        $this->assertSame(
            0,
            substr_count($html, '</script>') - 1, // -1 pour le </script> terminal légal
            'Aucun </script> nu ne doit figurer dans le bloc JS'
        );
    }

    // -------------------------------------------------------------------------
    // Deux instances sur la même page
    // -------------------------------------------------------------------------

    #[Test]
    public function two_capFrame_instances_have_distinct_ids(): void
    {
        $html = Blade::render('@capFrame(null, \'login-cap\') @capFrame(null, \'contact-cap\')');

        $this->assertSame(1, substr_count($html, 'id="login-cap"'));
        $this->assertSame(1, substr_count($html, 'id="login-cap-token"'));
        $this->assertSame(1, substr_count($html, 'id="contact-cap"'));
        $this->assertSame(1, substr_count($html, 'id="contact-cap-token"'));
    }
}
