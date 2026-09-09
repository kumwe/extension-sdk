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
    foreach (packageSetRequiredPackages() as $name) {
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

/**
 * Synthetic metadata-validation fixture only; never a published release or an attestation claim.
 * @param string $workspace Private fixture directory.
 * @return array<string, mixed> Native input whose immutable local-byte relationships are consistent.
 * @since 0.3.0
 */
function packageSetNativeFixture(string $workspace): array
{
    $input = packageSetFixture($workspace);
    $input['graph_mode'] = 'native';
    $native = ['extension_version' => '1.0.0'];
    foreach (['engine' => 'kumwe/engine', 'extension' => 'kumwe/kumwe-engine'] as $kind => $package) {
        $member = $workspace . '/' . $kind . '-attestation.yaml';
        file_put_contents($member, "fixture_only: true\npackage: " . $package . "\n");
        $archive = $workspace . '/' . $kind . '-attestation.zip';
        $zip = new ZipArchive();
        $zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($member, 'RELEASE-ATTESTATION.yaml');
        $zip->close();
        $native[$kind] = ['package' => $package, 'version' => '1.0.0', 'tag' => 'v1.0.0',
            'commit' => str_repeat('b', 40), 'archive_sha256' => str_repeat('c', 64),
            'attestation' => ['uri' => 'https://github.com/kumwe/extension-sdk/actions/runs/1/artifacts/1',
                'member' => 'RELEASE-ATTESTATION.yaml', 'archive_path' => $archive,
                'sha256' => hash_file('sha256', $archive), 'member_path' => $member,
                'member_sha256' => hash_file('sha256', $member)]];
    }
    $embedding = $workspace . '/embedding-fixture.tar';
    file_put_contents($embedding, 'synthetic embedding input for byte-identity unit tests only');
    $native['engine']['embedding_archive_path'] = $embedding;
    $native['engine']['embedding_archive_sha256'] = hash_file('sha256', $embedding);
    $native['compatibility_tuple'] = ['engine' => 'kumwe/engine', 'version' => '1.0.0',
        'abi_status' => 'frozen', 'semantic_release_verified' => true,
        'computation' => ['engine_version' => '1.0.0', 'api_version' => '1.0.0'],
        'extension_package' => 'kumwe/kumwe-engine', 'extension_module' => 'kumwe_engine',
        'extension_version' => '1.0.0', 'embedded_engine_commit' => $native['engine']['commit'],
        'embedded_source_sha256' => $native['engine']['embedding_archive_sha256']];
    $input['native'] = $native;
    foreach ($input['packages'] as &$entry) {
        if ($entry['name'] === 'kumwe/computation') {
            $entry['version'] = '0.3.1';
            $entry['composer']['require']['ext-kumwe_engine'] = '1.0.0';
            packageSetFixtureMetadata($entry);
        }
    }
    return $input;
}

$cases = [
    'unknown graph mode' => static function (array &$input): string {
        $input['graph_mode'] = 'candidate';
        return 'Unsupported package graph mode';
    },
    'native evidence in portable mode' => static function (array &$input): string {
        $input['native'] = [];
        return 'cannot carry native evidence';
    },
    'missing native evidence' => static function (array &$input): string {
        $input['graph_mode'] = 'native';
        return 'requires stable evidence';
    },
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

$nativeCases = [
    'floating durable evidence ref' => static function (array &$input): string {
        $input['native']['engine']['attestation']['uri'] = 'https://raw.githubusercontent.com/kumwe/extension-sdk/main/evidence/native/attestation.zip';
        return 'immutable attestation location';
    },
    'foreign durable evidence owner' => static function (array &$input): string {
        $input['native']['engine']['attestation']['uri'] = 'https://raw.githubusercontent.com/other/extension-sdk/' . str_repeat('a', 40) . '/evidence/native/attestation.zip';
        return 'immutable attestation location';
    },
    'durable evidence path traversal' => static function (array &$input): string {
        $input['native']['engine']['attestation']['uri'] = 'https://raw.githubusercontent.com/kumwe/extension-sdk/' . str_repeat('a', 40) . '/evidence/../attestation.zip';
        return 'immutable attestation location';
    },
    'durable evidence query suffix' => static function (array &$input): string {
        $input['native']['engine']['attestation']['uri'] = 'https://raw.githubusercontent.com/kumwe/extension-sdk/' . str_repeat('a', 40) . '/evidence/native/attestation.zip?ref=main';
        return 'immutable attestation location';
    },
    'candidate extension' => static function (array &$input): string {
        $input['native']['extension_version'] = '1.0.0-dev';
        return 'requires stable evidence';
    },
    'floating native tag' => static function (array &$input): string {
        $input['native']['engine']['tag'] = 'main';
        return 'coordinates must be canonical';
    },
    'changed attestation archive' => static function (array &$input): string {
        file_put_contents($input['native']['engine']['attestation']['archive_path'], 'tampered');
        return 'evidence file identity';
    },
    'changed attestation member' => static function (array &$input): string {
        $attestation = &$input['native']['engine']['attestation'];
        file_put_contents($attestation['member_path'], "changed: true\n");
        $attestation['member_sha256'] = hash_file('sha256', $attestation['member_path']);
        return 'ZIP member differs';
    },
    'raw embedding digest confused with published digest' => static function (array &$input): string {
        $input['native']['compatibility_tuple']['embedded_source_sha256'] = $input['native']['engine']['archive_sha256'];
        return 'tuple does not match';
    },
    'unverified semantic freeze' => static function (array &$input): string {
        $input['native']['compatibility_tuple']['semantic_release_verified'] = false;
        return 'tuple does not match';
    },
    'unstable ABI' => static function (array &$input): string {
        $input['native']['compatibility_tuple']['abi_status'] = 'unstable-development';
        return 'tuple does not match';
    },
    'portable computation in native graph' => static function (array &$input): string {
        foreach ($input['packages'] as &$entry) {
            if ($entry['name'] === 'kumwe/computation') {
                unset($entry['composer']['require']['ext-kumwe_engine']);
                packageSetFixtureMetadata($entry);
            }
        }
        return 'select the stable native Computation successor';
    },
    'floating native requirement' => static function (array &$input): string {
        foreach ($input['packages'] as &$entry) {
            if ($entry['name'] === 'kumwe/computation') {
                $entry['composer']['require']['ext-kumwe_engine'] = '^1.0';
                packageSetFixtureMetadata($entry);
            }
        }
        return 'exact stable native extension';
    },
];

try {
    if (count(packageSetInput(packageSetFixture($workspace))) !== 31) {
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
    if (count(packageSetInput(packageSetNativeFixture($workspace))) !== 31) {
        throw new RuntimeException('Complete native metadata fixture was not accepted.');
    }
    $durable = packageSetNativeFixture($workspace);
    foreach (['engine', 'extension'] as $kind) {
        $durable['native'][$kind]['attestation']['uri'] = 'https://raw.githubusercontent.com/kumwe/extension-sdk/'
            . str_repeat('a', 40) . '/evidence/native/' . $kind . '/1.0.0/attestation.zip';
    }
    if (count(packageSetInput($durable)) !== 31) {
        throw new RuntimeException('Exact-commit durable evidence metadata was not accepted.');
    }
    foreach ($nativeCases as $label => $mutate) {
        $input = packageSetNativeFixture($workspace);
        $message = $mutate($input);
        try {
            packageSetInput($input);
        } catch (RuntimeException $failure) {
            if (str_contains($failure->getMessage(), $message)) {
                continue;
            }
            throw new RuntimeException('Wrong native refusal for ' . $label . ': ' . $failure->getMessage(), 0, $failure);
        }
        throw new RuntimeException('Invalid native input was accepted: ' . $label);
    }
    if (packageSetTupleValue(['a' => 1, 'b' => ['c' => true]]) !== packageSetTupleValue(['b' => ['c' => true], 'a' => 1])
        || packageSetTupleValue(['a' => 1]) === packageSetTupleValue(['a' => '1'])) {
        throw new RuntimeException('Tuple comparison must preserve typed values without depending on JSON object-key order.');
    }
    try {
        packageSetAssertRuntime(packageSetNativeInput(packageSetNativeFixture($workspace)));
        throw new LogicException('Synthetic metadata was accepted as an actual stable native runtime.');
    } catch (RuntimeException $failure) {
        if (!str_contains($failure->getMessage(), 'actual selected stable extension')
            && !str_contains($failure->getMessage(), 'actual native runtime differs')) {
            throw $failure;
        }
    }
    if (extension_loaded('kumwe_engine')) {
        try {
            packageSetAssertRuntime(null);
            throw new LogicException('Portable mode accepted a loaded native extension.');
        } catch (RuntimeException $failure) {
            if (!str_contains($failure->getMessage(), 'without the native extension')) { throw $failure; }
        }
    } else {
        packageSetAssertRuntime(null);
    }
    echo 'Package-set input regressions passed: complete graph and ' . (count($cases) + count($nativeCases)) . " hostile cases.\n";
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($workspace,
        FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($workspace);
}
