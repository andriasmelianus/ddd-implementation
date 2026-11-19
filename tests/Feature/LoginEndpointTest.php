<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\IamUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(IamUserSeeder::class);
    }

    public function test_an_active_user_can_log_in(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'active@example.com',
            'password' => '12345',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'user_id'])
            ->assertJsonPath('token_type', 'Bearer');

        $this->assertNotSame('', $response->json('access_token'));
    }

    public function test_an_unverified_user_is_forbidden_after_correct_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'unverified@example.com',
            'password' => '12345',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Silakan verifikasi email Anda terlebih dahulu.');
    }

    public function test_a_suspended_user_is_forbidden_after_correct_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'suspended@example.com',
            'password' => '12345',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Akun Anda sedang ditangguhkan.');
    }

    public function test_unknown_email_and_wrong_password_return_the_same_unauthorized_response(): void
    {
        $unknownEmail = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => '12345',
        ]);
        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            'email' => 'active@example.com',
            'password' => 'wrong-password',
        ]);

        $unknownEmail->assertUnauthorized();
        $wrongPassword->assertUnauthorized();
        $this->assertSame($unknownEmail->json('message'), $wrongPassword->json('message'));
    }

    public function test_invalid_input_returns_validation_errors(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'not-an-email',
            'password' => '',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
