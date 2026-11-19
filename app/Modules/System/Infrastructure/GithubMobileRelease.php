<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure;

use App\Shared\Domain\Clock;

/** Public GitHub metadata only. Never downloads, builds or publishes APKs. */
final class GithubMobileRelease
{
    public const REPOSITORY = 'https://github.com/JKGhost01/SIGRA_MOBILE';

    public function __construct(private readonly string $directory, private readonly Clock $clock,
        private readonly ?\Closure $request = null) {}

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $fallback = ['version' => '1.0.0.0', 'published' => false, 'apk' => null,
            'release' => self::REPOSITORY . '/releases', 'repository' => self::REPOSITORY,
            'stale' => true];
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) return $fallback;
        $path = $this->directory . '/mobile-release.json';
        $cache = is_file($path) ? json_decode((string) @file_get_contents($path), true) : null;
        $previous = is_array($cache['data'] ?? null) ? $cache['data'] : $fallback;
        if (($cache['next_at'] ?? 0) > $this->clock->now()->getTimestamp()) return $previous;
        $lock = @fopen($this->directory . '/mobile-release.lock', 'c');
        if ($lock === false) return $previous;
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) return $previous;
            $fresh = is_file($path) ? json_decode((string) @file_get_contents($path), true) : null;
            if (($fresh['next_at'] ?? 0) > $this->clock->now()->getTimestamp() && is_array($fresh['data'] ?? null)) return $fresh['data'];
            // Reserve before I/O so failures cannot turn page visits into repeated GitHub requests.
            $now = $this->clock->now()->getTimestamp();
            $this->write($path, ['next_at' => $now + 3600, 'data' => array_replace($previous, ['stale' => true])]);
            try {
                $response = $this->get('https://api.github.com/repos/JKGhost01/SIGRA_MOBILE/releases?per_page=10');
                if ($response['status'] !== 200) throw new \RuntimeException('GitHub unavailable');
                $releases = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($releases) || !array_is_list($releases)) throw new \RuntimeException('Invalid releases');
                $data = $fallback;
                $data['stale'] = false;
                foreach ($releases as $release) {
                    if (!is_array($release) || ($release['draft'] ?? true) || ($release['prerelease'] ?? true)) continue;
                    $tag = $release['tag_name'] ?? '';
                    if (!is_string($tag) || !preg_match('/^v?(\d+\.\d+\.\d+(?:\.\d+)?)$/D', $tag, $match)) continue;
                    $data['version'] = $match[1];
                    $data['published'] = true;
                    $data['release'] = self::REPOSITORY . '/releases/tag/' . rawurlencode($tag);
                    foreach ($release['assets'] ?? [] as $asset) {
                        $url = $asset['browser_download_url'] ?? null;
                        $name = $asset['name'] ?? '';
                        if (is_string($name) && preg_match('/\.apk$/iD', $name)
                            && !preg_match('/debug|unsigned|unaligned/i', $name)
                            && is_string($url) && str_starts_with($url, self::REPOSITORY . '/releases/download/')
                            && \App\Shared\Domain\ExternalUrl::http($url) !== null) {
                            $data['apk'] = $url;
                            break;
                        }
                    }
                    break;
                }
                if (!$data['published']) {
                    $source = $this->get('https://raw.githubusercontent.com/JKGhost01/SIGRA_MOBILE/main/app/build.gradle.kts');
                    if ($source['status'] !== 200 || !preg_match('/versionName\s*=\s*"(\d+\.\d+\.\d+(?:\.\d+)?)"/', $source['body'], $match)) {
                        throw new \RuntimeException('Source version unavailable');
                    }
                    $data['version'] = $match[1];
                }
                $this->write($path, ['next_at' => $now + 3600, 'data' => $data]);
                return $data;
            } catch (\Throwable) {
                return array_replace($previous, ['stale' => true]);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function get(string $url): array
    {
        if ($this->request !== null) return ($this->request)($url);
        $response = \Config\Services::curlrequest(['timeout' => 5, 'connect_timeout' => 3,
            'http_errors' => false, 'allow_redirects' => false, 'verify' => true,
            'headers' => ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'SIGRA']], null, null, false)->get($url);
        return ['status' => $response->getStatusCode(), 'body' => $response->getBody()];
    }

    private function write(string $path, array $data): void
    {
        $temp = tempnam($this->directory, 'mobile-');
        if ($temp === false) return;
        try {
            if (file_put_contents($temp, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX) !== false) rename($temp, $path);
        } finally {
            if (is_file($temp)) unlink($temp);
        }
    }
}
