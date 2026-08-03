<?php

namespace App\Tests\Unit\Service\Ai;

use App\Service\Ai\OllamaClientService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * First precedent in this repo for testing an AI-calling service — uses
 * Symfony's MockHttpClient/MockResponse (ships with symfony/http-client,
 * already a dependency) instead of hitting a real Ollama server.
 */
class OllamaClientServiceTest extends TestCase
{
    public function testGenerateReturnsNullWhenModelIsBlank(): void
    {
        $httpClient = new MockHttpClient(function () {
            $this->fail('HTTP client should never be called when no model is configured.');
        });

        $service = new OllamaClientService($httpClient, 'http://ollama:11434', '', 120);

        $this->assertNull($service->generate('hello'));
    }

    public function testGenerateReturnsTextOnSuccess(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse(json_encode(['response' => 'Hello there!', 'done' => true]), ['http_code' => 200])
        );

        $service = new OllamaClientService($httpClient, 'http://ollama:11434', 'qwen3:8b', 120);

        $this->assertSame('Hello there!', $service->generate('hello'));
    }

    public function testGenerateReturnsNullOnNon200Status(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse(json_encode(['error' => 'model not found']), ['http_code' => 404])
        );

        $service = new OllamaClientService($httpClient, 'http://ollama:11434', 'unknown-model', 120);

        $this->assertNull($service->generate('hello'));
    }

    public function testGenerateReturnsNullOnNetworkFailure(): void
    {
        $httpClient = new MockHttpClient(function () {
            throw new \RuntimeException('Connection refused');
        });

        $service = new OllamaClientService($httpClient, 'http://ollama:11434', 'qwen3:8b', 120);

        $this->assertNull($service->generate('hello'));
    }

    public function testGenerateJsonDecodesValidJsonResponse(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse(json_encode(['response' => '{"healthScore": 80, "anomalies": []}']), ['http_code' => 200])
        );

        $service = new OllamaClientService($httpClient, 'http://ollama:11434', 'qwen3:8b', 120);
        $result = $service->generateJson('analyze this');

        $this->assertSame(['healthScore' => 80, 'anomalies' => []], $result);
    }

    public function testGenerateJsonReturnsNullWhenResponseIsNotValidJson(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse(json_encode(['response' => 'not valid json at all']), ['http_code' => 200])
        );

        $service = new OllamaClientService($httpClient, 'http://ollama:11434', 'qwen3:8b', 120);

        $this->assertNull($service->generateJson('analyze this'));
    }

    public function testGenerateJsonSendsFormatJsonOption(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('POST', $method);
            $this->assertStringContainsString('/api/generate', $url);
            $body = json_decode($options['body'], true);
            $this->assertSame('json', $body['format'] ?? null);
            $this->assertSame('qwen3:8b', $body['model'] ?? null);
            $this->assertFalse($body['stream'] ?? null);

            return new MockResponse(json_encode(['response' => '{"ok": true}']), ['http_code' => 200]);
        });

        $service = new OllamaClientService($httpClient, 'http://ollama:11434', 'qwen3:8b', 120);

        $this->assertSame(['ok' => true], $service->generateJson('analyze this'));
    }
}
