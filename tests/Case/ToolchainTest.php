<?php

/**
 * Proves the host-neutral author toolchain produces safe, deterministic packages.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use ArrayObject;
use InvalidArgumentException;
use Kumwe\Extension\Package\PackageChecksum;
use Kumwe\Extension\Package\PackageFinding;
use Kumwe\Extension\Package\PackageLimits;
use Kumwe\Extension\Package\PackageSignature;
use Kumwe\Extension\Package\SodiumPublicKeyPackageSignatureVerifier;
use Kumwe\Extension\Tests\TestCase;
use Kumwe\Extension\Toolchain\ComponentScaffolder;
use Kumwe\Extension\Toolchain\ConformanceReport;
use Kumwe\Extension\Toolchain\DeterministicPackageBuilder;
use Kumwe\Extension\Toolchain\LifecycleConformanceAdapter;
use Kumwe\Extension\Toolchain\LifecycleConformanceRunner;
use Kumwe\Extension\Toolchain\PackageInspector;
use Kumwe\Extension\Toolchain\PackageSigner;
use Kumwe\Extension\Toolchain\ProtectedSigningKeyReader;
use Kumwe\Extension\Toolchain\ScaffoldRequest;
use Kumwe\Extension\Toolchain\SignatureDocument;
use Kumwe\Extension\Toolchain\StaticConformanceRunner;
use RuntimeException;

/**
 * The complete scaffold, deterministic build, inspection, conformance and signing path.
 *
 * @since  0.1.0
 */
