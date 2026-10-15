<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_unavailable(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Uninvited User',
            'email' => 'uninvited@example.test',
            'password' => 'uninvited-password',
            'password_confirmation' => 'uninvited-password',
        ])->assertNotFound();
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }
}
