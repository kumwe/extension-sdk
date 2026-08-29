<?php

/**
 * Record the SDK-owned public API, manifest generations and resource digests.
 *
 * This is a clean canonical baseline, not a comparison with a host repository. It reads only tracked
 * SDK source/resources, so platform scratch files and local build output can never enter a release.
 * Run `php tools/record-contract.php`; CI uses `tools/verify-contract.php` to recompute the same facts.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    recordContractMain($argv);
}

/** @param list<string> $arguments Command arguments. @since 0.2.0 */
function recordContractMain(array $arguments): void
{
    $root = dirname(__DIR__);
    if (count($arguments) !== 1) {
        fwrite(STDERR, "Usage: php tools/record-contract.php\n");
        exit(1);
    }

    $classification = recordPublicApi($root);
    $generations = recordGenerations($root);

    writeJson($root . '/resources/contract/classification.json', $classification);
    writeJson($root . '/resources/contract/generations.json', $generations);
    writeJson($root . '/resources/PIN.json', recordResourcePin($root));

    fwrite(
        STDOUT,
        sprintf(
            "Recorded %d canonical public types, %d manifest generations and SDK-owned resource digests.\n",
            count($classification['types']),
            count($generations['manifest_generations']),
        ),
    );
}

/**
 * @param string $root Repository root.
 *
 * @return array<string, mixed> Canonical public API profile.
 *
 * @since 0.2.0
 */
function recordPublicApi(string $root): array
{
    $types = [];
    foreach (headFiles($root, 'src') as $path) {
        if (!str_ends_with($path, '.php')) {
            continue;
        }
        $bytes = headBytes($root, $path);
        $declaration = sourceDeclaration($bytes, $path);
        if (
            $declaration === null
            || str_contains($bytes, '@internal')
            || str_starts_with($declaration['type'], 'Kumwe\\Extension\\Support\\')
        ) {
            continue;
        }
        $types[] = $declaration + [
            'path' => $path,
            'source_sha256' => hash('sha256', $bytes),
        ];
    }
    usort($types, static fn (array $left, array $right): int => $left['type'] <=> $right['type']);

    return [
        'format' => 'kumwe-extension-sdk-public-api-v1',
        'package' => 'kumwe/extension-sdk',
        'release_line' => '0.2',
        'authority' => 'SDK source under src/; Support is internal.',
        'note' => 'Canonical package-owned API. Type membership and source digests are recorded from this repository only.',
        'public_namespaces' => [
            'Kumwe\\Extension\\Contract\\',
            'Kumwe\\Extension\\Manifest\\',
            'Kumwe\\Extension\\Package\\',
            'Kumwe\\Extension\\Spi\\',
            'Kumwe\\Extension\\Toolchain\\',
        ],
        'internal_namespaces' => [
            'Kumwe\\Extension\\Support\\',
        ],
        'external_packages' => [
            'doctrine/dbal',
            'kumwe/conversion',
            'kumwe/producer',
            'psr/http-message',
            'psr/http-server-handler',
            'ramsey/uuid',
        ],
        'types' => $types,
    ];
}

/**
 * @param string $root Repository root.
 *
 * @return array<string, mixed> Canonical manifest/SPI generation profile.
 *
 * @since 0.2.0
 */
