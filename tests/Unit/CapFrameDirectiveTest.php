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
}