final class ToolchainTest extends TestCase
{
    /**
     * Two builds of one scaffolded tree are identical and the result passes every static check.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testCompleteScaffoldBuildsReproduciblyAndConforms(): void
    {
        $work = $this->workspace();
        $source = $work . '/component';
        $result = (new ComponentScaffolder())->scaffold(new ScaffoldRequest(
            'acme/quality-component',
            'Acme\\QualityComponent',
            $source,
            "Owner's Quality Component",
        ));
        $this->assertTrue($result->fileCount >= 10, 'The shipped template generates a complete component.');

        $inspector = $this->inspector();
        $builder = new DeterministicPackageBuilder($inspector);
        $first = $builder->build($source, $work . '/first.zip');
        $second = $builder->build($source, $work . '/second.zip');

        $this->assertSame(
            (string) $first->inspection->package->checksum,
            (string) $second->inspection->package->checksum,
            'Two builds of the same tree produce the same digest.',
        );
        $this->assertSame(
            'acme/quality-component',
            $first->inspection->package->manifest->identifier()->value(),
            'The built package parses back to its identity.',
        );
        $report = (new StaticConformanceRunner($inspector))->run($first->archive);
        $this->assertTrue($report->conforms(), 'A scaffolded build passes: ' . $this->messages($report->findings));
        $this->assertTrue(
            !(new ConformanceReport($report->inspection, ['forced_failure' => false], []))->conforms(),
            'A failed named check prevents author conformance even without findings.',
        );
        $this->removeTree($work);
    }

    /**
     * Scaffold input preserves every canonical package character used by the contribution namespace.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testScaffoldRequestPreservesExtensionCompatibleContributionNamespace(): void
    {
        $request = new ScaffoldRequest(
            'ac9.me/orders_v1',
            'Acme\\Orders',
            sys_get_temp_dir() . '/kumwe-sdk-never-created',
            'Orders',
        );

        $this->assertSame('ac9.me/orders_v1', $request->identifier->value(), 'The identifier survives.');
        $this->assertSame('ac9.me.orders_v1', $request->contributionNamespace(), 'The dotted namespace derives.');
    }

    /**
     * Development cache material is omitted and common private-key or environment files fail closed.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testBuilderOmitsDevelopmentCachesAndRejectsSensitiveFiles(): void
    {
        $work = $this->workspace();
        $source = $work . '/component';
        (new ComponentScaffolder())->scaffold(new ScaffoldRequest(
            'acme/safe-component',
            'Acme\\SafeComponent',
            $source,
            'Safe Component',
        ));
        mkdir($source . '/.phpunit.cache', 0700);
        file_put_contents($source . '/.phpunit.cache/results', "test-results\n", LOCK_EX);
        $builder = new DeterministicPackageBuilder($this->inspector());
        $result = $builder->build($source, $work . '/without-cache.zip');

        $this->assertTrue(
            !in_array('.gitignore', $result->inspection->package->paths(), true),
            'Version-control material stays out of the archive.',
        );
        $this->assertTrue(
            !in_array('.phpunit.cache/results', $result->inspection->package->paths(), true),
            'Cache material stays out of the archive.',
        );

        file_put_contents($source . '/.env.production', "SECRET=value\n", LOCK_EX);
        $this->assertThrows(
            static fn () => $builder->build($source, $work . '/unsafe.zip'),
            RuntimeException::class,
            'A packaged environment file must fail the build closed.',
        );
        $this->removeTree($work);
    }

    /**
     * Strict-types text inside a comment cannot satisfy the executable declaration gate.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testStaticConformanceRejectsCommentOnlyStrictTypesMarker(): void
    {
        $work = $this->workspace();
        $source = $work . '/component';
        (new ComponentScaffolder())->scaffold(new ScaffoldRequest(
            'acme/non-strict-component',
            'Acme\\NonStrictComponent',
            $source,
            'Non-strict Component',
        ));
        $path = $source . '/src/Application/OverviewService.php';
        $contents = (string) file_get_contents($path);
        $unsafe = str_replace('declare(strict_types=1);', '// declare(strict_types=1);', $contents, $replacements);
        $this->assertSame(1, $replacements, 'Exactly one declaration is commented out.');
        file_put_contents($path, $unsafe, LOCK_EX);

        $archive = (new DeterministicPackageBuilder($this->inspector()))
            ->build($source, $work . '/non-strict.zip')->archive;
        $report = (new StaticConformanceRunner($this->inspector()))->run($archive);

        $this->assertTrue(!$report->conforms(), 'The commented declaration fails conformance.');
        $this->assertSame(false, $report->checks['strict_types'], 'The strict-types check is the one that fails.');
        $this->assertTrue(
            in_array(
                'PHP file src/Application/OverviewService.php must declare strict_types=1.',
                $this->messagesList($report->findings),
                true,
            ),
            'The violation names the file with the stable message.',
        );
        $this->removeTree($work);
    }

    /**
     * Protected seed decoding produces a verifiable detached signature sidecar.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testProtectedSigningProducesPortableVerifiableSidecar(): void
    {
        $work = $this->workspace();
        $source = $work . '/component';
        (new ComponentScaffolder())->scaffold(new ScaffoldRequest(
            'acme/signed-component',
            'Acme\\SignedComponent',
            $source,
            'Signed Component',
        ));
        $inspector = $this->inspector();
        $archive = (new DeterministicPackageBuilder($inspector))->build($source, $work . '/signed.zip')->archive;
        $seed = random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES);
        $keyFile = $work . '/signing.key';
        file_put_contents($keyFile, bin2hex($seed), LOCK_EX);
        chmod($keyFile, 0600);

        $signer = new PackageSigner(new ProtectedSigningKeyReader(), $inspector);
        $document = $signer->sign($archive, 'release-2026', $keyFile);
        $sidecar = $work . '/signed.signature.json';
        $signer->write($document, $sidecar);
        $decoded = SignatureDocument::fromJson((string) file_get_contents($sidecar));
        $public = sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair($seed));

        $signature = PackageSignature::ed25519($decoded->keyId, $decoded->base64Signature);
        $this->assertTrue(
            (new SodiumPublicKeyPackageSignatureVerifier())->verify(
                base64_encode($public),
                PackageChecksum::sha256($decoded->packageSha256),
                $signature,
            ),
            'The written sidecar verifies through the canonical SDK primitive.',
        );
        $this->assertTrue(
            !sodium_crypto_sign_verify_detached($signature->bytes(), $decoded->packageSha256, $public),
            'The signature is domain separated from a bare checksum signature.',
        );
        $this->removeTree($work);
    }

    /**
     * Signing material with group access is rejected before decoding.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testSigningKeyReaderRejectsGroupReadableFiles(): void
    {
        $work = $this->workspace();
        $keyFile = $work . '/unsafe.key';
        file_put_contents($keyFile, bin2hex(random_bytes(32)), LOCK_EX);
        chmod($keyFile, 0640);

        $this->assertThrows(
            static fn (): string => (new ProtectedSigningKeyReader())->read($keyFile),
            InvalidArgumentException::class,
            'A group-readable signing key must be rejected.',
        );
        $this->removeTree($work);
    }

    /**
     * The lifecycle runner invokes every explicit conformance gate in dependency order.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testLifecycleConformanceRunsEveryGateAndRecovery(): void
    {
        $work = $this->workspace();
        [$base, $upgrade] = $this->lifecyclePackages($work);
        $calls = new ArrayObject();
        $report = (new LifecycleConformanceRunner(new StaticConformanceRunner($this->inspector())))->run(
            $this->lifecycleAdapter($calls),
            $base,
            $upgrade,
        );

        $this->assertTrue($report->conforms(), implode("\n", $report->violations));
        $this->assertTrue(!in_array(false, $report->checks, true), 'Every gate passed.');
        $this->assertSame([
            'package_safety_and_signing',
            'schema_plan',
            'install',
            'definitions',
            'authorization_and_field_policies',
            'routes',
            'rest_and_openapi',
            'cli_and_mcp',
            'jobs_events_and_reports',
            'portal_and_administrator',
            'backup_and_restore',
            'upgrade',
            'disable',
            'reactivate',
            'database_matrix',
            'uninstall',
            'recovery',
        ], $calls->getArrayCopy(), 'The platform gates run in dependency order, recovery last.');
        $this->removeTree($work);
    }

    /**
     * A failed gate stops dependent gates but cannot suppress recovery.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function testLifecycleConformanceStopsAfterFailureAndStillRecovers(): void
    {
        $work = $this->workspace();
        [$base, $upgrade] = $this->lifecyclePackages($work);
        $calls = new ArrayObject();
        $report = (new LifecycleConformanceRunner(new StaticConformanceRunner($this->inspector())))->run(
            $this->lifecycleAdapter($calls, 'definitions'),
            $base,
            $upgrade,
        );

        $this->assertTrue(!$report->conforms(), 'A failed gate refuses conformance.');
        $this->assertSame(true, $report->checks['static_base_package'], 'The static leg passed first.');
        $this->assertSame(true, $report->checks['install'], 'Install ran before the failure.');
        $this->assertSame(false, $report->checks['definitions'], 'The failed gate is recorded as failed.');
        $this->assertSame(
            false,
            $report->checks['authorization_and_field_policies'],
            'A dependent gate never ran.',
        );
        $this->assertSame(true, $report->checks['recovery'], 'Recovery still ran.');
        $this->assertSame([
            'package_safety_and_signing',
            'schema_plan',
            'install',
            'definitions',
            'recovery',
        ], $calls->getArrayCopy(), 'The failure stopped the sequence, then recovery ran.');
        $this->assertSame(
            ['definitions: forced definitions failure'],
            $report->violations,
            'The violation names the gate and its platform evidence.',
        );
        $this->removeTree($work);
    }

    /**
     * Build canonical base and upgrade packages used by the lifecycle-runner contract tests.
     *
     * @param   string  $work  Private directory for this test.
     *
     * @return  array{string, string}  Base and upgrade archive paths.
     *
     * @since   0.1.0
     */
    private function lifecyclePackages(string $work): array
    {
        (new ComponentScaffolder())->scaffold(new ScaffoldRequest(
            'acme/lifecycle-component',
            'Acme\\LifecycleComponent',
            $work . '/lifecycle-base-source',
            'Lifecycle Component',
        ));
        (new ComponentScaffolder())->scaffold(new ScaffoldRequest(
            'acme/lifecycle-component',
            'Acme\\LifecycleComponent',
            $work . '/lifecycle-upgrade-source',
            'Lifecycle Component',
            '1.1.0',
        ));
        $builder = new DeterministicPackageBuilder($this->inspector());

        return [
            $builder->build($work . '/lifecycle-base-source', $work . '/lifecycle-base.zip')->archive,
            $builder->build($work . '/lifecycle-upgrade-source', $work . '/lifecycle-upgrade.zip')->archive,
        ];
    }

