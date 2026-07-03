<?php

namespace App\Chatbot\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Talks to the Gemini API and nothing else — builds the request, sends it,
 * parses the reply. Knows nothing about products, prompts or chat history;
 * ChatbotService owns all of that, this just survives the network.
 */
class GeminiClientService
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';
    private const TIMEOUT_SECONDS = 10;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $model,
    ) {}

    /**
     * @return string|null The generated text, or null if the key is missing,
     *                      the request timed out, Gemini errored, or the
     *                      response didn't have the shape we expect.
     */
    public function generate(string $prompt): ?string
    {
        if ($this->apiKey === '') {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', sprintf(self::ENDPOINT, $this->model), [
                'query' => ['key' => $this->apiKey],
                'json' => [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.4,
                        'maxOutputTokens' => 400,
                    ],
                ],
                'timeout' => self::TIMEOUT_SECONDS,
            ]);

            // toArray(false): decode the body regardless of status code
            // instead of throwing on 4xx/5xx — a quota/auth error from
            // Gemini is something we want to read and fall back from, not
            // an exception that crashes the chat request.
            $data = $response->toArray(false);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        } catch (\Throwable $e) {
            // Network failure, DNS issue, timeout, malformed JSON — degrade gracefully.
            return null;
        }
    }
}