function recordGenerations(string $root): array
{
    $surfaces = [
        'capabilities',
        'resource_policies',
        'administrator.workspaces',
        'administrator.navigation',
        'administrator.routes',
        'administrator.views',
        'portal.workspaces',
        'portal.navigation',
        'portal.routes',
        'portal.templates',
        'interface.surfaces',
        'business.field_types',
        'business.field_presentations',
        'business.definitions',
        'business.view_handlers',
        'business.action_handlers',
        'integration.event_schemas',
        'integration.domain_listeners',
        'integration.consumers',
        'integration.jobs',
        'integration.queues',
        'integration.schedules',
        'integration.projections',
        'integration.reports',
        'integration.webhooks',
        'integration.rate_providers',
        'integration.unit_converters',
        'content.translation_groups',
        'composition.blocks',
        'composition.patterns',
        'composition.field_controls',
        'composition.inspectors',
        'composition.design_vocabularies',
        'composition.migrations',
        'composition.documents',
        'composition.host_bindings',
    ];

    $manifestGenerations = [];
    for ($schema = 1; $schema <= 6; $schema++) {
        $fixture = sprintf('fixtures/generations/manifest-%d', $schema);
        $absolute = $root . '/resources/' . $fixture;
        $manifestBytes = requiredBytes($absolute . '/kumwe.json');
        $manifest = decodeObject($manifestBytes, $fixture . '/kumwe.json');
        $entry = [
            'id' => 'manifest-' . $schema,
            'schema' => $schema,
            'contribution_spi' => match ($schema) {
                1 => null,
                2, 3 => 1,
                4 => 2,
                5 => 3,
                6 => 4,
            },
            'fixture' => $fixture,
            'identifier' => $manifest['name'] ?? null,
            'provider' => $manifest['provider'] ?? null,
            'manifest_sha256' => hash('sha256', $manifestBytes),
            'fixture_sha256' => treeDigest($root, 'resources/' . $fixture),
            'surface_counts' => manifestSurfaceCounts($manifest, $surfaces),
            'executable_bindings' => manifestExecutableBindings($manifest),
        ];
        $entry['surface_digest'] = canonicalDigest($entry);
        $manifestGenerations[] = $entry;
    }

    $spiSurfaces = [
        1 => array_slice($surfaces, 0, 16),
        2 => array_slice($surfaces, 0, 28),
        3 => array_values(array_diff($surfaces, ['composition.documents', 'composition.host_bindings'])),
        4 => array_values(array_diff($surfaces, [
            'composition.blocks',
            'composition.patterns',
            'composition.field_controls',
            'composition.inspectors',
            'composition.design_vocabularies',
            'composition.migrations',
        ])),
    ];
    $spiSchemas = [1 => [2, 3], 2 => [4], 3 => [5], 4 => [6]];
    $spiGenerations = [];
    foreach ($spiSchemas as $version => $schemas) {
        $entry = [
            'id' => 'contribution-spi-' . $version,
            'version' => $version,
            'manifest_schemas' => $schemas,
            'surfaces' => $spiSurfaces[$version],
        ];
        $entry['surface_digest'] = canonicalDigest($entry);
        $spiGenerations[] = $entry;
    }

    return [
        'format' => 'kumwe-extension-sdk-generations-v1',
        'package' => 'kumwe/extension-sdk',
        'release_line' => '0.2',
        'authority' => 'resources/fixtures/generations and the canonical SDK manifest parser',
        'note' => 'Canonical SDK generations. The signed manifest is the sole declaration source; '
            . 'providers bind executable implementations only to validated identifiers.',
        'classification' => 'contract/classification.json',
        'fixture_root' => 'fixtures/generations',
        'contribution_surfaces' => $surfaces,
        'manifest_generations' => $manifestGenerations,
        'spi_generations' => $spiGenerations,
        'scaffolds' => [
            [
                'id' => 'complete-component',
                'path' => 'extension-scaffold/complete-component',
                'promise' => 'Complete manifest-bound administrator, portal, automation, integration, projection and migration author surface.',
            ],
        ],
    ];
}

/**
 * @param string $root Repository root.
 *
 * @return array<string, mixed> SDK-owned digest pin.
 *
 * @since 0.2.0
 */
function recordResourcePin(string $root): array
{
    $files = [];
    foreach (trackedFiles($root, 'resources') as $path) {
        if ($path === 'resources/PIN.json') {
            continue;
        }
        $absolute = $root . '/' . $path;
        if (!is_file($absolute)) {
            continue;
        }
        $files[] = [
            'file' => substr($path, strlen('resources/')),
            'sha256' => hash_file('sha256', $absolute),
        ];
    }
    usort($files, static fn (array $left, array $right): int => $left['file'] <=> $right['file']);

    return [
        'format' => 'kumwe-extension-sdk-resource-pin-v2',
        'package' => 'kumwe/extension-sdk',
        'release_line' => '0.2',
        'note' => 'SDK-owned digest inventory. It proves the canonical resources shipped by this package and names no host repository as an authority.',
        'files' => $files,
    ];
}

/**
 * @param string $root Repository root.
 * @param string $directory Repository-relative directory.
 *
 * @return list<string> Tracked files below the directory.
 *
 * @since 0.2.0
 */
function trackedFiles(string $root, string $directory): array
{
    $command = sprintf(
        'git -C %s ls-files -- %s',
        escapeshellarg($root),
        escapeshellarg($directory),
    );
    exec($command, $lines, $status);
    if ($status !== 0) {
        throw new RuntimeException('Tracked SDK files could not be listed.');
    }
    $lines = array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    sort($lines, SORT_STRING);

    return $lines;
}

/**
 * @param string $root Repository root.
 * @param string $directory Repository-relative directory.
 *
 * @return list<string> Files committed at HEAD below the directory.
 *
 * @since 0.2.0
 */
