<?php

/** Prove external verification receipts never enter either SDK source/archive distribution. @since 0.3.0 */

declare(strict_types=1);

$root = dirname(__DIR__);
$composer = json_decode((string) file_get_contents($root . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
if (!in_array('/evidence', $composer['archive']['exclude'] ?? [], true)
    || !in_array('/evidence/**', $composer['archive']['exclude'] ?? [], true)
    || preg_match('~^/evidence export-ignore$~m', (string) file_get_contents($root . '/.gitattributes')) !== 1) {
    throw new RuntimeException('SDK external evidence needs both Git and Composer archive exclusions.');
}
$workspace = sys_get_temp_dir() . '/kumwe-sdk-archive-boundary-' . bin2hex(random_bytes(8));
mkdir($workspace, 0700);
try {
    foreach ([
        ['git', '-C', $root, 'archive', '--format=zip', '--output=' . $workspace . '/git.zip', 'HEAD'],
        ['composer', '--working-dir=' . $root, 'archive', '--format=zip', '--dir=' . $workspace, '--file=composer'],
    ] as $command) {
        $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
        if (!is_resource($process) || proc_close($process) !== 0) {
            throw new RuntimeException('Could not build the actual archive boundary fixture.');
        }
    }
    foreach (['git.zip', 'composer.zip'] as $name) {
        $zip = new ZipArchive();
        if ($zip->open($workspace . '/' . $name) !== true) {
            throw new RuntimeException('Cannot inspect the actual SDK archive.');
        }
        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $path = $zip->getNameIndex($index);
                if (!is_string($path) || $path === 'evidence' || str_starts_with($path, 'evidence/')) {
                    throw new RuntimeException('External release evidence entered the SDK distribution: ' . $name);
                }
            }
            if ($zip->getFromName('src/Manifest/ExtensionManifest.php') === false
                || $zip->getFromName('MIGRATION-HANDOFF.md') === false) {
                throw new RuntimeException('Archive boundary proof must retain actual SDK source and its handoff.');
            }
        } finally {
            $zip->close();
        }
    }
    echo "Both Git and Composer SDK archives exclude external release evidence and retain SDK source/handoff.\n";
} finally {
    foreach (glob($workspace . '/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($workspace);
}
