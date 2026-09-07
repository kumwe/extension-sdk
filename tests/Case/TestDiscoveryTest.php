<?php

/** Regression coverage for fail-closed package test discovery. @since 0.2.5 */
declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use Kumwe\Extension\Tests\TestCase;

final class TestDiscoveryTest extends TestCase
{
    public function testEmptySuitesAndEmptyCasesFailBothExecutionAndInventory(): void
    {
        $root = sys_get_temp_dir() . '/kumwe-sdk-discovery-' . bin2hex(random_bytes(8));
        mkdir($root . '/tests/Case', 0700, true);
        mkdir($root . '/vendor', 0700, true);
        copy(dirname(__DIR__) . '/run.php', $root . '/tests/run.php');
        copy(dirname(__DIR__) . '/TestCase.php', $root . '/tests/TestCase.php');
        file_put_contents($root . '/vendor/autoload.php', '<?php');
        try {
            foreach (['', ' --list-json'] as $argument) {
                $output = [];
                exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/run.php') . $argument
                    . ' 2>&1', $output, $status);
                $this->assertSame(1, $status, 'An empty suite must fail in every discovery mode.');
            }
            file_put_contents($root . '/tests/Case/EmptyTest.php', '<?php namespace Kumwe\\Extension\\Tests\\Case; '
                . 'final class EmptyTest extends \\Kumwe\\Extension\\Tests\\TestCase {}');
            foreach (['', ' --list-json'] as $argument) {
                $output = [];
                exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/run.php') . $argument
                    . ' 2>&1', $output, $status);
                $this->assertSame(1, $status, 'An empty case cannot make the package appear tested.');
                $this->assertStringContains('Empty test case', implode("\n", $output), 'Failure names the missing tests.');
            }
        } finally {
            @unlink($root . '/tests/Case/EmptyTest.php');
            unlink($root . '/tests/run.php');
            unlink($root . '/tests/TestCase.php');
            unlink($root . '/vendor/autoload.php');
            rmdir($root . '/tests/Case');
            rmdir($root . '/tests');
            rmdir($root . '/vendor');
            rmdir($root);
        }
    }
}
