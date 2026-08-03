<?php

namespace App\Service\Ai;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Talks to Groq's OpenAI-compatible chat completions API (model:
 * llama-3.3-70b-versatile by default) and nothing else — builds the request,
 * sends it, parses the reply. Shared by every feature in the project that
 * needs an LLM call (chatbot replies, product-type attribute suggestions, ...).
 */
class GroqClientService
{
    private const ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
    private const TIMEOUT_SECONDS = 15;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $model,
    ) {
    }

    /**
     * @return string|null the generated text, or null if the key is missing,
     *                     the request timed out, the API errored, or the
     *                     response didn't have the shape we expect
     */
    public function generate(string $prompt): ?string
    {
        $data = $this->call([
            ['role' => 'user', 'content' => $prompt],
        ], [
            'temperature' => 0.4,
            'max_tokens' => 400,
        ]);

        return $data['choices'][0]['message']['content'] ?? null;
    }

    /**
     * Same as generate(), but constrains the model to return a single JSON
     * object (Groq/OpenAI "JSON mode") and decodes it. The prompt MUST ask
     * for JSON explicitly and describe the exact shape wanted — JSON mode
     * only guarantees syntactically valid JSON, not a particular schema, so
     * callers must still validate/sanitize every field of the result.
     *
     * @return array<string, mixed>|null decoded JSON object, or null if the
     *                                   key is missing, the request failed,
     *                                   or the response wasn't valid JSON
     */
    public function generateJson(string $prompt): ?array
    {
        $data = $this->call([
            ['role' => 'user', 'content' => $prompt],
        ], [
            'temperature' => 0.2,
            'max_tokens' => 1500,
            'response_format' => ['type' => 'json_object'],
        ]);

        $content = $data['choices'][0]['message']['content'] ?? null;
        if (!is_string($content)) {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed>                             $extra
     *
     * @return array<string, mixed>|null
     */
    private function call(array $messages, array $extra): ?array
    {
        if ('' === $this->apiKey) {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', self::ENDPOINT, [
                'auth_bearer' => $this->apiKey,
                'json' => array_merge([
                    'model' => $this->model,
                    'messages' => $messages,
                ], $extra),
                'timeout' => self::TIMEOUT_SECONDS,
            ]);

            // toArray(false): decode the body regardless of status code
            // instead of throwing on 4xx/5xx — a quota/auth error is
            // something we want to read and fall back from, not an
            // exception that crashes the caller.
            $data = $response->toArray(false);

            if (200 !== $response->getStatusCode()) {
                return null;
            }

            return $data;
        } catch (\Throwable) {
            // Network failure, DNS issue, timeout, malformed JSON — degrade gracefully.
            return null;
        }
    }
}