    /**
     * Build a recording platform adapter that may fail at one named lifecycle gate.
     *
     * @param   ArrayObject<int, string>  $calls    Mutable ordered call evidence.
     * @param   ?string                   $failure  Gate that must throw, or null for a successful run.
     *
     * @return  LifecycleConformanceAdapter  Complete deterministic test adapter.
     *
     * @since   0.1.0
     */
    private function lifecycleAdapter(ArrayObject $calls, ?string $failure = null): LifecycleConformanceAdapter
    {
        return new class ($calls, $failure) implements LifecycleConformanceAdapter {
            /**
             * Retain mutable call evidence and the optional forced failure.
             *
             * @param   ArrayObject<int, string>  $calls    Mutable ordered call evidence.
             * @param   ?string                   $failure  Gate that must throw.
             *
             * @since   0.1.0
             */
            public function __construct(
                private ArrayObject $calls,
                private ?string $failure,
            ) {
            }

            /**
             * Record the safety and signing gate.
             *
             * @param   string  $basePackage     Base package path.
             * @param   string  $upgradePackage  Upgrade package path.
             *
             * @return  void
             *
             * @since   0.1.0
             */
            public function assertPackageSafetyAndSigning(string $basePackage, string $upgradePackage): void
            {
                $this->packages($basePackage, $upgradePackage);
                $this->pass('package_safety_and_signing');
            }

            /**
             * Record the schema-plan gate.
             *
             * @param   string  $basePackage     Base package path.
             * @param   string  $upgradePackage  Upgrade package path.
             *
             * @return  void
             *
             * @since   0.1.0
             */
            public function assertSchemaPlan(string $basePackage, string $upgradePackage): void
            {
                $this->packages($basePackage, $upgradePackage);
                $this->pass('schema_plan');
            }

            /**
             * Record the install gate.
             *
             * @param   string  $basePackage  Base package path.
             *
             * @return  void
             *
             * @since   0.1.0
             */
            public function install(string $basePackage): void
            {
                if (!is_file($basePackage)) {
                    throw new RuntimeException('The base package is unavailable.');
                }
                $this->pass('install');
            }

            /**
             * Record the definitions gate.
             *
             * @return  void
             *
             * @since   0.1.0
             */
            public function assertDefinitions(): void
            {
                $this->pass('definitions');
            }

            /**
             * Record the authorization gate.
             *
             * @return  void
             *
             * @since   0.1.0
             */
            public function assertAuthorizationAndFieldPolicies(): void
            {
                $this->pass('authorization_and_field_policies');
            }

            /**
             * Record the routes gate.
             *
             * @return  void
             *
             * @since   0.1.0
             */
            public function assertRoutes(): void
            {
                $this->pass('routes');
            }

            /**
             * Record the REST and OpenAPI gate.
             *
             * @return  void
             *
             * @since   0.1.0
             */
            public function assertRestAndOpenApi(): void
            {
                $this->pass('rest_and_openapi');
            }

            /**
             * Record the CLI and MCP gate.
             *
             * @return  void
             *
             * @since   0.1.0
             */
            public function assertCliAndMcp(): void
            {
                $this->pass('cli_and_mcp');
            }

            /**
             * Record the jobs, events, and reports gate.
             *
             * @return  void
             *
             * @since   0.1.0
             */
            public function assertJobsEventsAndReports(): void
            {
                $this->pass('jobs_events_and_reports');
            }

            /**
             * Record the portal and administrator gate.
             *
             * @return  void
             *
             * @since   0.1.0
             */
            public function assertPortalAndAdministrator(): void
            {
                $this->pass('portal_and_administrator');
            }

            /**
             * Record the backup and restore gate.
             *
             * @return  void
             *
             * @since   0.1.0
             */
            public function assertBackupAndRestore(): void
            {
                $this->pass('backup_and_restore');
            }

            /**
             * Record the upgrade gate.
             *
             * @param   string  $upgradePackage  Upgrade package path.
             *
             * @return  void
             *
             * @since   0.1.0
             */
            public function upgrade(string $upgradePackage): void
            {
                if (!is_file($upgradePackage)) {
                    throw new RuntimeException('The upgrade package is unavailable.');
                }
                $this->pass('upgrade');
            }

            /**
             * Record the disable gate.
             *
             * @return  void
             *
             * @since   0.1.0
             */
            public function disable(): void
            {
                $this->pass('disable');
            }

            /**
             * Record the reactivate gate.
             *
             * @return  void
             *
             * @since   0.1.0
             */
            public function reactivate(): void
            {
                $this->pass('reactivate');
            }

            /**
             * Record the database-matrix gate.
             *
             * @param   string  $basePackage     Base package path.
             * @param   string  $upgradePackage  Upgrade package path.
             *
             * @return  void
             *
             * @since   0.1.0
             */
            public function assertDatabaseMatrix(string $basePackage, string $upgradePackage): void
            {
                $this->packages($basePackage, $upgradePackage);
                $this->pass('database_matrix');
            }

            /**
             * Record the uninstall gate.
             *
             * @return  void
             *
             * @since   0.1.0
             */
            public function uninstall(): void
            {
                $this->pass('uninstall');
            }

            /**
             * Record recovery; repeated calls must be safe.
             *
             * @return  void
             *
             * @since   0.1.0
             */
            public function recover(): void
            {
                $this->pass('recovery');
            }

            /**
             * Require both lifecycle packages to remain available to the adapter.
             *
             * @param   string  $basePackage     Base package path.
             * @param   string  $upgradePackage  Upgrade package path.
             *
             * @return  void
             *
             * @since   0.1.0
             */
            private function packages(string $basePackage, string $upgradePackage): void
            {
                if (!is_file($basePackage) || !is_file($upgradePackage)) {
                    throw new RuntimeException('A lifecycle package is unavailable.');
                }
            }

            /**
             * Record one gate and optionally throw its configured deterministic failure.
             *
             * @param   string  $gate  Stable lifecycle gate name.
             *
             * @return  void
             *
             * @since   0.1.0
             */
            private function pass(string $gate): void
            {
                $this->calls->append($gate);
                if ($this->failure === $gate) {
                    throw new RuntimeException('forced ' . $gate . ' failure');
                }
            }
        };
    }

