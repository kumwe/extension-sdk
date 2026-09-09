<?php
/** Bound and validate archive entries before extracting an immutable source ZIP. */
declare(strict_types=1);
if ($argc !== 3 || file_exists($argv[2])) {
    throw new RuntimeException('Expected archive path and fresh extraction directory.');
}
$zip = new ZipArchive();
if ($zip->open($argv[1]) !== true || $zip->numFiles > 20000) {
    throw new RuntimeException('Invalid or unbounded source archive.');
}
$total = 0;
$names = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $stat = $zip->statIndex($i);
    $name = $stat['name'];
    if ($name === '' || str_contains($name, '\\') || str_starts_with($name, '/')
        || preg_match('~(^|/)\.{1,2}(/|$)|[\x00-\x1f]~', $name) || isset($names[$name])) {
        throw new RuntimeException('Unsafe or duplicate archive path.');
    }
    $names[$name] = true;
    $zip->getExternalAttributesIndex($i, $os, $attributes);
    $type = ($attributes >> 16) & 0170000;
    if ($type !== 0 && $type !== 0100000 && $type !== 0040000) {
        throw new RuntimeException('Archive link or special entry refused.');
    }
    $total += $stat['size'];
    if ($stat['size'] > 20000000 || $total > 150000000) {
        throw new RuntimeException('Archive expansion budget exceeded.');
    }
}
mkdir($argv[2], 0700, true);
if (!$zip->extractTo($argv[2])) {
    throw new RuntimeException('Archive extraction failed.');
}
$zip->close();