function headFiles(string $root, string $directory): array
{
    $command = sprintf(
        'git -C %s ls-tree -r --name-only HEAD -- %s',
        escapeshellarg($root),
        escapeshellarg($directory),
    );
    exec($command, $lines, $status);
    if ($status !== 0) {
        throw new RuntimeException('Committed SDK files could not be listed.');
    }
    $lines = array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    sort($lines, SORT_STRING);

    return $lines;
}

/**
 * @param string $root Repository root.
 * @param string $path Repository-relative committed file.
 *
 * @return string Bytes at HEAD.
 *
 * @since 0.2.0
 */
function headBytes(string $root, string $path): string
{
    $command = sprintf(
        'git -C %s show %s',
        escapeshellarg($root),
        escapeshellarg('HEAD:' . $path),
    );
    $process = popen($command, 'r');
    if (!is_resource($process)) {
        throw new RuntimeException('Committed SDK source could not be opened: ' . $path);
    }
    $bytes = stream_get_contents($process);
    $status = pclose($process);
    if (!is_string($bytes) || $status !== 0) {
        throw new RuntimeException('Committed SDK source could not be read: ' . $path);
    }

    return $bytes;
}

/**
 * @param string $bytes PHP source bytes.
 * @param string $path Stable path for failure messages.
 *
 * @return ?array{type: string, kind: string} One top-level declaration, or null for a helper file.
 *
 * @since 0.2.0
 */
