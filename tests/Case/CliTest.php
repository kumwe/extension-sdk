<?php

/**
 * Proves the installed `kumwe-extension` command runs every lane against scaffold output.
 *
 * @since 0.2.4
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use Kumwe\Extension\Tests\TestCase;
use Kumwe\Extension\Toolchain\ComponentScaffolder;
use Kumwe\Extension\Toolchain\ScaffoldRequest;
use ZipArchive;

/**
 * Executes `bin/kumwe-extension` as a subprocess, the way an author's shell or CI invokes it.
 *
 * The command is composed from the same production objects the PHP API exposes, so the only thing
 * the PHP suite cannot prove in-process is that the bootstrap itself composes them from constructors
 * that exist. 0.2.3 shipped a bootstrap that fatalled on every real command; these tests hold each
 * lane — `help`, `build`, `inspect`, `evidence`, `conformance` — to its exit code and report.
 *
 * @since  0.2.4
 */
final class CliTest extends TestCase
{
    /**
     * The help text answers explicit requests with success and a missing command with usage failure.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testHelpListsEveryLaneAndAMissingCommandIsAUsageError(): void
    {
        foreach (['help', '--help', '-h'] as $flag) {
            [$status, $stdout] = $this->run([$flag]);
            $this->assertSame(0, $status, sprintf('%s exits successfully.', $flag));
            $this->assertStringContains('Usage:', $stdout, 'The help text carries a usage section.');
            foreach (['build', 'inspect', 'evidence', 'conformance'] as $lane) {
                $this->assertStringContains(
                    'kumwe-extension ' . $lane . ' ',
                    $stdout,
                    sprintf('The help text names the %s lane.', $lane),
                );
            }
        }

        [$status, $stdout] = $this->run([]);
        $this->assertSame(2, $status, 'A missing command is a usage error.');
        $this->assertStringContains('Usage:', $stdout, 'A missing command still prints the usage text.');
    }

    /**
     * Build, inspect, evidence and conformance succeed in sequence over one scaffolded component.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testEveryLaneRunsAgainstTheScaffoldedComponent(): void
    {
        $work = $this->workspace();
        $source = $work . '/component';
        (new ComponentScaffolder(self::encoder()))->scaffold(new ScaffoldRequest(
            'acme/cli-component',
            'Acme\\CliComponent',
            $source,
            'CLI Component',
        ));
        $archive = $work . '/cli-component.zip';

        [$status, $stdout, $stderr] = $this->run(['build', $source, '--output=' . $archive]);
        $this->assertSame(0, $status, 'build exits successfully: ' . $stderr);
        $build = $this->json($stdout, 'build');
        $this->assertSame($archive, $build['archive'], 'build reports the published archive path.');
        $this->assertTrue(is_file($archive), 'build publishes the archive at the requested path.');
        $this->assertTrue(
            is_string($build['package_sha256']) && preg_match('/^[a-f0-9]{64}$/D', $build['package_sha256']) === 1,
            'build reports the package digest.',
        );
        $this->assertTrue(
            is_int($build['entry_count']) && $build['entry_count'] > 10,
            'build reports the number of packaged entries.',
        );

        [$status, $stdout, $stderr] = $this->run(['inspect', $archive]);
        $this->assertSame(0, $status, 'inspect exits successfully: ' . $stderr);
        $inspection = $this->json($stdout, 'inspect');
        $this->assertSame('kumwe-extension-inspection-v2', $inspection['format'], 'inspect reports its format.');
        $this->assertSame($build['package_sha256'], $inspection['package_sha256'], 'inspect sees the built bytes.');
        $this->assertSame('acme/cli-component', $inspection['manifest']['name'], 'inspect parses the manifest.');
        $this->assertSame([], $inspection['archive_findings'], 'A deterministic build has no archive finding.');
        $this->assertTrue(
            is_array($inspection['paths']) && in_array('kumwe.json', $inspection['paths'], true),
            'inspect lists the package paths.',
        );

        [$status, $stdout, $stderr] = $this->run(['evidence', $archive]);
        $this->assertSame(0, $status, 'evidence exits successfully: ' . $stderr);
        $evidence = $this->json($stdout, 'evidence');
        $this->assertSame('kumwe-extension-evidence-v2', $evidence['format'], 'evidence reports its format.');
        $this->assertSame('authoring', $evidence['scope'], 'The command reports complete authoring evidence.');
        $this->assertSame('verified', $evidence['sbom']['state'], 'The generated inventory verifies.');
        $this->assertSame('verified', $evidence['provenance']['state'], 'The generated statement verifies.');
        $this->assertSame([], $evidence['findings'], 'A clean build carries no evidence finding.');

        [$status, $stdout, $stderr] = $this->run(['conformance', $archive]);
        $this->assertSame(0, $status, 'conformance exits successfully: ' . $stderr);
        $report = $this->json($stdout, 'conformance');
        $this->assertSame('kumwe-extension-conformance-v2', $report['format'], 'conformance reports its format.');
        $this->assertSame(true, $report['conforms'], 'A scaffolded build conforms.');
        $this->assertSame([], $report['findings'], 'A scaffolded build has no finding.');
        $this->assertTrue(
            is_array($report['checks']) && !in_array(false, $report['checks'], true),
            'Every named check holds.',
        );
        $this->removeTree($work);
    }

    /**
     * A nonconforming archive is still reported as facts, and only the conformance lane exits nonzero.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testConformanceExitsNonzeroForANonconformingArchiveWhileEvidenceReportsIt(): void
    {
        $work = $this->workspace();
        $archive = $work . '/hand-made.zip';
        $manifest = (string) file_get_contents(
            dirname(__DIR__, 2) . '/resources/fixtures/generations/manifest-1/kumwe.json',
        );
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL) === true, 'The ZIP opens.');
        $this->assertTrue($zip->addFromString('kumwe.json', $manifest), 'The manifest is added.');
        $this->assertTrue($zip->close(), 'The ZIP closes.');

        [$status, $stdout] = $this->run(['conformance', $archive]);
        $this->assertSame(1, $status, 'A nonconforming archive fails the conformance lane.');
        $report = $this->json($stdout, 'conformance');
        $this->assertSame(false, $report['conforms'], 'The report records the nonconformance.');
        $this->assertTrue(
            in_array('attestation.sbom.missing', array_column($report['findings'], 'code'), true),
            'The missing inventory is a coded finding.',
        );

        [$status, $stdout] = $this->run(['evidence', $archive]);
        $this->assertSame(0, $status, 'The evidence lane reports facts without deciding.');
        $evidence = $this->json($stdout, 'evidence');
        $this->assertSame('absent', $evidence['sbom']['state'], 'The absent inventory is reported.');
        $this->assertSame('absent', $evidence['provenance']['state'], 'The absent statement is reported.');
        $this->removeTree($work);
    }

    /**
     * Malformed invocations are refused with a message and a nonzero exit before any work starts.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testMalformedInvocationsAreRefused(): void
    {
        $work = $this->workspace();

        [$status, , $stderr] = $this->run(['inspect', 'relative.zip']);
        $this->assertSame(2, $status, 'A relative target is a usage error.');
        $this->assertStringContains('canonical absolute path', $stderr, 'The relative target is explained.');

        [$status, , $stderr] = $this->run(['inspect']);
        $this->assertSame(2, $status, 'A missing target is a usage error.');

        [$status, , $stderr] = $this->run(['publish', $work . '/never.zip']);
        $this->assertSame(1, $status, 'An unknown command fails.');
        $this->assertStringContains('Unknown command publish.', $stderr, 'The unknown command is named.');

        [$status, , $stderr] = $this->run(['build', $work]);
        $this->assertSame(1, $status, 'A build without an output path fails.');
        $this->assertStringContains('--output=ABSOLUTE_ARCHIVE', $stderr, 'The missing output option is explained.');

        [$status, , $stderr] = $this->run(['build', $work, '--output=' . $work . '/out.zip', '--force']);
        $this->assertSame(1, $status, 'An unknown build argument fails.');
        $this->assertStringContains('Unknown build argument --force.', $stderr, 'The unknown argument is named.');

        [$status, $stdout, $stderr] = $this->run(['inspect', $work . '/missing.zip']);
        $this->assertSame(1, $status, 'A missing archive fails.');
        $this->assertSame('', $stdout, 'A failed lane prints no report.');
        $this->assertTrue($stderr !== '', 'A failed lane explains itself on standard error.');
        $this->removeTree($work);
    }

    /**
     * Execute the shipped command as a subprocess through the same interpreter running the suite.
     *
     * @param   list<string>  $arguments  Command line arguments after the executable.
     *
     * @return  array{int, string, string}  Exit status, standard output and standard error.
     *
     * @since   0.2.4
     */
    private function run(array $arguments): array
    {
        $command = array_merge([PHP_BINARY, dirname(__DIR__, 2) . '/bin/kumwe-extension'], $arguments);
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 2),
        );
        $this->assertTrue(is_resource($process), 'The command process starts.');
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }

    /**
     * Decode one lane's JSON report.
     *
     * @param   string  $stdout  Complete standard output of the lane.
     * @param   string  $lane    Lane name for diagnostics.
     *
     * @return  array<string, mixed>  Decoded report object.
     *
     * @since   0.2.4
     */
    private function json(string $stdout, string $lane): array
    {
        $decoded = json_decode($stdout, true);
        $this->assertTrue(
            is_array($decoded) && !array_is_list($decoded),
            sprintf('%s prints one JSON object: %s', $lane, $stdout),
        );

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Allocate a private working directory for one test.
     *
     * @return  string  Absolute path of the writable directory.
     *
     * @since   0.2.4
     */
    private function workspace(): string
    {
        $work = sys_get_temp_dir() . '/kumwe-sdk-cli-' . bin2hex(random_bytes(8));
        mkdir($work, 0700);

        return $work;
    }

    /**
     * Remove one private working directory created by this test.
     *
     * @param   string  $root  Absolute path of the tree to remove.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    private function removeTree(string $root): void
    {
        if (!str_starts_with($root, sys_get_temp_dir() . '/kumwe-sdk-cli-')) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if (!$entry instanceof \SplFileInfo) {
                continue;
            }
            $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($root);
    }
}
