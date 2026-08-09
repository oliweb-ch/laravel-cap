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
        // L'endpoint est injecté via json_encode() (M1) — les slashes sont échappés en \/
        $this->assertStringContainsString(json_encode(config('cap.endpoint')), $response->getContent());
    }

    #[Test]
    public function cap_frame_body_contains_wasm_asset_url(): void
    {
        $response = $this->get(route('cap.frame'));
        // Même pattern que @capScripts : json_encode(asset(...)) (M1)
        $this->assertStringContainsString(json_encode(asset('vendor/cap/cap_wasm_bg.wasm')), $response->getContent());
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
        $csp = $this->get(route('cap.frame'))->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('connect-src', $csp);
    }

    // -------------------------------------------------------------------------
    // M4 — Resserrement de connect-src (1.8.7)
    // -------------------------------------------------------------------------

    #[Test]
    public function cap_frame_csp_connect_src_includes_self(): void
    {
        $csp = $this->get(route('cap.frame'))->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("connect-src 'self'", $csp);
    }

    #[Test]
    public function cap_frame_csp_connect_src_includes_endpoint_origin(): void
    {
        // Endpoint de test : https://cap.test/site-key/ → origine https://cap.test
        $csp = $this->get(route('cap.frame'))->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('https://cap.test', $csp);
    }

    #[Test]
    public function cap_frame_csp_connect_src_includes_port_when_present_in_endpoint(): void
    {
        $this->app['config']->set('cap.endpoint', 'https://cap.test:8443/site-key/');

        $csp = $this->get(route('cap.frame'))->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('https://cap.test:8443', $csp);
    }

    #[Test]
    public function cap_frame_csp_connect_src_falls_back_to_self_when_endpoint_is_invalid(): void
    {
        $this->app['config']->set('cap.endpoint', null);

        $csp = $this->get(route('cap.frame'))->headers->get('Content-Security-Policy');
        // Pas d'exception — retombe sur 'self' uniquement, sans autre origine
        $this->assertStringContainsString("connect-src 'self'", $csp);
        $this->assertStringNotContainsString("connect-src 'self' http", $csp);
    }

    #[Test]
    public function cap_frame_csp_connect_src_does_not_use_wildcard(): void
    {
        $csp = $this->get(route('cap.frame'))->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('connect-src *', $csp);
    }

    // -------------------------------------------------------------------------
    // Restriction du scheme dans connect-src (1.9.1)
    // -------------------------------------------------------------------------

    #[Test]
    public function cap_frame_csp_connect_src_falls_back_to_self_when_scheme_is_ftp(): void
    {
        $this->app['config']->set('cap.endpoint', 'ftp://cap.test/site-key/');

        $csp = $this->get(route('cap.frame'))->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("connect-src 'self'", $csp);
        $this->assertStringNotContainsString('ftp://cap.test', $csp);
    }

    #[Test]
    public function cap_frame_csp_connect_src_falls_back_to_self_when_scheme_is_javascript(): void
    {
        $this->app['config']->set('cap.endpoint', 'javascript://cap.test/');

        $csp = $this->get(route('cap.frame'))->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("connect-src 'self'", $csp);
        $this->assertStringNotContainsString('javascript://cap.test', $csp);
    }

    #[Test]
    public function cap_frame_csp_connect_src_accepts_http_scheme(): void
    {
        $this->app['config']->set('cap.endpoint', 'http://cap.test/site-key/');

        $csp = $this->get(route('cap.frame'))->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("connect-src 'self' http://cap.test", $csp);
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

