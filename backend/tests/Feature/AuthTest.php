<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_user_can_login_and_read_profile(): void
    {
        $this->seed();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@provadorvirtual.online',
            'password' => 'provador123',
        ]);

        $login->assertOk()
            ->assertJsonPath('user.email', 'demo@provadorvirtual.online')
            ->assertJsonStructure(['token']);

        $token = $login->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'demo@provadorvirtual.online')
            ->assertJsonCount(1, 'merchants');
    }
}
