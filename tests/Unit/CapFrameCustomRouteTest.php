<?php

namespace LaravelCap\Tests\Unit;

use LaravelCap\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests de la route iframe avec cap.frame_route personnalisé.
 *
 * Dans un fichier séparé car defineEnvironment() doit être surchargé
 * avant le boot du CapServiceProvider (la route est enregistrée dans boot()).
 */
class CapFrameCustomRouteTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('cap.frame_route', 'my-custom-cap-route');
    }

    #[Test]
    public function custom_frame_route_config_changes_registered_route_path(): void
    {
        $this->get('/my-custom-cap-route')->assertStatus(200);
    }

    #[Test]
    public function default_cap_frame_path_is_not_registered_when_overridden(): void
    {
        $this->get('/cap-frame')->assertStatus(404);
    }
}
