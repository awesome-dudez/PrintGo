<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class KioskFrontendTest extends TestCase
{
    public function test_kiosk_homepage_renders_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('PrintGO');
        $response->assertSee('kiosk-logo');
        $response->assertSee('Admin Portal Login');
        $response->assertSee('/css/kiosk.css');
        $response->assertSee('/js/api.js');
        $response->assertSee('/js/kiosk.js');
        $response->assertSee('/js/admin.js');
    }

    public function test_admin_route_renders_successfully(): void
    {
        $response = $this->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('data-start-view="admin"', false);
        $response->assertSee('Admin Portal Login');
    }
}
