<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_landing_page_renders_the_mar_vel_operations_portal(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Welcome')
                ->where('canLogin', true)
                ->where('canRegister', false)
                ->missing('laravelVersion')
                ->missing('phpVersion'));

        $this->assertTrue(Route::has('login'));
        $this->assertFalse(Route::has('register'));
    }

    public function test_public_registration_route_is_absent(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }
}