    /**
     * Build the production-safe package inspection service used by each test path.
     *
     * @return  PackageInspector  Inspector with shipped installation limits.
     *
     * @since   0.1.0
     */
    private function inspector(): PackageInspector
    {
        return new PackageInspector(new PackageLimits());
    }

    /**
     * Flatten typed findings for test diagnostics.
     *
     * @param   list<PackageFinding>  $findings  Neutral coded findings.
     *
     * @return  string  Finding messages in order.
     *
     * @since   0.2.0
     */
    private function messages(array $findings): string
    {
        return implode('; ', $this->messagesList($findings));
    }

    /**
     * Select messages from typed findings.
     *
     * @param   list<PackageFinding>  $findings  Neutral coded findings.
     *
     * @return  list<string>  Finding messages in order.
     *
     * @since   0.2.0
     */
    private function messagesList(array $findings): array
    {
        return array_map(
            static fn (PackageFinding $finding): string => $finding->message,
            $findings,
        );
    }

    /**
     * Allocate a private working directory for one test.
     *
     * @return  string  Absolute path of the writable directory.
     *
     * @since   0.1.0
     */
    private function workspace(): string
    {
        $work = sys_get_temp_dir() . '/kumwe-sdk-toolchain-' . bin2hex(random_bytes(8));
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
     * @since   0.1.0
     */
    private function removeTree(string $root): void
    {
        if (!str_starts_with($root, sys_get_temp_dir() . '/kumwe-sdk-toolchain-')) {
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