function sourceDeclaration(string $bytes, string $path): ?array
{
    $tokens = token_get_all($bytes);
    $namespace = '';
    $count = count($tokens);
    for ($index = 0; $index < $count; $index++) {
        $token = $tokens[$index];
        if (!is_array($token)) {
            continue;
        }
        if ($token[0] === T_NAMESPACE) {
            $namespace = '';
            for ($cursor = $index + 1; $cursor < $count; $cursor++) {
                $member = $tokens[$cursor];
                if ($member === ';' || $member === '{') {
                    break;
                }
                if (is_array($member) && in_array($member[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                    $namespace .= $member[1];
                }
            }
            continue;
        }
        $kind = match ($token[0]) {
            T_CLASS => 'class',
            T_INTERFACE => 'interface',
            T_TRAIT => 'trait',
            T_ENUM => 'enum',
            default => null,
        };
        if ($kind === null) {
            continue;
        }
        $previous = previousSignificantToken($tokens, $index);
        if ($token[0] === T_CLASS && is_array($previous) && $previous[0] === T_NEW) {
            continue;
        }
        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            $member = $tokens[$cursor];
            if (is_array($member) && $member[0] === T_STRING) {
                if ($namespace === '') {
                    throw new RuntimeException($path . ' declares a type without a namespace.');
                }

                return ['type' => $namespace . '\\' . $member[1], 'kind' => $kind];
            }
        }
    }

    return null;
}

/**
 * @param list<array|string> $tokens Token stream.
 * @param int $offset Current token offset.
 *
 * @return array|string|null Previous non-whitespace/comment token.
 *
 * @since 0.2.0
 */
function previousSignificantToken(array $tokens, int $offset): array|string|null
{
    for ($index = $offset - 1; $index >= 0; $index--) {
        $token = $tokens[$index];
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return $token;
    }

    return null;
}

/**
 * @param array<string, mixed> $manifest Decoded manifest.
 * @param list<string> $surfaces Known dotted list surfaces.
 *
 * @return array<string, int> Non-empty list counts keyed by surface.
 *
 * @since 0.2.0
 */
function manifestSurfaceCounts(array $manifest, array $surfaces): array
{
    $contributions = $manifest['contributions'] ?? [];
    if (!is_array($contributions)) {
        return [];
    }
    $counts = [];
    foreach ($surfaces as $surface) {
        $value = dottedValue($contributions, $surface);
        if (is_array($value) && array_is_list($value) && $value !== []) {
            $counts[$surface] = count($value);
        }
    }

    return $counts;
}

/**
 * @param array<string, mixed> $manifest Decoded manifest.
 *
 * @return array<string, list<string>> Exact manifest-bound executable identifiers.
 *
 * @since 0.2.0
 */
function manifestExecutableBindings(array $manifest): array
{
    $mapping = [
        'administrator_route' => ['administrator.routes', 'name'],
        'portal_route' => ['portal.routes', 'name'],
        'field_presenter' => ['business.field_presentations', 'field_type'],
        'custom_business_view_handler' => ['business.view_handlers', 'handler'],
        'custom_business_action_handler' => ['business.action_handlers', 'handler'],
        'money_rate_provider' => ['integration.rate_providers', 'provider_id'],
        'unit_conversion_provider' => ['integration.unit_converters', 'provider_id'],
        'domain_listener' => ['integration.domain_listeners', 'listener_id'],
        'event_consumer' => ['integration.consumers', 'consumer_id'],
        'job_handler' => ['integration.jobs', 'job_type'],
        'projection' => ['integration.projections', 'identifier'],
        'webhook' => ['integration.webhooks', 'adapter_id'],
    ];
    $contributions = $manifest['contributions'] ?? [];
    if (!is_array($contributions)) {
        return [];
    }
    $bindings = [];
    foreach ($mapping as $kind => [$surface, $member]) {
        $items = dottedValue($contributions, $surface);
        if (!is_array($items) || !array_is_list($items)) {
            continue;
        }
        foreach ($items as $item) {
            if (is_array($item) && is_string($item[$member] ?? null)) {
                $bindings[$kind][] = $item[$member];
            }
        }
    }
    $hostBindings = dottedValue($contributions, 'composition.host_bindings');
    if (is_array($hostBindings) && array_is_list($hostBindings)) {
        foreach ($hostBindings as $binding) {
            if (
                is_array($binding)
                && ($binding['kind'] ?? null) === 'block-definition'
                && is_string($binding['renderer'] ?? null)
            ) {
                $bindings['studio_preview_renderer'][] = $binding['renderer'];
            }
        }
    }
    foreach ($bindings as $kind => $identifiers) {
        $identifiers = array_values(array_unique($identifiers));
        sort($identifiers, SORT_STRING);
        if ($identifiers === []) {
            unset($bindings[$kind]);
            continue;
        }
        $bindings[$kind] = $identifiers;
    }
    ksort($bindings, SORT_STRING);

    return $bindings;
}

/**
 * @param array<string, mixed> $object Root object.
 * @param string $path Dotted member path.
 *
 * @return mixed Resolved value, or null when absent.
 *
 * @since 0.2.0
 */
function dottedValue(array $object, string $path): mixed
{
    $value = $object;
    foreach (explode('.', $path) as $member) {
        if (!is_array($value) || !array_key_exists($member, $value)) {
            return null;
        }
        $value = $value[$member];
    }

    return $value;
}

/**
 * @param string $root Repository root.
 * @param string $directory Repository-relative fixture directory.
 *
 * @return string Stable SHA-256 over relative paths and file digests.
 *
 * @since 0.2.0
 */
function treeDigest(string $root, string $directory): string
{
    $members = [];
    foreach (trackedFiles($root, $directory) as $path) {
        $absolute = $root . '/' . $path;
        if (!is_file($absolute) || is_link($absolute)) {
            continue;
        }
        $relative = substr($path, strlen($directory) + 1);
        $members[$relative] = hash_file('sha256', $absolute);
    }
    ksort($members, SORT_STRING);

    return canonicalDigest($members);
}

/**
 * @param mixed $value Value to canonicalize.
 *
 * @return mixed Recursively key-sorted value.
 *
 * @since 0.2.0
 */
function canonicalValue(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    if (array_is_list($value)) {
        return array_map(canonicalValue(...), $value);
    }
    ksort($value, SORT_STRING);
    foreach ($value as $key => $member) {
        $value[$key] = canonicalValue($member);
    }

    return $value;
}

/** @param mixed $value Value to digest. @return string Lowercase SHA-256. @since 0.2.0 */
function canonicalDigest(mixed $value): string
{
    return hash('sha256', json_encode(canonicalValue($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

/**
 * @param string $bytes JSON bytes.
 * @param string $path Display path.
 *
 * @return array<string, mixed> Decoded object.
 *
 * @since 0.2.0
 */
function decodeObject(string $bytes, string $path): array
{
    $decoded = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($decoded) || array_is_list($decoded)) {
        throw new RuntimeException($path . ' is not a JSON object.');
    }

    return $decoded;
}

/** @param string $path File path. @return string File bytes. @since 0.2.0 */
function requiredBytes(string $path): string
{
    $bytes = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($bytes)) {
        throw new RuntimeException('Required SDK file cannot be read: ' . $path);
    }

    return $bytes;
}

/** @param string $path Destination path. @param array<string, mixed> $document Document. @since 0.2.0 */
function writeJson(string $path, array $document): void
{
    $bytes = json_encode(
        $document,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    ) . "\n";
    if (file_put_contents($path, $bytes) !== strlen($bytes)) {
        throw new RuntimeException('SDK contract could not be written: ' . $path);
    }
}
