<?php

namespace App\Tests\Unit\Service;

use App\Service\GoogleAuthService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GoogleAuthServiceTest extends TestCase
{
    public function testThrowsWhenTokenInfoRequestFails(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse('', ['http_code' => 400])
        );
        $service = new GoogleAuthService($httpClient, 'my-client-id');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid Google token');
        $this->expectExceptionCode(401);

        $service->verifyAndFetchProfile('bad-token');
    }

    public function testThrowsWhenAudienceDoesNotMatchClientId(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse(json_encode([
                'aud' => 'someone-elses-client-id',
                'email_verified' => 'true',
            ]), ['http_code' => 200])
        );
        $service = new GoogleAuthService($httpClient, 'my-client-id');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Token was not issued for this application');
        $this->expectExceptionCode(401);

        $service->verifyAndFetchProfile('some-token');
    }

    public function testAcceptsAzpMatchingClientIdWhenAudDoesNotMatch(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            if (str_contains($url, 'tokeninfo')) {
                return new MockResponse(json_encode([
                    'aud' => 'something-else',
                    'azp' => 'my-client-id',
                    'email_verified' => 'true',
                    'email' => 'user@example.com',
                ]), ['http_code' => 200]);
            }

            return new MockResponse(json_encode(['given_name' => 'Jane']), ['http_code' => 200]);
        });
        $service = new GoogleAuthService($httpClient, 'my-client-id');

        $profile = $service->verifyAndFetchProfile('some-token');

        $this->assertSame('user@example.com', $profile['email']);
    }

    public function testThrowsWhenEmailIsNotVerified(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse(json_encode([
                'aud' => 'my-client-id',
                'email_verified' => 'false',
            ]), ['http_code' => 200])
        );
        $service = new GoogleAuthService($httpClient, 'my-client-id');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Google email is not verified');
        $this->expectExceptionCode(401);

        $service->verifyAndFetchProfile('some-token');
    }

    public function testAcceptsBooleanTrueForEmailVerified(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            if (str_contains($url, 'tokeninfo')) {
                return new MockResponse(json_encode([
                    'aud' => 'my-client-id',
                    'email_verified' => true,
                    'email' => 'user@example.com',
                ]), ['http_code' => 200]);
            }

            return new MockResponse(json_encode([]), ['http_code' => 200]);
        });
        $service = new GoogleAuthService($httpClient, 'my-client-id');

        $profile = $service->verifyAndFetchProfile('some-token');

        $this->assertSame('user@example.com', $profile['email']);
    }

    public function testMergesTokenInfoAndProfileResponses(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            if (str_contains($url, 'tokeninfo')) {
                return new MockResponse(json_encode([
                    'aud' => 'my-client-id',
                    'email_verified' => 'true',
                    'email' => 'user@example.com',
                ]), ['http_code' => 200]);
            }

            return new MockResponse(json_encode([
                'given_name' => 'Jane',
                'family_name' => 'Doe',
            ]), ['http_code' => 200]);
        });
        $service = new GoogleAuthService($httpClient, 'my-client-id');

        $profile = $service->verifyAndFetchProfile('some-token');

        $this->assertSame('user@example.com', $profile['email']);
        $this->assertSame('Jane', $profile['given_name']);
        $this->assertSame('Doe', $profile['family_name']);
    }

    public function testSkipsAudienceCheckWhenNoClientIdConfigured(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            if (str_contains($url, 'tokeninfo')) {
                return new MockResponse(json_encode([
                    'aud' => 'anything',
                    'email_verified' => 'true',
                    'email' => 'user@example.com',
                ]), ['http_code' => 200]);
            }

            return new MockResponse(json_encode([]), ['http_code' => 200]);
        });
        $service = new GoogleAuthService($httpClient, ''); // no client id configured

        $profile = $service->verifyAndFetchProfile('some-token');

        $this->assertSame('user@example.com', $profile['email']);
    }
}
