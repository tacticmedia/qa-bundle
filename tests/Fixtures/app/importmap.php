<?php

declare(strict_types=1);

/**
 * @return array<string, array{path?: string, version?: string, entrypoint?: bool, type?: string}>
 */
return [
    'app' => ['path' => './assets/app.js', 'entrypoint' => true],
    '@symfony/stimulus-bundle' => ['path' => '../../../vendor/symfony/stimulus-bundle/assets/dist/loader.js'],
    '@hotwired/stimulus' => ['version' => '3.2.2'],
    '@hotwired/turbo' => ['version' => '8.0.23'],
];
