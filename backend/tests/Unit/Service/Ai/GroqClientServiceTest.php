<?php

namespace App\Tests\Unit\Service\Ai;

use App\Service\Ai\GroqClientService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GroqClientServiceTest extends TestCase
{
    public function testGenerateReturnsNullWhenApiKeyIsBlank(): void
    {
        $httpClient = new MockHttpClient(function () {
            $this->fail('HTTP client should never be called when no API key is configured.');
        });

        $service = new GroqClientService($httpClient, '', 'llama-3.3-70b-versatile');

        $this->assertNull($service->generate('hello'));
    }

    public function testGenerateReturnsTextOnSuccess(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse(json_encode([
                'choices' => [['message' => ['content' => 'Hello there!']]],
            ]), ['http_code' => 200])
        );

        $service = new GroqClientService($httpClient, 'test-key', 'llama-3.3-70b-versatile');

        $this->assertSame('Hello there!', $service->generate('hello'));
    }

    public function testGenerateReturnsNullOnNon200Status(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse(json_encode(['error' => 'invalid api key']), ['http_code' => 401])
        );

        $service = new GroqClientService($httpClient, 'bad-key', 'llama-3.3-70b-versatile');

        $this->assertNull($service->generate('hello'));
    }

    public function testGenerateReturnsNullOnNetworkFailure(): void
    {
        $httpClient = new MockHttpClient(function () {
            throw new \RuntimeException('Connection refused');
        });

        $service = new GroqClientService($httpClient, 'test-key', 'llama-3.3-70b-versatile');

        $this->assertNull($service->generate('hello'));
    }

    public function testGenerateReturnsNullWhenResponseShapeIsUnexpected(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse(json_encode(['choices' => []]), ['http_code' => 200])
        );

        $service = new GroqClientService($httpClient, 'test-key', 'llama-3.3-70b-versatile');

        $this->assertNull($service->generate('hello'));
    }

    public function testGenerateJsonDecodesValidJsonResponse(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse(json_encode([
                'choices' => [['message' => ['content' => '{"attributes": ["Color", "Size"]}']]],
            ]), ['http_code' => 200])
        );

        $service = new GroqClientService($httpClient, 'test-key', 'llama-3.3-70b-versatile');

        $this->assertSame(['attributes' => ['Color', 'Size']], $service->generateJson('suggest attributes'));
    }

    public function testGenerateJsonReturnsNullWhenContentIsNotValidJson(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse(json_encode([
                'choices' => [['message' => ['content' => 'not valid json at all']]],
            ]), ['http_code' => 200])
        );

        $service = new GroqClientService($httpClient, 'test-key', 'llama-3.3-70b-versatile');

        $this->assertNull($service->generateJson('suggest attributes'));
    }

    public function testGenerateSendsExpectedRequestPayload(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('POST', $method);
            $this->assertStringContainsString('api.groq.com', $url);
            $this->assertSame('Authorization: Bearer test-key', $options['normalized_headers']['authorization'][0] ?? null);

            $body = json_decode($options['body'], true);
            $this->assertSame('llama-3.3-70b-versatile', $body['model'] ?? null);
            $this->assertSame('hello', $body['messages'][0]['content'] ?? null);

            return new MockResponse(json_encode([
                'choices' => [['message' => ['content' => 'ok']]],
            ]), ['http_code' => 200]);
        });

        $service = new GroqClientService($httpClient, 'test-key', 'llama-3.3-70b-versatile');

        $this->assertSame('ok', $service->generate('hello'));
    }

    public function testGenerateJsonRequestsJsonObjectFormat(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $body = json_decode($options['body'], true);
            $this->assertSame('json_object', $body['response_format']['type'] ?? null);

            return new MockResponse(json_encode([
                'choices' => [['message' => ['content' => '{"ok": true}']]],
            ]), ['http_code' => 200]);
        });

        $service = new GroqClientService($httpClient, 'test-key', 'llama-3.3-70b-versatile');

        $this->assertSame(['ok' => true], $service->generateJson('give me json'));
    }
}
