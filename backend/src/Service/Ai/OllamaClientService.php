<?php

namespace App\Service\Ai;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Talks to a self-hosted Ollama server (the "ollama" Docker service) —
 * builds the request, sends it, parses the reply. Nothing here ever leaves
 * the machine, unlike GroqClientService. Which model is used is entirely
 * up to $model (bound from OLLAMA_MODEL, deliberately left blank in
 * .env.example — model choice is a hardware trade-off for each developer).
 *
 * Same shape/contract as GroqClientService on purpose (generate/generateJson,
 * silent-degrade-to-null on any failure) so callers don't need to care which
 * provider they're talking to.
 */
class OllamaClientService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private string $model,
        private int $timeout,
    ) {
    }

    /**
     * @return string|null the generated text, or null if no model is
     *                     configured, the request timed out, the server
     *                     errored, or the response didn't have the shape
     *                     we expect
     */
    public function generate(string $prompt): ?string
    {
        $data = $this->call($prompt, [
            'temperature' => 0.4,
        ]);

        $response = $data['response'] ?? null;

        return is_string($response) ? $response : null;
    }

    /**
     * Same as generate(), but constrains the model to return a single JSON
     * object (Ollama's native "format": "json" mode) and decodes it. The
     * prompt MUST ask for JSON explicitly and describe the exact shape
     * wanted — JSON mode only guarantees syntactically valid JSON, not a
     * particular schema, so callers must still validate/sanitize every
     * field of the result.
     *
     * @return array<string, mixed>|null decoded JSON object, or null if no
     *                                   model is configured, the request
     *                                   failed, or the response wasn't
     *                                   valid JSON
     */
    public function generateJson(string $prompt): ?array
    {
        $data = $this->call($prompt, [
            'temperature' => 0.2,
        ], jsonMode: true);

        $response = $data['response'] ?? null;
        if (!is_string($response)) {
            return null;
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>|null
     */
    private function call(string $prompt, array $options, bool $jsonMode = false): ?array
    {
        if ('' === $this->model) {
            return null;
        }

        try {
            $payload = [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'options' => $options,
            ];
            if ($jsonMode) {
                $payload['format'] = 'json';
            }

            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/').'/api/generate', [
                'json' => $payload,
                // Local CPU inference is far slower than a cloud API — this is
                // configurable (OLLAMA_TIMEOUT) precisely because it varies a
                // lot by model size and hardware.
                'timeout' => $this->timeout,
            ]);

            // toArray(false): decode the body regardless of status code
            // instead of throwing on 4xx/5xx — e.g. "model not found" is
            // something we want to read and fall back from, not an
            // exception that crashes the caller.
            $data = $response->toArray(false);

            if (200 !== $response->getStatusCode()) {
                return null;
            }

            return $data;
        } catch (\Throwable) {
            // Network failure, container not running, timeout, malformed JSON — degrade gracefully.
            return null;
        }
    }
}
