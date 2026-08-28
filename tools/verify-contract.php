<?php

/**
 * Prove the vendored extension contract is exactly the pinned, frozen surface.
 *
 * Three obligations, one pass. First, every byte under `resources/` must match
 * the digest `resources/PIN.json` records, no pinned file may be missing, and
 * no unpinned file may hide in the tree — the pinned digests are the source
 * digests, so a green sweep is the proof of byte equality with the Kumwe App
 * copies at the recorded source commit. Second, every generation entry in
 * `resources/contract/generations.json` must still hash to its recorded
 * `surface_digest` over its own canonical bytes, exactly as the App's
 * `extension:contract` gate computes it, so widening a frozen generation fails
 * here until the change is made deliberately. Third, the documents must agree
 * with the artifacts beside them: every compatibility package is present with
 * its recorded manifest digest, every pin fixture a type or SPI generation
 * cites exists, and the classification stays a well-formed public surface.
 *
 * Dependency-free so it runs before any composer install. Invoked as
 * `php tools/verify-contract.php [--root=PATH]`, and as `composer contract`
 * inside `composer check`. The root override exists so a test can prove the
 * check fails in the right direction against a deliberately broken copy,
 * without committing that copy.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

$root = dirname(__DIR__) . '/resources';
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--root=')) {
        $root = substr($argument, strlen('--root='));
        continue;
    }
    fwrite(STDERR, "Unknown argument {$argument}. Usage: php tools/verify-contract.php [--root=PATH]\n");
    exit(1);
}

$errors = [];

// --- 1. The digest sweep: every vendored byte matches PIN.json, nothing extra, nothing missing.
$pinRaw = is_file($root . '/PIN.json') ? file_get_contents($root . '/PIN.json') : false;
$pin = is_string($pinRaw) ? json_decode($pinRaw, true) : null;
if (!is_array($pin) || !is_array($pin['files'] ?? null) || ($pin['format'] ?? null) !== 'kumwe-extension-sdk-resource-pin-v1') {
    fwrite(STDERR, "resources/PIN.json is missing or malformed.\n");
    exit(1);
}

$pinned = [];
foreach ($pin['files'] as $entry) {
    if (!is_array($entry) || !is_string($entry['file'] ?? null) || !is_string($entry['sha256'] ?? null)) {
        $errors[] = 'PIN.json carries a malformed file entry.';
        continue;
    }
    $pinned[$entry['file']] = $entry['sha256'];
}

$present = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->isLink()) {
        $errors[] = 'Symbolic link in the vendored contract: ' . $file->getPathname();
        continue;
    }
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if ($relative === 'PIN.json') {
        continue;
    }
    $present[$relative] = hash_file('sha256', $file->getPathname());
}
foreach ($pinned as $relative => $digest) {
    if (!isset($present[$relative])) {
        $errors[] = "Pinned artifact is missing: {$relative}";
    } elseif (!hash_equals($digest, $present[$relative])) {
        $errors[] = "Digest mismatch against the recorded source digest: {$relative}";
    }
}
foreach ($present as $relative => $digest) {
    if (!isset($pinned[$relative])) {
        $errors[] = "Unpinned file in the vendored contract: {$relative}";
    }
}

// --- 2. Decode the two contract documents.
$generations = contractDocument($root . '/contract/generations.json', $errors);
$classification = contractDocument($root . '/contract/classification.json', $errors);
if ($errors !== []) {
    reportContractFailure($errors);
}
if (($generations['format'] ?? null) !== 'kumwe-extension-contract-generations-v1') {
    $errors[] = 'generations.json does not declare the kumwe-extension-contract-generations-v1 format.';
}
if (($classification['format'] ?? null) !== 'kumwe-extension-contract-classification-v1') {
    $errors[] = 'classification.json does not declare the kumwe-extension-contract-classification-v1 format.';
}
if ($errors !== []) {
    reportContractFailure($errors);
}

$surfaces = [];
foreach (contractList($generations['contribution_surfaces'] ?? null, 'contribution_surfaces', $errors) as $surface) {
    if (!is_string($surface) || $surface === '') {
        $errors[] = 'Every contribution surface must be a non-empty dotted key.';
        continue;
    }
    $surfaces[$surface] = true;
}

// --- 3. Manifest generations: frozen digests, key statuses, and the vendored compatibility packages.
$manifestGenerations = [];
$fixturePackages = [];
foreach (contractList($generations['manifest_generations'] ?? null, 'manifest_generations', $errors) as $entry) {
    if (!is_array($entry) || !is_string($entry['id'] ?? null)) {
        $errors[] = 'Every manifest generation must be an object with an identifier.';
        continue;
    }
    $id = (string) $entry['id'];
    if (isset($manifestGenerations[$id])) {
        $errors[] = sprintf('Manifest generation %s is declared more than once.', $id);
    }
    $manifestGenerations[$id] = $entry;
    assertFrozenDigest($id, $entry, $errors);

    $schema = $entry['schema'] ?? null;
    if (!is_int($schema) || $schema < 1) {
        $errors[] = sprintf('Manifest generation %s has no positive schema number.', $id);
    }
    if (($entry['status'] ?? null) !== 'frozen') {
        $errors[] = sprintf('Manifest generation %s is listed but not frozen; withdraw it instead.', $id);
    }
    foreach (contractList($entry['manifest_keys'] ?? null, $id . '.manifest_keys', $errors) as $key) {
        if (
            !is_array($key)
            || !is_string($key['key'] ?? null)
            || !is_string($key['status'] ?? null)
            || !isset(($generations['key_statuses'] ?? [])[$key['status']])
        ) {
            $errors[] = sprintf('Manifest generation %s declares a key without a known status.', $id);
        }
    }

    $fixture = $entry['fixture'] ?? null;
    if (!is_array($fixture)) {
        $errors[] = sprintf('Manifest generation %s has no compatibility fixture.', $id);
        continue;
    }
    $package = $fixture['package'] ?? null;
    $vendored = is_string($package) ? vendoredPath($root, $package) : null;
    if ($vendored === null || !is_dir($vendored)) {
        $errors[] = sprintf('Manifest generation %s names a compatibility package that is not vendored.', $id);
        continue;
    }
    $fixturePackages[$id] = $package;
    $manifestFile = $vendored . '/kumwe.json';
    $manifestBytes = is_file($manifestFile) ? file_get_contents($manifestFile) : false;
    if (!is_string($manifestBytes)) {
        $errors[] = sprintf('Compatibility package %s has no readable kumwe.json.', (string) $package);
        continue;
    }
    if (($fixture['manifest_sha256'] ?? null) !== hash('sha256', $manifestBytes)) {
        $errors[] = sprintf(
            'Compatibility package %s no longer matches its recorded manifest digest. Its generation promises '
            . 'a fixed manifest shape: change the digest only when the change is a deliberate one.',
            (string) $package,
        );
    }
    /** @var mixed $decoded */
    $decoded = json_decode($manifestBytes, true);
    if (!is_array($decoded) || ($decoded['schema'] ?? null) !== $schema) {
        $errors[] = sprintf(
            'Compatibility package %s does not declare schema %s.',
            (string) $package,
            var_export($schema, true),
        );
    }
    if (is_array($decoded) && ($decoded['name'] ?? null) !== ($fixture['identifier'] ?? null)) {
        $errors[] = sprintf('Compatibility package %s does not carry its recorded identifier.', (string) $package);
    }
    $lifecycle = $fixture['lifecycle'] ?? null;
    if ($lifecycle !== ['install', 'activate', 'upgrade', 'disable', 'reactivate', 'uninstall']) {
        $errors[] = sprintf(
            'Compatibility package %s must declare the complete lifecycle: install, activate, upgrade, '
            . 'disable, reactivate, uninstall.',
            (string) $package,
        );
    }
    $contributions = $fixture['contributions'] ?? null;
    if (!is_array($contributions)) {
        $errors[] = sprintf('Compatibility package %s declares no expected contribution inventory.', (string) $package);
        continue;
    }
    foreach ($contributions as $surface => $count) {
        if (!is_string($surface) || !isset($surfaces[$surface])) {
            $errors[] = sprintf(
                'Compatibility package %s expects unknown contribution surface %s.',
                (string) $package,
                (string) $surface,
            );
        }
        if (!is_int($count) || $count < 1) {
            $errors[] = sprintf(
                'Compatibility package %s expects a non-positive count on %s.',
                (string) $package,
                (string) $surface,
            );
        }
    }
}

