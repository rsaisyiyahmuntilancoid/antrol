<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ExternalAuthService
{
    protected string $apiUrl;
    protected ?string $spvUsername;
    protected ?string $spvPassword;
    protected ?string $spvToken;

    public function __construct()
    {
        $this->apiUrl = rtrim((string) config('external_auth.api_url'), '/');
        $this->spvUsername = config('external_auth.spv_username');
        $this->spvPassword = config('external_auth.spv_password');
        $this->spvToken = config('external_auth.spv_token');
    }

    /**
     * Get supervisor username
     *
     * @return string|null
     */
    public function getSpvUsername(): ?string
    {
        return $this->spvUsername;
    }

    /**
     * Get supervisor token
     *
     * @return string|null
     */
    public function getSpvToken(): ?string
    {
        return $this->spvToken;
    }

    /**
     * Check if token belongs to supervisor local session
     *
     * @param string $token
     * @return bool
     */
    public function isSpvToken(string $token): bool
    {
        if (empty($this->spvToken)) {
            return false;
        }

        return hash_equals($this->spvToken, $token);
    }

    /**
     * Check if given credentials match configured supervisor credentials
     *
     * @param string $username
     * @param string $password
     * @return bool
     */
    protected function isSpvCredentials(string $username, string $password): bool
    {
        if (empty($this->spvUsername) || empty($this->spvPassword) || empty($this->spvToken)) {
            return false;
        }

        $inputUsername = strtolower(trim($username));
        $targetUsername = strtolower(trim($this->spvUsername));

        return hash_equals($targetUsername, $inputUsername) && hash_equals($this->spvPassword, $password);
    }

    /**
     * Authenticate user with username and password
     *
     * @param string $username
     * @param string $password
     * @return array
     */
    public function login(string $username, string $password): array
    {
        // Bypass khusus supervisor (spv) tanpa perlu hit API eksternal
        if ($this->isSpvCredentials($username, $password)) {
            Log::info('Supervisor local bypass login authenticated', ['username' => $username]);

            return [
                'success' => true,
                'token' => $this->spvToken,
                'message' => 'Login berhasil.',
            ];
        }

        try {
            Log::info('Attempting external SSO login', ['username' => $username]);

            $response = Http::timeout(15)
                ->connectTimeout(10)
                ->post("{$this->apiUrl}/api/core/sign", [
                    'username' => $username,
                    'password' => $password,
                ]);

            if ($response->failed()) {
                Log::warning('External SSO login HTTP request failed', [
                    'status' => $response->status(),
                    'username' => $username,
                ]);

                $errorData = $response->json();
                if (is_array($errorData) && isset($errorData['message'])) {
                    return [
                        'success' => false,
                        'message' => $errorData['message'],
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'Gagal terhubung ke server autentikasi external (' . $response->status() . ').',
                ];
            }

            $data = $response->json();

            if (isset($data['statusCode']) && in_array((int)$data['statusCode'], [0, 200], true) && isset($data['resource']['accessToken'])) {
                return [
                    'success' => true,
                    'token' => $data['resource']['accessToken'],
                    'message' => $data['message'] ?? 'Login berhasil.',
                ];
            }

            Log::warning('External SSO login rejected credentials or bad response structure', [
                'username' => $username,
                'response' => $data,
            ]);

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Username atau password salah.',
            ];
        } catch (Exception $e) {
            Log::error('Error occurred during external SSO login', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat menghubungi server autentikasi.',
            ];
        }
    }

    /**
     * Fetch user profile information using the access token
     *
     * @param string $token
     * @return array
     */
    public function getUserInfo(string $token): array
    {
        if ($this->isSpvToken($token)) {
            return [
                'success' => true,
                'user' => [
                    'id' => 'spv',
                    'username' => $this->spvUsername ?: 'spv',
                    'name' => 'Supervisor',
                    'role' => 'supervisor',
                    'token' => $token,
                    'isExpired' => false,
                ],
            ];
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withHeaders([
                    'X-API-TOKEN' => $token
                ])
                ->get("{$this->apiUrl}/api/core/auth/info");

            if ($response->failed()) {
                Log::warning('Failed to fetch user info from external SSO', [
                    'status' => $response->status(),
                ]);
                return [
                    'success' => false,
                    'message' => 'Gagal mengambil informasi user dari server.',
                ];
            }

            $data = $response->json();

            if (isset($data['statusCode']) && in_array((int)$data['statusCode'], [0, 200], true) && isset($data['resource'])) {
                return [
                    'success' => true,
                    'user' => $data['resource'],
                ];
            }

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Informasi user tidak valid.',
            ];
        } catch (Exception $e) {
            Log::error('Error fetching user info from external SSO', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat menghubungi server autentikasi.',
            ];
        }
    }

    /**
     * Terminate the SSO session
     *
     * @param string $token
     * @return bool
     */
    public function logout(string $token): bool
    {
        if ($this->isSpvToken($token)) {
            Log::info('Supervisor local session logged out');
            return true;
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withHeaders([
                    'X-API-TOKEN' => $token
                ])
                ->post("{$this->apiUrl}/api/core/logout");

            if ($response->failed()) {
                Log::warning('Logout request to external SSO failed', [
                    'status' => $response->status(),
                ]);
                return false;
            }

            $data = $response->json();
            return isset($data['statusCode']) && in_array((int)$data['statusCode'], [0, 200], true);
        } catch (Exception $e) {
            Log::error('Error occurred during external SSO logout', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
