<?php

declare(strict_types=1);

namespace MoveMoveApp\VKID;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\InvalidStateException;
use League\OAuth2\Client\Token\AccessToken;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

/**
 * VK ID OAuth 2.1 provider for Laravel Socialite.
 *
 * Features:
 * - PKCE with cache-backed verifier (no reliance on PHP session cookies).
 * - Configurable cache store/prefix/TTL via config('services.vkid.*').
 * - Robust token response normalization (prevents "string offset" issues).
 * - User profile via api.vk.com with configurable API version.
 * - Email/phone extraction from id_token (when scopes and app permissions allow).
 */
class Provider extends AbstractProvider
{
    public const IDENTIFIER = 'VKID';

    /**
     * Default scopes if not overridden via ->scopes([...]) or
     * globally via config('services.vkid.scopes').
     *
     * @var array<int, string>
     */
    protected $scopes = ['email'];

    /**
     * Allow global override of scopes via config('services.vkid.scopes')
     * and per-request override via Socialite::driver('vkid')->scopes([...]).
     *
     * @return array<int, string>
     */
    public function getScopes(): array
    {
        $cfg = config('services.vkid.scopes');

        if (is_array($cfg) && !empty($cfg)) {
            // If runtime ->scopes([...]) was set, $this->scopes already reflects it; otherwise use config.
            return $this->scopes ?: $cfg;
        }

        return $this->scopes;
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase('https://id.vk.ru/authorize', $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl(): string
    {
        return 'https://id.vk.ru/oauth2/auth';
    }

    /**
     * Build the authorization code fields and persist PKCE verifier.
     *
     * @param  string|null  $state
     * @return array<string, string>
     *
     * @throws \RuntimeException When cache store is unavailable/misconfigured.
     */
    protected function getCodeFields($state = null): array
    {
        $fields = [
            'client_id'             => $this->clientId,
            'redirect_uri'          => $this->redirectUrl,
            'scope'                 => $this->formatScopes($this->getScopes(), $this->scopeSeparator),
            'response_type'         => 'code',
            'state'                 => (string) $state,
            'code_challenge_method' => 'S256',
        ];

        // Generate PKCE pair
        $verifier  = $this->generateCodeVerifier();
        $challenge = $this->codeChallengeS256($verifier);

        // Configurable cache store/prefix/ttl
        $ttlMinutes = (int) config('services.vkid.pkce_ttl', 10);
        $expiresAt  = now()->addMinutes($ttlMinutes);
        $key        = $this->pkceCacheKey($state);
        $store      = (string) config('services.vkid.cache_store', '');

        try {
            if ($store !== '') {
                Cache::store($store)->put($key, $verifier, $expiresAt);
            } else {
                Cache::put($key, $verifier, $expiresAt);
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'VKID PKCE cache failure: unable to persist code_verifier. ' .
                'Check services.vkid.cache_store and your cache configuration.',
                previous: $e
            );
        }

        $fields['code_challenge'] = $challenge;

        // Note: we intentionally do not use base Socialite PKCE; we manage our own.
        return $fields;
    }

    /**
     * Build the token exchange fields and validate PKCE state/verifier.
     *
     * @param  string  $code
     * @return array<string, string>
     *
     * @throws InvalidStateException When state is missing/expired or user waited too long.
     * @throws \RuntimeException     When cache store is unavailable.
     */
    protected function getTokenFields($code): array
    {
        $state    = request()->query('state');
        $deviceId = request()->query('device_id');

        if (!$state) {
            // Be explicit about invalid callback shape.
            throw new InvalidStateException('VKID: missing "state" in callback.');
        }

        $key   = $this->pkceCacheKey((string) $state);
        $store = (string) config('services.vkid.cache_store', '');
        try {
            $verifier = $store !== '' ? Cache::store($store)->pull($key) : Cache::pull($key);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'VKID PKCE cache failure: unable to retrieve code_verifier. ' .
                'Ensure the configured cache store is available.',
                previous: $e
            );
        }

        if (!$verifier) {
            // UX-friendly message: user delayed > TTL, tab suspended, or cache evicted.
            throw new InvalidStateException(
                'VKID: authorization timed out or was cancelled. Please start sign-in again.'
            );
        }

        $fields = [
            'grant_type'    => 'authorization_code',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUrl,
            'code'          => (string) $code,
            'code_verifier' => $verifier,
        ];

        // device_id is optional but should be forwarded if present.
        if (!empty($deviceId)) {
            $fields['device_id'] = (string) $deviceId;
        }

        return $fields;
    }

    /**
     * Normalize the token response to an array (avoid string-offset issues).
     *
     * @param  string  $code
     * @return array<string, mixed>
     */
    public function getAccessTokenResponse($code): array
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'form_params' => $this->getTokenFields($code),
            'headers'     => ['Accept' => 'application/json'],
            'http_errors' => false,
            'timeout'     => 15,
        ]);

        $raw  = (string) $response->getBody();
        $json = json_decode($raw, true);

        return is_array($json) ? $json : ['_raw' => $raw];
    }

    /**
     * Fetch VK profile by access token.
     *
     * @param  AccessToken|string  $token
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $accessToken = $token instanceof AccessToken ? $token->getToken() : (string) $token;

        $apiVersion = (string) config('services.vkid.api_version', '5.199');

        $resp = Http::timeout(10)->get('https://api.vk.com/method/users.get', [
            'access_token' => $accessToken,
            'fields'       => 'id,screen_name,photo_200,first_name,last_name,domain',
            'v'            => $apiVersion,
        ])->json();

        $u = $resp['response'][0] ?? null;

        return $u ?: [];
    }

    /**
     * Map VK payload to Socialite User.
     *
     * Email/phone are extracted from id_token claims when available.
     *
     * @param  array<string, mixed>  $u
     * @return \SocialiteProviders\Manager\OAuth2\User
     */
    protected function mapUserToObject(array $u): User
    {
        $email = null;
        $phone = null;

        // accessTokenResponseBody is set by AbstractProvider; normalize to array.
        $body = $this->accessTokenResponseBody ?? null;
        if (is_string($body)) {
            $decoded = json_decode($body, true);
            $body    = is_array($decoded) ? $decoded : [];
        }

        if (is_array($body) && !empty($body['id_token'])) {
            $email = $this->emailFromIdToken($body['id_token']);
            $phone = $this->phoneFromIdToken($body['id_token']);
        }

        // Expose extras through raw payload (accessible via $user->user['phone']).
        if ($phone !== null) {
            $u['phone'] = $phone;
        }

        return (new User())->setRaw($u)->map([
            'id'       => (string) ($u['id'] ?? ''),
            'nickname' => $u['screen_name'] ?? null,
            'name'     => trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: null,
            'email'    => $email,                     // may be null
            'avatar'   => $u['photo_200'] ?? null,
        ]);
    }

    /**
     * Build PKCE cache key with configurable prefix.
     *
     * @param string|null $state
     * @return string
     */
    protected function pkceCacheKey(?string $state): string
    {
        $prefix = (string) config('services.vkid.cache_prefix', 'socialite:vkid:pkce:');

        return $prefix . ($state ?? 'no-state:' . Str::random(8));
    }

    /**
     * Generate RFC 7636–compatible code_verifier.
     *
     * @param int $length
     * @return string
     * @throws \Random\RandomException
     */
    protected function generateCodeVerifier(int $length = 64): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~';
        $s = '';

        for ($i = 0; $i < $length; $i++) {
            $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $s;
    }

    /**
     * Compute S256 code_challenge.
     *
     * @param string $verifier
     * @return string
     */
    protected function codeChallengeS256(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /**
     * Extract email from id_token (when present).
     *
     * @param string $jwt
     * @return string|null
     */
    protected function emailFromIdToken(string $jwt): ?string
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        return is_array($payload) ? ($payload['email'] ?? null) : null;
    }

    /**
     * Extract phone from id_token (when scope/permissions allow).
     * Common keys observed: "phone" or "phone_number".
     *
     * @param string $jwt
     * @return string|null
     */
    protected function phoneFromIdToken(string $jwt): ?string
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (!is_array($payload)) {
            return null;
        }

        return $payload['phone'] ?? ($payload['phone_number'] ?? null);
    }
}