// --- 4. SPI generations: frozen digests, known surfaces, and the pins they cite.
$spiGenerations = [];
foreach (contractList($generations['spi_generations'] ?? null, 'spi_generations', $errors) as $entry) {
    if (!is_array($entry) || !is_string($entry['id'] ?? null)) {
        $errors[] = 'Every SPI generation must be an object with an identifier.';
        continue;
    }
    $id = (string) $entry['id'];
    if (isset($spiGenerations[$id])) {
        $errors[] = sprintf('SPI generation %s is declared more than once.', $id);
    }
    $spiGenerations[$id] = $entry;
    assertFrozenDigest($id, $entry, $errors);

    $version = $entry['version'] ?? null;
    if (!is_int($version) || $version < 1) {
        $errors[] = sprintf('SPI generation %s has no positive version.', $id);
    }
    if (($entry['status'] ?? null) !== 'frozen') {
        $errors[] = sprintf('SPI generation %s is listed but not frozen; withdraw it instead.', $id);
    }
    foreach (contractList($entry['surfaces'] ?? null, $id . '.surfaces', $errors) as $surface) {
        if (!is_string($surface) || !isset($surfaces[$surface])) {
            $errors[] = sprintf('SPI generation %s claims unknown contribution surface %s.', $id, (string) $surface);
        }
    }
    foreach (contractList($entry['manifest_schemas'] ?? null, $id . '.manifest_schemas', $errors) as $schema) {
        $matched = false;
        foreach ($manifestGenerations as $manifest) {
            if (($manifest['schema'] ?? null) === $schema && ($manifest['spi'] ?? null) === $version) {
                $matched = true;
            }
        }
        if (!$matched) {
            $errors[] = sprintf(
                'SPI generation %s claims manifest schema %s, which no manifest generation binds to it.',
                $id,
                var_export($schema, true),
            );
        }
    }
    foreach (contractList($entry['pinned_by'] ?? null, $id . '.pinned_by', $errors) as $fixture) {
        if (!is_string($fixture) || vendoredPath($root, $fixture) === null || !is_file((string) vendoredPath($root, $fixture))) {
            $errors[] = sprintf('SPI generation %s cites a compatibility fixture that is not vendored.', $id);
        }
    }
    foreach (contractList($entry['fixtures'] ?? null, $id . '.fixtures', $errors) as $package) {
        if (!is_string($package) || !in_array($package, $fixturePackages, true)) {
            $errors[] = sprintf('SPI generation %s cites a package no manifest generation ships.', $id);
        }
    }
}

