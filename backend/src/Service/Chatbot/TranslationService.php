<?php

namespace App\Service\Chatbot;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Translates user messages to English using the MyMemory free translation API.
 * https://mymemory.translated.net/doc/spec.php.
 *
 * Free tier limits:
 *   - 1 000 words/day  — anonymous (no configuration needed)
 *   - 10 000 words/day — with MYMEMORY_EMAIL set in .env (no credit card)
 *
 * Source language is auto-detected by MyMemory, so French, Arabic, Spanish,
 * etc. all work without any extra configuration.
 *
 * Degrades silently: if the API is unreachable or the daily limit is hit,
 * the original text is returned unchanged so the chatbot keeps working.
 */
class TranslationService
{
    private const API_URL = 'https://api.mymemory.translated.net/get';
    private const TIMEOUT = 3;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $email = '',
    ) {
    }

    /**
     * Translates $text to English. Returns the original when the text is
     * already in English, when the API is unavailable, or when the daily
     * quota is exhausted.
     */
    public function toEnglish(string $text): string
    {
        if ('' === trim($text)) {
            return $text;
        }

        try {
            $query = [
                'q' => $text,
                'langpair' => 'autodetect|en',
            ];

            if ('' !== $this->email) {
                $query['de'] = $this->email;
            }

            $response = $this->httpClient->request('GET', self::API_URL, [
                'query' => $query,
                'timeout' => self::TIMEOUT,
            ]);

            $data = $response->toArray(false);

            if (($data['responseStatus'] ?? 0) !== 200) {
                return $text;
            }

            $translated = $data['responseData']['translatedText'] ?? '';

            // MyMemory returns the original when it detects the source is
            // already English — comparing case-insensitively avoids a pointless
            // merge that would just duplicate the same keywords.
            if ('' === $translated || mb_strtolower($translated) === mb_strtolower($text)) {
                return $text;
            }

            return $translated;
        } catch (\Throwable) {
            return $text;
        }
    }
}
