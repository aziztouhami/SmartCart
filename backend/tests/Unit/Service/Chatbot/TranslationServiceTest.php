<?php

namespace App\Tests\Unit\Service\Chatbot;

use App\Service\Chatbot\TranslationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Same MockHttpClient/MockResponse pattern as OllamaClientServiceTest — no real
 * call to the MyMemory API is ever made.
 */
class TranslationServiceTest extends TestCase
{
    public function testToEnglishReturnsOriginalTextForBlankInput(): void
    {
        $httpClient = new MockHttpClient(function () {
            $this->fail('HTTP client should never be called for a blank/whitespace message.');
        });

        $service = new TranslationService($httpClient);

        $this->assertSame('   ', $service->toEnglish('   '));
    }

    public function testToEnglishReturnsOriginalTextForEmptyString(): void
    {
        $httpClient = new MockHttpClient(function () {
            $this->fail('HTTP client should never be called for an empty message.');
        });

        $service = new TranslationService($httpClient);

        $this->assertSame('', $service->toEnglish(''));
    }

    public function testToEnglishReturnsTranslatedTextOnSuccess(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse(json_encode([
                'responseStatus' => 200,
                'responseData' => ['translatedText' => 'Hello there'],
            ]), ['http_code' => 200])
        );

        $service = new TranslationService($httpClient);

        $this->assertSame('Hello there', $service->toEnglish('Bonjour'));
    }

    public function testToEnglishReturnsOriginalWhenResponseStatusIsNot200(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse(json_encode([
                'responseStatus' => 403,
                'responseData' => ['translatedText' => 'should not be used'],
            ]), ['http_code' => 200])
        );

        $service = new TranslationService($httpClient);

        $this->assertSame('Bonjour', $service->toEnglish('Bonjour'));
    }

    public function testToEnglishReturnsOriginalWhenTranslatedTextIsEmpty(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse(json_encode([
                'responseStatus' => 200,
                'responseData' => ['translatedText' => ''],
            ]), ['http_code' => 200])
        );

        $service = new TranslationService($httpClient);

        $this->assertSame('Bonjour', $service->toEnglish('Bonjour'));
    }

    public function testToEnglishReturnsOriginalWhenTranslationIsSameTextCaseInsensitive(): void
    {
        // MyMemory returns the input unchanged (aside from casing) when it detects
        // the source is already English — comparing case-insensitively avoids merging
        // duplicate keywords.
        $httpClient = new MockHttpClient(
            new MockResponse(json_encode([
                'responseStatus' => 200,
                'responseData' => ['translatedText' => 'HELLO WORLD'],
            ]), ['http_code' => 200])
        );

        $service = new TranslationService($httpClient);

        $this->assertSame('hello world', $service->toEnglish('hello world'));
    }

    public function testToEnglishDegradesGracefullyOnNetworkFailure(): void
    {
        $httpClient = new MockHttpClient(function () {
            throw new \RuntimeException('Connection refused');
        });

        $service = new TranslationService($httpClient);

        $this->assertSame('Bonjour le monde', $service->toEnglish('Bonjour le monde'));
    }

    public function testToEnglishDegradesGracefullyWhenResponseBodyIsMalformed(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse('not valid json', ['http_code' => 200])
        );

        $service = new TranslationService($httpClient);

        $this->assertSame('Bonjour', $service->toEnglish('Bonjour'));
    }

    public function testToEnglishDegradesGracefullyOnNon200HttpStatus(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse(json_encode(['error' => 'quota exceeded']), ['http_code' => 429])
        );

        $service = new TranslationService($httpClient);

        $this->assertSame('Bonjour', $service->toEnglish('Bonjour'));
    }

    public function testToEnglishSendsAutodetectToEnglishLangpair(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('GET', $method);
            $this->assertStringContainsString('api.mymemory.translated.net/get', $url);
            $this->assertStringContainsString('langpair=autodetect%7Cen', $url);
            $this->assertStringContainsString('q=Bonjour', $url);

            return new MockResponse(json_encode([
                'responseStatus' => 200,
                'responseData' => ['translatedText' => 'Hello'],
            ]), ['http_code' => 200]);
        });

        $service = new TranslationService($httpClient);

        $this->assertSame('Hello', $service->toEnglish('Bonjour'));
    }

    public function testToEnglishIncludesEmailParameterWhenConfigured(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertStringContainsString('de=user%40example.com', $url);

            return new MockResponse(json_encode([
                'responseStatus' => 200,
                'responseData' => ['translatedText' => 'Hello'],
            ]), ['http_code' => 200]);
        });

        $service = new TranslationService($httpClient, 'user@example.com');

        $service->toEnglish('Bonjour');
    }

    public function testToEnglishOmitsEmailParameterWhenNotConfigured(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertStringNotContainsString('de=', $url);

            return new MockResponse(json_encode([
                'responseStatus' => 200,
                'responseData' => ['translatedText' => 'Hello'],
            ]), ['http_code' => 200]);
        });

        $service = new TranslationService($httpClient);

        $service->toEnglish('Bonjour');
    }
}