// --- 5. Withdrawn surface stays recorded, never silently deleted.
$withdrawn = [];
foreach (contractList($generations['withdrawn'] ?? null, 'withdrawn', $errors) as $entry) {
    if (
        !is_array($entry)
        || !is_string($entry['type'] ?? null)
        || !is_string($entry['withdrawn_in'] ?? null)
        || !is_string($entry['reason'] ?? null)
        || ($entry['reason'] === '')
    ) {
        $errors[] = 'Every withdrawn entry needs the type it withdrew, where it went, and why.';
        continue;
    }
    $withdrawn[(string) $entry['type']] = true;
}

// --- 6. The classification: one public surface, every pin honoured by its fixture bytes.
$internalNamespaces = [];
foreach (contractList($classification['namespaces'] ?? null, 'namespaces', $errors) as $entry) {
    if (!is_array($entry) || !is_string($entry['prefix'] ?? null) || !is_string($entry['reason'] ?? null)) {
        $errors[] = 'Every classified namespace needs a prefix and a reason.';
        continue;
    }
    if (($entry['visibility'] ?? null) === 'internal') {
        $internalNamespaces[] = (string) $entry['prefix'];
    }
}

$pinnedFixtures = [];
$classified = [];
foreach (contractList($classification['types'] ?? null, 'types', $errors) as $entry) {
    if (!is_array($entry) || !is_string($entry['type'] ?? null)) {
        $errors[] = 'Every classified type must be an object naming the type.';
        continue;
    }
    $type = (string) $entry['type'];
    if (isset($classified[$type])) {
        $errors[] = sprintf('Type %s is classified more than once.', $type);
    }
    $classified[$type] = true;

    if (isset($withdrawn[$type])) {
        $errors[] = sprintf('Type %s is both classified public and recorded as withdrawn.', $type);
    }
    if (($entry['visibility'] ?? null) !== 'public') {
        $errors[] = sprintf('Type %s is listed but not public; the list holds the public surface only.', $type);
    }
    if (!in_array($entry['kind'] ?? null, ['interface', 'class', 'enum'], true)) {
        $errors[] = sprintf('Type %s has no known kind.', $type);
    }
    if (!isset(($classification['roles'] ?? [])[$entry['role'] ?? ''])) {
        $errors[] = sprintf('Type %s carries a role classification.json does not declare.', $type);
    }
    if (!isset($manifestGenerations[$entry['reachable_from'] ?? ''])) {
        $errors[] = sprintf('Type %s is reachable from a generation that is not declared.', $type);
    }
    $spi = $entry['spi'] ?? null;
    if ($spi !== null) {
        $known = false;
        foreach ($spiGenerations as $generation) {
            if (($generation['version'] ?? null) === $spi) {
                $known = true;
            }
        }
        if (!$known) {
            $errors[] = sprintf(
                'Type %s names contribution SPI %s, which is not a declared generation.',
                $type,
                var_export($spi, true),
            );
        }
    }
    foreach ($internalNamespaces as $prefix) {
        if (str_starts_with($type, $prefix)) {
            $errors[] = sprintf('Type %s is classified public inside internal namespace %s.', $type, $prefix);
        }
    }

    $pinnedBy = $entry['pinned_by'] ?? null;
    if ($pinnedBy === null) {
        continue;
    }
    $pinPath = is_string($pinnedBy) ? vendoredPath($root, $pinnedBy) : null;
    if ($pinPath === null || !is_file($pinPath)) {
        $errors[] = sprintf('Type %s cites a compatibility fixture that is not vendored.', $type);
        continue;
    }
    if (!isset($pinnedFixtures[$pinnedBy])) {
        $pinnedFixtures[$pinnedBy] = pinnedTypes($pinPath);
    }
    if (!isset($pinnedFixtures[$pinnedBy][$type])) {
        $errors[] = sprintf('Type %s claims fixture %s pins it, and that fixture does not name it.', $type, $pinnedBy);
    }
}

