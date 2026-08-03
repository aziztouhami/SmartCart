<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Verifies a Google OAuth access token and returns the caller's profile.
 * Same "fetch external JSON, degrade to null on any failure" convention as
 * GroqClientService/TranslationService.
 */
class GoogleAuthService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $googleClientId = '',
    ) {
    }

    /**
     * Verifies the access token against Google's tokeninfo endpoint (audience
     * + email-verified checks), then fetches the full profile and merges it
     * with the tokeninfo response (tokeninfo may not include name).
     *
     * @throws \RuntimeException (HTTP code 401, caught by RuntimeExceptionListener)
     *                           when the token is invalid, was not issued for
     *                           this app, or the email isn't verified
     */
    public function verifyAndFetchProfile(string $accessToken): array
    {
        // Verify the token with Google's tokeninfo endpoint (also returns `aud` for audience check)
        $tokenInfo = $this->fetchGoogleJson('GET', 'https://oauth2.googleapis.com/tokeninfo', [
            'query' => ['access_token' => $accessToken],
        ]);

        if (!$tokenInfo) {
            throw new \RuntimeException('Invalid Google token', 401);
        }

        // Verify the token was issued for this app
        if ($this->googleClientId && ($tokenInfo['aud'] ?? '') !== $this->googleClientId
            && ($tokenInfo['azp'] ?? '') !== $this->googleClientId) {
            throw new \RuntimeException('Token was not issued for this application', 401);
        }

        if (($tokenInfo['email_verified'] ?? '') !== 'true' && ($tokenInfo['email_verified'] ?? false) !== true) {
            throw new \RuntimeException('Google email is not verified', 401);
        }

        // Fetch the full profile (tokeninfo may not include name)
        $profile = $this->fetchGoogleJson('GET', 'https://www.googleapis.com/oauth2/v3/userinfo', [
            'headers' => ['Authorization' => 'Bearer '.$accessToken],
        ]) ?? [];

        // Merge so email/verified from tokenInfo are always present
        return array_merge($tokenInfo, $profile);
    }

    /**
     * GET request to a Google API endpoint, decoded as JSON. Returns null on
     * any failure (network error, non-200 status, invalid JSON) — same
     * degrade-silently convention as GroqClientService/TranslationService.
     */
    private function fetchGoogleJson(string $method, string $url, array $options = []): ?array
    {
        try {
            $response = $this->httpClient->request($method, $url, array_merge(['timeout' => 10], $options));
            if (200 !== $response->getStatusCode()) {
                return null;
            }

            return $response->toArray(false);
        } catch (\Throwable) {
            return null;
        }
    }
}
