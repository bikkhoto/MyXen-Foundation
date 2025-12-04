<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test user registration with valid data.
     *
     * @return void
     */
    public function test_user_can_register_with_valid_data(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'password' => 'TestPass123!@#$SecureXYZ',
            'password_confirmation' => 'TestPass123!@#$SecureXYZ',
        ];

        $response = $this->postJson('/api/v1/auth/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'is_verified',
                        'kyc_status',
                        'created_at',
                    ],
                    'token',
                    'token_type',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'user' => [
                        'name' => 'John Doe',
                        'email' => 'john.doe@example.com',
                        'role' => 'user',
                        'is_verified' => false,
                        'kyc_status' => 'pending',
                    ],
                    'token_type' => 'Bearer',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'name' => 'John Doe',
        ]);
    }

    /**
     * Test user registration fails with duplicate email.
     *
     * @return void
     */
    public function test_user_cannot_register_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'john.doe@example.com']);

        $userData = [
            'name' => 'Jane Doe',
            'email' => 'john.doe@example.com',
            'password' => 'TestPass123!@#$SecureXYZ',
            'password_confirmation' => 'TestPass123!@#$SecureXYZ',
        ];

        $response = $this->postJson('/api/v1/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test user can login with valid credentials.
     *
     * @return void
     */
    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'john.doe@example.com',
            'password' => 'TestPass123!@#$SecureXYZ',
        ]);

        $loginData = [
            'email' => 'john.doe@example.com',
            'password' => 'TestPass123!@#$SecureXYZ',
        ];

        $response = $this->postJson('/api/v1/auth/login', $loginData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'is_verified',
                        'kyc_status',
                    ],
                    'token',
                    'token_type',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'email' => 'john.doe@example.com',
                    ],
                    'token_type' => 'Bearer',
                ],
            ]);
    }

    /**
     * Test user login fails with invalid credentials.
     *
     * @return void
     */
    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'john.doe@example.com',
            'password' => 'TestPass123!@#$SecureXYZ',
        ]);

        $loginData = [
            'email' => 'john.doe@example.com',
            'password' => 'WrongPassword',
        ];

        $response = $this->postJson('/api/v1/auth/login', $loginData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test authenticated user can logout.
     *
     * @return void
     */
    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logout successful',
            ]);
    }

    /**
     * Test authenticated user can get their profile.
     *
     * @return void
     */
    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'name' => 'John Doe',
                        'email' => 'john.doe@example.com',
                    ],
                ],
            ]);
    }

    /**
     * Test unauthenticated user cannot access protected routes.
     *
     * @return void
     */
    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }
}
