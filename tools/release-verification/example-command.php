<?php

declare(strict_types=1);

/** Execute an unchanged installed example in the real consumer's Composer context. */
function releaseVerificationExampleCommand(string $php, string $example, string $autoload): array
{
    if (!is_file($example) || !is_file($autoload)) {
        throw new RuntimeException('The installed example and actual consumer autoloader must exist.');
    }
    return [$php, '-d', 'auto_prepend_file=' . $autoload, $example, $autoload];
}
