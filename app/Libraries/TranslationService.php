<?php

declare(strict_types=1);

namespace App\Libraries;

use Config\Services;
use Config\Translator;

/**
 * Traducción automática de cadenas de interfaz con caché en disco.
 *
 * Se usa desde `php spark sigra:translate` para generar los archivos
 * de idioma. Las claves se escriben una sola vez en español; el resto
 * de locales se rellenan automáticamente.
 */
class TranslationService
{
    private Translator $config;
    private string $cacheFile = '';
    private array $cache = [];

    public function __construct(?Translator $config = null)
    {
        $this->config = $config ?? config(Translator::class);
    }

    public function setProvider(string $provider): self
    {
        $this->config->provider = $provider;

        return $this;
    }

    public function translate(string $text, string $to): ?string
    {
        $from = $this->config->sourceLocale;
        $text = trim($text);
        if ($text === '' || $to === $from) {
            return $text;
        }

        $this->loadCache($from, $to);
        $key = sha1($from . '|' . $to . '|' . $text);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $translated = $this->request($text, $from, $to);
        if (! is_string($translated) || trim($translated) === '') {
            return null;
        }

        $translated = trim($translated);
        if (! $this->samePlaceholders($text, $translated)) {
            log_message('warning', 'La traducción cambió los marcadores: {text}', ['text' => $text]);

            return null;
        }

        $this->cache[$key] = $translated;
        $this->saveCache($from, $to);

        if ($this->config->rateLimitMs > 0) {
            usleep($this->config->rateLimitMs * 1000);
        }

        return $translated;
    }

    private function request(string $text, string $from, string $to): ?string
    {
        return match ($this->config->provider) {
            'mymemory'      => $this->mymemory($text, $from, $to),
            'libretranslate'=> $this->libretranslate($text, $from, $to),
            'deepl'         => $this->deepl($text, $from, $to),
            default         => $this->google($text, $from, $to),
        };
    }

    private function client(): \CodeIgniter\HTTP\CURLRequest
    {
        return Services::curlrequest([
            'timeout'     => $this->config->timeout,
            'http_errors' => false,
            'verify'      => ENVIRONMENT !== 'development',
            'headers'     => ['Accept' => 'application/json'],
        ]);
    }

    private function google(string $text, string $from, string $to): ?string
    {
        $url = 'https://translate.googleapis.com/translate_a/single?' . http_build_query([
            'client' => 'gtx',
            'sl'     => $from,
            'tl'     => $to,
            'dt'     => 't',
            'q'      => $text,
        ], '', '&', PHP_QUERY_RFC3986);

        $response = $this->client()->get($url);
        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $data = json_decode($response->getBody(), true);
        if (! is_array($data[0] ?? null)) {
            return null;
        }

        $out = '';
        foreach ($data[0] as $segment) {
            if (is_array($segment) && isset($segment[0])) {
                $out .= $segment[0];
            }
        }

        return $out !== '' ? html_entity_decode($out, ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
    }

    private function mymemory(string $text, string $from, string $to): ?string
    {
        $url = 'https://api.mymemory.translated.net/get?' . http_build_query([
            'q'        => $text,
            'langpair' => $from . '|' . $to,
        ], '', '&', PHP_QUERY_RFC3986);

        $response = $this->client()->get($url);
        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $data = json_decode($response->getBody(), true);
        $translated = $data['responseData']['translatedText'] ?? null;

        return is_string($translated) ? html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
    }

    private function libretranslate(string $text, string $from, string $to): ?string
    {
        $endpoint = $this->config->endpoint !== '' ? $this->config->endpoint : 'https://libretranslate.com/translate';
        $payload  = [
            'q'      => $text,
            'source' => $from,
            'target' => $to,
            'format' => 'text',
        ];
        if ($this->config->apiKey !== '') {
            $payload['api_key'] = $this->config->apiKey;
        }

        $response = $this->client()->post($endpoint, ['json' => $payload]);
        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $data = json_decode($response->getBody(), true);

        return is_string($data['translatedText'] ?? null) ? $data['translatedText'] : null;
    }

    private function deepl(string $text, string $from, string $to): ?string
    {
        $endpoint = $this->config->endpoint !== '' ? $this->config->endpoint : 'https://api-free.deepl.com/v2/translate';
        $response = $this->client()->post($endpoint, [
            'form_params' => [
                'auth_key'    => $this->config->apiKey,
                'text'        => $text,
                'source_lang' => strtoupper($from),
                'target_lang' => strtoupper($to),
            ],
        ]);
        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $data = json_decode($response->getBody(), true);

        return is_string($data['translations'][0]['text'] ?? null) ? $data['translations'][0]['text'] : null;
    }

    private function samePlaceholders(string $source, string $translated): bool
    {
        preg_match_all('/\{\d+\}/', $source, $a);
        preg_match_all('/\{\d+\}/', $translated, $b);

        $left  = $a[0];
        $right = $b[0];
        sort($left);
        sort($right);

        return $left === $right;
    }

    private function loadCache(string $from, string $to): void
    {
        if ($this->cacheFile !== '') {
            return;
        }

        if (! is_dir($this->config->cacheDirectory)) {
            mkdir($this->config->cacheDirectory, 0775, true);
        }

        $this->cacheFile = rtrim($this->config->cacheDirectory, '/\\') . DIRECTORY_SEPARATOR . $from . '-' . $to . '.json';
        if (is_file($this->cacheFile)) {
            $data = json_decode((string) file_get_contents($this->cacheFile), true);
            $this->cache = is_array($data) ? $data : [];
        }
    }

    private function saveCache(string $from, string $to): void
    {
        if ($this->cacheFile === '') {
            $this->loadCache($from, $to);
        }

        file_put_contents(
            $this->cacheFile,
            json_encode($this->cache, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX,
        );
    }
}
