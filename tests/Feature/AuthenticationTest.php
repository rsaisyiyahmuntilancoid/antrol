<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class AuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear rate limiter before each test
        RateLimiter::clear('login-attempts:' . request()->ip());
    }

    /**
     * Test login page is accessible.
     */
    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Antrol System');
    }

    /**
     * Test root landing page is accessible without authentication.
     */
    public function test_welcome_page_is_accessible_without_auth(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * Test protected web routes redirect to login when unauthenticated.
     */
    public function test_protected_pages_redirect_to_login_when_unauthenticated(): void
    {
        $response = $this->get('/monitoring');
        $response->assertRedirect('/login');

        $response = $this->get('/mobilejkn/referensi-pendaftaran');
        $response->assertRedirect('/login');

        $response = $this->get('/mobilejkn/taskid-logs');
        $response->assertRedirect('/login');

        $response = $this->get('/bpjs-logs');
        $response->assertRedirect('/login');
    }

    /**
     * Test protected API routes return 401 when unauthenticated.
     */
    public function test_protected_api_routes_return_401_when_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/monitoring/analytics');

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => 'Sesi Anda telah berakhir atau belum terautentikasi. Silakan login kembali.'
        ]);
    }

    /**
     * Test login success with mock external API.
     */
    public function test_login_success_with_valid_credentials(): void
    {
        $baseUrl = config('external_auth.api_url');
        
        // Mock external authentication responses
        Http::fake([
            $baseUrl . '/api/core/sign' => Http::response([
                'statusCode' => 0,
                'message' => 'Success',
                'resource' => [
                    'accessToken' => 'mocked-jwt-token'
                ]
            ], 200),
            $baseUrl . '/api/core/auth/info' => Http::response([
                'statusCode' => 0,
                'message' => 'Success',
                'resource' => [
                    'id' => '123',
                    'username' => 'johndoe',
                    'token' => 'mocked-jwt-token',
                    'isExpired' => false,
                    'expiredAt' => (time() + 3600) * 1000,
                    'createdAt' => '2026-07-25T04:49:46.678Z',
                    'updatedAt' => '2026-07-25T04:49:46.678Z'
                ]
            ], 200)
        ]);

        $response = $this->from('/login')->post('/login', [
            'username' => 'johndoe',
            'password' => 'secretpassword123'
        ]);

        $response->assertRedirect('/');
        $this->assertEquals('mocked-jwt-token', session('auth_token'));
        $this->assertEquals('johndoe', session('auth_user.username'));
    }

    /**
     * Test login failure with invalid credentials.
     */
    public function test_login_fails_with_invalid_credentials(): void
    {
        $baseUrl = config('external_auth.api_url');

        Http::fake([
            $baseUrl . '/api/core/sign' => Http::response([
                'statusCode' => 1,
                'message' => 'Invalid username or password',
                'resource' => null
            ], 401)
        ]);

        $response = $this->from('/login')->post('/login', [
            'username' => 'johndoe',
            'password' => 'wrongpassword'
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['auth_error']);
        $this->assertNull(session('auth_token'));
    }

    /**
     * Test login rate limiting.
     */
    public function test_login_rate_limiting_after_five_failed_attempts(): void
    {
        $baseUrl = config('external_auth.api_url');

        Http::fake([
            $baseUrl . '/api/core/sign' => Http::response([
                'statusCode' => 1,
                'message' => 'Invalid username or password'
            ], 401)
        ]);

        // Attempt login 5 times
        for ($i = 0; $i < 5; $i++) {
            $response = $this->from('/login')->post('/login', [
                'username' => 'johndoe',
                'password' => 'wrongpassword'
            ]);
            $response->assertRedirect('/login');
        }

        // 6th attempt should fail due to rate limiting
        $response = $this->from('/login')->post('/login', [
            'username' => 'johndoe',
            'password' => 'wrongpassword'
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['auth_error']);
        
        $errors = session('errors')->getBag('default');
        $this->assertTrue(str_contains($errors->first('auth_error'), 'Terlalu banyak percobaan login'));
    }

    /**
     * Test logout clears session and calls external API.
     */
    public function test_logout_clears_session(): void
    {
        $baseUrl = config('external_auth.api_url');

        Http::fake([
            $baseUrl . '/api/core/logout' => Http::response([
                'statusCode' => 0,
                'message' => 'Logged out successfully'
            ], 200)
        ]);

        // Mock an active session
        session(['auth_token' => 'active-token']);

        $response = $this->post('/logout');

        $response->assertRedirect('/login');
        $this->assertNull(session('auth_token'));
    }

    /**
     * Test API authentication using Bearer and X-API-TOKEN headers.
     */
    public function test_api_token_header_authentication(): void
    {
        $baseUrl = config('external_auth.api_url');

        Http::fake([
            $baseUrl . '/api/core/auth/info' => Http::response([
                'statusCode' => 200,
                'message' => 'Success',
                'resource' => [
                    'id' => '123',
                    'username' => 'johndoe'
                ]
            ], 200)
        ]);

        // Test with Authorization Bearer header
        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-jwt-token',
            'Accept' => 'application/json'
        ])->getJson('/api/v1/mobilejkn/task-id-logs');

        $response->assertStatus(200);

        // Test with X-API-TOKEN header
        $response = $this->withHeaders([
            'X-API-TOKEN' => 'valid-jwt-token',
            'Accept' => 'application/json'
        ])->getJson('/api/v1/mobilejkn/task-id-logs');

        $response->assertStatus(200);
    }

    /**
     * Test API login returns JSON response with token.
     */
    public function test_api_login_returns_json_token(): void
    {
        $baseUrl = config('external_auth.api_url');

        Http::fake([
            $baseUrl . '/api/core/sign' => Http::response([
                'statusCode' => 200,
                'message' => 'Success',
                'resource' => [
                    'accessToken' => 'api-jwt-token-123'
                ]
            ], 200),
            $baseUrl . '/api/core/auth/info' => Http::response([
                'statusCode' => 200,
                'message' => 'Success',
                'resource' => [
                    'id' => '999',
                    'username' => 'api_user'
                ]
            ], 200)
        ]);

        $response = $this->postJson('/api/v1/login', [
            'username' => 'api_user',
            'password' => 'api_password'
        ]);

        $response->assertStatus(200);
        $response->assertCookie(config('session.cookie'));
        $response->assertJson([
            'success' => true,
            'token' => 'api-jwt-token-123',
            'user' => [
                'username' => 'api_user'
            ]
        ]);
    }

    /**
     * Test SPV login succeeds without calling external API.
     */
    public function test_spv_login_succeeds_without_calling_external_api(): void
    {
        Http::fake();

        $response = $this->from('/login')->post('/login', [
            'username' => 'spv',
            'password' => 'b!sm!llah*',
        ]);

        $response->assertRedirect('/');
        $this->assertEquals(\App\Services\ExternalAuthService::SPV_TOKEN, session('auth_token'));
        $this->assertEquals('spv', session('auth_user.username'));

        // Verify that external HTTP API was NOT called
        Http::assertNothingSent();
    }

    /**
     * Test SPV login via API succeeds without calling external API.
     */
    public function test_spv_api_login_succeeds_without_calling_external_api(): void
    {
        Http::fake();

        $response = $this->postJson('/api/v1/login', [
            'username' => 'spv',
            'password' => 'b!sm!llah*',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'token' => \App\Services\ExternalAuthService::SPV_TOKEN,
            'user' => [
                'username' => 'spv',
            ],
        ]);

        // Verify that external HTTP API was NOT called
        Http::assertNothingSent();
    }

    /**
     * Test SPV with wrong password falls back to external API.
     */
    public function test_spv_with_wrong_password_falls_back_to_external_api(): void
    {
        $baseUrl = config('external_auth.api_url');

        Http::fake([
            $baseUrl . '/api/core/sign' => Http::response([
                'statusCode' => 1,
                'message' => 'Username atau password salah',
            ], 401),
        ]);

        $response = $this->from('/login')->post('/login', [
            'username' => 'spv',
            'password' => 'wrongpassword',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['auth_error']);
        $this->assertNull(session('auth_token'));

        // Verify external sign was attempted
        Http::assertSent(function ($request) use ($baseUrl) {
            return $request->url() === $baseUrl . '/api/core/sign';
        });
    }

    /**
     * Test SPV logout clears session without calling external API.
     */
    public function test_spv_logout_clears_session_without_calling_external_api(): void
    {
        Http::fake();

        session([
            'auth_token' => \App\Services\ExternalAuthService::SPV_TOKEN,
            'auth_user' => [
                'id' => 'spv',
                'username' => 'spv',
            ],
        ]);

        $response = $this->post('/logout');

        $response->assertRedirect('/login');
        $this->assertNull(session('auth_token'));
        $this->assertNull(session('auth_user'));

        // External logout API should not be called for SPV
        Http::assertNothingSent();
    }
}
