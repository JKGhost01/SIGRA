<?php

declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

use App\Modules\System\Infrastructure\GithubMobileRelease;

$directory = sys_get_temp_dir() . '/sigra-mobile-tests-' . bin2hex(random_bytes(8));
$clock = new class implements App\Shared\Domain\Clock {
    public int $timestamp = 1800000000;
    public function now(): DateTimeImmutable { return new DateTimeImmutable('@' . $this->timestamp); }
};
$calls = 0; $releases = []; $failure = false;
$request = static function (string $url) use (&$calls, &$releases, &$failure): array {
    $calls++;
    if ($failure) throw new RuntimeException('private transport details');
    return ['status' => 200, 'body' => str_contains($url, 'raw.githubusercontent.com')
        ? 'versionName = "1.0.0.0"' : json_encode($releases)];
};
$service = static fn() => new GithubMobileRelease($directory, $clock, $request);
try {
    $data = $service()->snapshot();
    expectArchitecture($data['version'] === '1.0.0.0' && !$data['published'] && $data['apk'] === null && !$data['stale'],
        'Empty releases must show source version and no APK');
    $service()->snapshot();
    expectArchitecture($calls === 2, 'Page views repeatedly queried GitHub');
    $clock->timestamp += 3600;
    $release = ['draft' => false, 'prerelease' => false, 'tag_name' => 'v1.0.0.1', 'assets' => [
        ['name' => 'app-debug.apk', 'browser_download_url' => GithubMobileRelease::REPOSITORY . '/releases/download/v1.0.0.1/app-debug.apk'],
        ['name' => 'app-release.apk', 'browser_download_url' => 'https://untrusted.example/file.apk'],
        ['name' => 'SIGRA.apk', 'browser_download_url' => GithubMobileRelease::REPOSITORY . '/releases/download/v1.0.0.1/SIGRA.apk'],
    ]];
    $releases = [array_replace($release, ['tag_name' => 'v9.0.0', 'prerelease' => true]), $release];
    $data = $service()->snapshot();
    expectArchitecture($data['version'] === '1.0.0.1' && $data['published'] && str_ends_with($data['apk'], '/SIGRA.apk'),
        'Stable release and APK selection failed');
    $clock->timestamp += 3600; $failure = true;
    $before = $calls;
    $data = $service()->snapshot();
    expectArchitecture($data['stale'] && $data['version'] === '1.0.0.1' && $data['apk'] !== null,
        'GitHub outage discarded known release');
    $service()->snapshot();
    expectArchitecture($calls === $before + 1, 'Failure caused immediate GitHub retries');
    expectArchitecture(!str_contains(json_encode($data), 'private transport'), 'Raw exception leaked');
    $clock->timestamp += 3600; $failure = false; $releases = [array_replace($release, ['assets' => []])];
    expectArchitecture($service()->snapshot()['apk'] === null, 'Release without APK invented download');
    file_put_contents($directory . '/mobile-release.json', '{broken');
    expectArchitecture($service()->snapshot()['version'] === '1.0.0.1', 'Corrupt cache prevented recovery');
} finally {
    foreach (glob($directory . '/*') as $file) unlink($file);
    rmdir($directory);
}
echo "Mobile releases: source fallback, stable APK selection, cached metadata, unavailable GitHub, missing APK and corrupt cache passed.\n";
