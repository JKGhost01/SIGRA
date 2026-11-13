<?php

/** Decorative SVGs; the surrounding control supplies the accessible label. */
function sigra_icon(string $name): string
{
    $paths = [
        'pin' => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'home' => '<path d="m3 10 9-7 9 7v11H3Z"/><path d="M9 21v-8h6v8"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="7.5"/><path d="m16 16 5 5"/>',
        'phone' => '<rect x="6" y="2" width="12" height="20" rx="2"/><path d="M10 5h4M11 19h2"/>',
        'list' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M5 19l1.5-1.5M17.5 6.5 19 5"/>',
        'moon' => '<path d="M21 13a9 9 0 0 1-10-10 9 9 0 1 0 10 10Z"/>',
    ];

    if (!isset($paths[$name])) {
        return '';
    }

    return '<svg class="sigra-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
}

/** Public assets in the recommended public/ webroot and the legacy root deployment. */
function sigra_public_url(string $path): string
{
    $publicRoot = realpath(ROOTPATH . 'public');
    $frontRoot = realpath(FCPATH);
    $prefix = $publicRoot !== false && $frontRoot === $publicRoot ? '' : 'public/';
    return base_url($prefix . ltrim($path, '/'));
}
