<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin can login successfully.
     */
    public function test_admin_can_login_successfully(): void
    {
        // Create admin
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => 'password123',
            'role' => Admin::ROLE_ADMIN,
        ]);

        // Attempt login
        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'admin' => ['id', 'name', 'email', 'role'],
                    'token',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'admin' => [
                        'email' => 'admin@test.com',
                        'role' => 'admin',
                    ],
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    /**
     * Test admin login fails with invalid credentials.
     */
    public function test_admin_login_fails_with_invalid_credentials(): void
    {
        Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => 'password123',
            'role' => Admin::ROLE_ADMIN,
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials.',
            ]);
    }

    /**
     * Test admin can access protected routes when authenticated.
     */
    public function test_admin_can_access_protected_routes_when_authenticated(): void
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => 'password123',
            'role' => Admin::ROLE_ADMIN,
        ]);

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/admin/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'admin' => [
                        'id' => $admin->id,
                        'email' => 'admin@test.com',
                    ],
                ],
            ]);
    }

    /**
     * Test unauthenticated user cannot access admin protected routes.
     */
    public function test_unauthenticated_user_cannot_access_admin_protected_routes(): void
    {
        $response = $this->getJson('/api/v1/admin/me');

        $response->assertStatus(401);
    }

    /**
     * Test regular user token cannot access admin routes.
     */
    public function test_regular_user_token_cannot_access_admin_routes(): void
    {
        // Create a regular user (not admin)
        $user = \App\Models\User::create([
            'name' => 'Regular User',
            'email' => 'user@test.com',
            'password' => 'password123',
        ]);

        $userToken = $user->createToken('user-token')->plainTextToken;

        // Try to access admin route with user token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $userToken,
        ])->getJson('/api/v1/admin/me');

        // Should fail because using wrong guard
        $response->assertStatus(401);
    }

    /**
     * Test admin can logout successfully.
     */
    public function test_admin_can_logout_successfully(): void
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => 'password123',
            'role' => Admin::ROLE_ADMIN,
        ]);

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/admin/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Admin logged out successfully.',
            ]);

        // Verify token is revoked by checking database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => Admin::class,
            'tokenable_id' => $admin->id,
        ]);
    }

    /**
     * Test only superadmin can register new admins.
     */
    public function test_only_superadmin_can_register_new_admins(): void
    {
        $superadmin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'password' => 'password123',
            'role' => Admin::ROLE_SUPERADMIN,
        ]);

        $token = $superadmin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/admin/register', [
            'name' => 'New Admin',
            'email' => 'newadmin@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => Admin::ROLE_ADMIN,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Admin registered successfully.',
            ]);

        $this->assertDatabaseHas('admins', [
            'email' => 'newadmin@test.com',
            'role' => 'admin',
        ]);
    }

    /**
     * Test regular admin cannot register new admins.
     */
    public function test_regular_admin_cannot_register_new_admins(): void
    {
        $admin = Admin::create([
            'name' => 'Regular Admin',
            'email' => 'admin@test.com',
            'password' => 'password123',
            'role' => Admin::ROLE_ADMIN,
        ]);

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/admin/register', [
            'name' => 'New Admin',
            'email' => 'newadmin@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => Admin::ROLE_ADMIN,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }
}
