<?php

/** Hostile input regressions for independently supplied package-set evidence. @since 0.3.0 */

declare(strict_types=1);

require __DIR__ . '/verify-package-set-consumer.php';

$workspace = sys_get_temp_dir() . '/kumwe-package-set-fixtures-' . bin2hex(random_bytes(8));
mkdir($workspace, 0700);

/** @param string $workspace Private fixture workspace. @return array<string, mixed> Input set. @since 0.3.0 */
function packageSetFixture(string $workspace): array
{
    $packages = [];
    foreach (packageSetTargets() as $name) {
        $root = $workspace . '/' . substr($name, 6);
        if (!is_dir($root)) {
            mkdir($root);
        }
        $metadata = ['name' => $name, 'require' => ['php' => '^8.5']];
        $entry = ['name' => $name, 'version' => '0.1.0', 'source_commit' => str_repeat('a', 40),
            'package_root' => $root, 'archive_path' => $root . '.zip', 'composer' => $metadata];
        packageSetFixtureMetadata($entry);
        $packages[] = $entry;
    }
    return ['schema' => 'kumwe-verified-php-package-set/v1', 'packages' => $packages];
}

/** @param array<string, mixed> $entry Fixture metadata to archive consistently. @return void @since 0.3.0 */
function packageSetFixtureMetadata(array &$entry): void
{
    $json = json_encode($entry['composer'], JSON_THROW_ON_ERROR);
    file_put_contents($entry['package_root'] . '/composer.json', $json);
    $zip = new ZipArchive();
    if ($zip->open($entry['archive_path'], ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Cannot create package metadata fixture.');
    }
    $zip->addFromString('composer.json', $json);
    $zip->close();
    $entry['archive_sha256'] = hash_file('sha256', $entry['archive_path']);
}

$cases = [
    'incomplete extraction set' => static function (array &$input): string {
        array_pop($input['packages']);
        return 'set is incomplete';
    },
    'duplicate canonical package' => static function (array &$input): string {
        $input['packages'][] = $input['packages'][0];
        return 'canonical name';
    },
    'development coordinate' => static function (array &$input): string {
        $input['packages'][0]['version'] = 'dev-main';
        return 'exact stable version';
    },
    'changed archive bytes' => static function (array &$input): string {
        file_put_contents($input['packages'][0]['archive_path'], 'changed archive');
        return 'archive contents have changed';
    },
    'relative archive path' => static function (array &$input): string {
        $input['packages'][0]['archive_path'] = 'relative.zip';
        return 'canonical local paths';
    },
    'changed extracted metadata' => static function (array &$input): string {
        file_put_contents($input['packages'][0]['package_root'] . '/composer.json', '{}');
        return 'Composer metadata differs';
    },
    'floating runtime dependency' => static function (array &$input): string {
        $input['packages'][0]['composer']['require']['kumwe/contribution'] = '^0.1';
        packageSetFixtureMetadata($input['packages'][0]);
        return 'exact stable coordinates';
    },
    'unselected transitive dependency' => static function (array &$input): string {
        $input['packages'][0]['composer']['require']['kumwe/unselected'] = '0.1.0';
        packageSetFixtureMetadata($input['packages'][0]);
        return 'graph does not satisfy';
    },
    'conflicting selected dependency' => static function (array &$input): string {
        $input['packages'][0]['composer']['require']['kumwe/contribution'] = '0.2.0';
        packageSetFixtureMetadata($input['packages'][0]);
        return 'graph does not satisfy';
    },
    'native portable requirement' => static function (array &$input): string {
        $input['packages'][0]['composer']['require']['ext-kumwe_engine'] = '*';
        packageSetFixtureMetadata($input['packages'][0]);
        return 'must not require the native extension';
    },
    'unresolved source identity' => static function (array &$input): string {
        $input['packages'][0]['source_commit'] = 'main';
        return 'verified source/archive identity';
    },
];

try {
    if (count(packageSetInput(packageSetFixture($workspace))) !== 28) {
        throw new RuntimeException('Complete portable fixture was not accepted.');
    }
    foreach ($cases as $label => $mutate) {
        $input = packageSetFixture($workspace);
        $message = $mutate($input);
        try {
            packageSetInput($input);
        } catch (RuntimeException $failure) {
            if (str_contains($failure->getMessage(), $message)) {
                continue;
            }
            throw new RuntimeException('Wrong refusal for ' . $label . ': ' . $failure->getMessage(), 0, $failure);
        }
        throw new RuntimeException('Invalid package evidence was accepted: ' . $label);
    }
    echo 'Package-set input regressions passed: complete graph and ' . count($cases) . " hostile cases.\n";
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($workspace,
        FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($workspace);
}
