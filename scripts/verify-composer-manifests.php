<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$rootManifest = json_decode((string) file_get_contents($root.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$packageManifest = json_decode((string) file_get_contents($root.'/packages/laravel-bulkflow/composer.json'), true, 512, JSON_THROW_ON_ERROR);

$expectedRoot = $packageManifest;
$expectedRoot['autoload']['psr-4']['BulkFlow\\'] = 'packages/laravel-bulkflow/src/';
$expectedRoot['autoload-dev']['psr-4']['BulkFlow\\Tests\\'] = 'packages/laravel-bulkflow/tests/';
unset($expectedRoot['scripts']);

foreach (['name', 'description', 'type', 'license', 'homepage', 'support', 'require', 'require-dev', 'autoload', 'autoload-dev', 'extra', 'config', 'minimum-stability', 'prefer-stable'] as $field) {
    if (($rootManifest[$field] ?? null) !== ($expectedRoot[$field] ?? null)) {
        fwrite(STDERR, sprintf("Root composer.json is out of sync for '%s'.\n", $field));
        exit(1);
    }
}

fwrite(STDOUT, "Composer manifests are synchronized.\n");
