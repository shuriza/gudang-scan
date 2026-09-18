<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_is_the_application_homepage(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Produk aktif')
            ->assertSee('Prioritas Restock');
    }
}
