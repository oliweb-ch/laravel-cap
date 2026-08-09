<?php

namespace LaravelCap\Tests\Unit;

use LaravelCap\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CapFrameControllerTest extends TestCase
{
    #[Test]
    public function cap_frame_route_responds_with_200(): void
    {
        $this->get(route('cap.frame'))->assertStatus(200);
    }

    #[Test]
    public function cap_frame_response_content_type_is_html(): void
    {
        $response = $this->get(route('cap.frame'));
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
    }

    #[Test]
    public function cap_frame_body_contains_configured_endpoint(): void
    {
        $response = $this->get(route('cap.frame'));
        $this->assertStringContainsString(config('cap.endpoint'), $response->getContent());
    }

    #[Test]
    public function cap_frame_body_contains_wasm_asset_url(): void
    {
        $response = $this->get(route('cap.frame'));
        $this->assertStringContainsString('vendor/cap/cap_wasm_bg.wasm', $response->getContent());
    }

    #[Test]
    public function cap_frame_csp_header_contains_default_src(): void
    {
        $csp = $this->get(route('cap.frame'))->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('default-src', $csp);
    }

    #[Test]
    public function cap_frame_csp_header_contains_script_src(): void
    {
        $csp = $this->get(route('cap.frame'))->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('script-src', $csp);
    }

    #[Test]
    public function cap_frame_csp_header_contains_style_src(): void
    {
        $csp = $this->get(route('cap.frame'))->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('style-src', $csp);
    }

    #[Test]
    public function cap_frame_csp_header_contains_worker_src(): void
    {
        $csp = $this->get(route('cap.frame'))->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('worker-src', $csp);
    }

    #[Test]
    public function cap_frame_csp_header_contains_img_src(): void
    {
        $csp = $this->get(route('cap.frame'))->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('img-src', $csp);
    }

    #[Test]
    public function cap_frame_csp_header_contains_frame_ancestors(): void
    {
        $csp = $this->get(route('cap.frame'))->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('frame-ancestors', $csp);
    }

    #[Test]
    public function cap_frame_csp_header_contains_connect_src_key(): void
    {
        // La valeur de connect-src sera resserrée en 1.8.6 (actuellement `*`).
        // Ce test vérifie uniquement la présence de la clé, pas sa valeur.
        $csp = $this->get(route('cap.frame'))->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('connect-src', $csp);
    }

    #[Test]
    public function cap_frame_x_frame_options_is_sameorigin(): void
    {
        $this->get(route('cap.frame'))->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    #[Test]
    public function cap_frame_cache_control_is_no_store(): void
    {
        $cacheControl = $this->get(route('cap.frame'))->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
    }
}

