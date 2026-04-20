<?php

it('manifest json exists in public directory', function (): void {
    $path = public_path('manifest.json');

    expect(file_exists($path))->toBeTrue();
});

it('manifest json contains required pwa fields', function (): void {
    $data = json_decode(file_get_contents(public_path('manifest.json')), true);

    expect($data)->toHaveKeys(['name', 'short_name', 'start_url', 'display', 'icons']);
    expect($data['display'])->toBe('standalone');
    expect($data['start_url'])->toBe('/');
});

it('service worker file exists in public directory', function (): void {
    expect(file_exists(public_path('sw.js')))->toBeTrue();

    $content = file_get_contents(public_path('sw.js'));
    expect($content)->toContain('CACHE_VERSION')
        ->and($content)->toContain('install')
        ->and($content)->toContain('fetch');
});

it('offline fallback html exists in public directory', function (): void {
    expect(file_exists(public_path('offline.html')))->toBeTrue();
    $content = file_get_contents(public_path('offline.html'));
    expect($content)->toContain('bağlantısı');
});