// --- 7. The host-service allowlist promises classified types only. The other half of this check —
// that the App's composition root hands out exactly this list — runs in the App's own gate, where
// the composition root lives.
$allowlist = $generations['host_services']['services'] ?? null;
if (!is_array($allowlist) || !array_is_list($allowlist)) {
    $errors[] = 'generations.json declares no host-service allowlist.';
} else {
    foreach ($allowlist as $service) {
        if (!is_string($service) || !isset($classified[$service])) {
            $errors[] = sprintf('Host service %s is allowlisted but not classified public.', (string) $service);
        }
    }
}

if ($errors !== []) {
    reportContractFailure($errors);
}

$commit = $pin['source']['commit'] ?? 'unknown';
fwrite(STDOUT, sprintf(
    "Kumwe extension contract verified: %d artifacts pinned at %s; %d manifest generations, "
    . "%d SPI generations, %d public types, %d withdrawn.\n",
    count($pinned),
    is_string($commit) ? substr($commit, 0, 12) : 'unknown',
    count($manifestGenerations),
    count($spiGenerations),
    count($classified),
    count($withdrawn),
));
exit(0);

/**
 * Map a repository-relative App path recorded in a contract document to its vendored artifact.
 *
 * The documents are verbatim frozen artifacts, so the App paths inside them are not rewritten;
 * this mapping is owned by the verifier instead. Only the known artifact families resolve.
 *
 * @param   string  $root  Vendored resources root.
 * @param   string  $path  Repository-relative path as the App document records it.
 *
 * @return  ?string  Absolute vendored path, or null when the path names no vendored family.
 *
 * @since   0.1.0
 */
function vendoredPath(string $root, string $path): ?string
{
    $map = [
        'tests/Fixtures/ExtensionApi/generations/' => $root . '/fixtures/generations/',
        'docs/extension-contract/' => $root . '/contract/',
    ];
    foreach ($map as $prefix => $target) {
        if (str_starts_with($path, $prefix)) {
            return $target . substr($path, strlen($prefix));
        }
    }
    if (preg_match('#^tests/Fixtures/ExtensionApi/(schema-[1-9][0-9]*)/(kumwe\.json)$#D', $path, $match) === 1) {
        return $root . '/fixtures/schemas/' . $match[1] . '/' . $match[2];
    }
    if (preg_match('#^tests/Fixtures/ExtensionApi/([A-Za-z0-9.-]+\.json)$#D', $path, $match) === 1) {
        return $root . '/fixtures/pins/' . $match[1];
    }

    return null;
}

/**
 * Recompute a generation's freeze digest from its own canonical bytes and compare it to the record.
 *
 * @param   string                $id      Generation identifier used in the failure message.
 * @param   array<string, mixed>  $entry   The generation entry, including its recorded digest.
 * @param   list<string>          $errors  Accumulated validation failures.
 *
 * @return  void
 *
 * @since   0.1.0
 */
function assertFrozenDigest(string $id, array $entry, array &$errors): void
{
    $recorded = $entry['surface_digest'] ?? null;
    unset($entry['surface_digest']);
    $canonical = json_encode(canonicalContract($entry), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($canonical)) {
        $errors[] = sprintf('Generation %s could not be canonicalized.', $id);

        return;
    }
    $digest = hash('sha256', $canonical);
    if (!is_string($recorded) || !hash_equals($digest, $recorded)) {
        $errors[] = sprintf(
            'Generation %s promises something other than its recorded frozen surface. Its digest is now %s. '
            . 'A frozen generation does not change: add a generation beside it, or, if the change really is '
            . 'deliberate, record the new digest in the same change that makes it.',
            $id,
            $digest,
        );
    }
}

/**
 * Order a decoded value deterministically so its encoding does not depend on key order.
 *
 * @param   mixed  $value  Decoded JSON value.
 *
 * @return  mixed  The same value with every object's keys sorted.
 *
 * @since   0.1.0
 */
function canonicalContract(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    $mapped = array_map(static fn (mixed $item): mixed => canonicalContract($item), $value);
    if (!array_is_list($mapped)) {
        ksort($mapped, SORT_STRING);
    }

    return $mapped;
}

/**
 * Read the fully qualified type names one compatibility fixture pins.
 *
 * @param   string  $path  Absolute path to the fixture document.
 *
 * @return  array<string, true>  Pinned type names held as a set.
 *
 * @since   0.1.0
 */
function pinnedTypes(string $path): array
{
    $raw = file_get_contents($path);
    /** @var mixed $document */
    $document = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($document)) {
        return [];
    }
    $names = [];
    foreach (array_keys($document['interfaces'] ?? []) as $name) {
        $names[(string) $name] = true;
    }
    foreach (array_keys($document['enums'] ?? []) as $name) {
        $names[(string) $name] = true;
    }
    if (is_string($document['interface'] ?? null)) {
        $names[$document['interface']] = true;
    }

    return $names;
}

/**
 * Read one JSON document and record a failure when it is absent or malformed.
 *
 * @param   string        $path    Absolute path to the document.
 * @param   list<string>  $errors  Accumulated validation failures.
 *
 * @return  array<string, mixed>  Decoded document, or an empty array when it could not be read.
 *
 * @since   0.1.0
 */
function contractDocument(string $path, array &$errors): array
{
    $name = basename($path);
    if (!is_file($path)) {
        $errors[] = sprintf('%s is missing.', $name);

        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        $errors[] = sprintf('%s could not be read.', $name);

        return [];
    }
    /** @var mixed $decoded */
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        $errors[] = sprintf('%s is not well-formed JSON: %s.', $name, json_last_error_msg());

        return [];
    }

    /** @var array<string, mixed> $decoded */
    return $decoded;
}

/**
 * Require a value to be a JSON array and return it as a list.
 *
 * @param   mixed         $value   Candidate value.
 * @param   string        $label   Diagnostic label naming the member.
 * @param   list<string>  $errors  Accumulated validation failures.
 *
 * @return  list<mixed>  The list, or an empty list when the value was the wrong shape.
 *
 * @since   0.1.0
 */
function contractList(mixed $value, string $label, array &$errors): array
{
    if (!is_array($value) || !array_is_list($value)) {
        $errors[] = sprintf('The extension contract member "%s" must be a JSON array.', $label);

        return [];
    }

    return $value;
}

/**
 * Print every failure and terminate with a non-zero status.
 *
 * @param   list<string>  $errors  Validation failures.
 *
 * @return  never
 *
 * @since   0.1.0
 */
function reportContractFailure(array $errors): never
{
    $errors = array_values(array_unique($errors));
    fwrite(STDERR, "Kumwe extension contract verification failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, ' - ' . $error . "\n");
    }
    exit(1);
}
