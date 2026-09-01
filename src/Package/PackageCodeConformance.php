<?php

declare(strict_types=1);

namespace Kumwe\Extension\Package;

use Kumwe\Extension\Manifest\ExtensionManifest;
use ParseError;

/**
 * The bounded, code-free static checks a package's contents are judged by, wherever they are judged.
 *
 * Author tooling and host inspection share this implementation. Keeping checks here makes their facts
 * identical without prescribing whether a consuming host blocks, warns or ignores any finding.
 *
 * Nothing here loads, includes or executes packaged code. PHP files are tokenized with `TOKEN_PARSE`,
 * which parses without binding a single symbol, and every other check is a string or path comparison.
 *
 * @since  0.1.0
 */
final readonly class PackageCodeConformance
{
    /**
     * Parse one PHP source file and optionally inspect its authoring convention.
     *
     * @param   string  $path      Package path quoted in each violation.
     * @param   string  $contents  PHP source bytes.
     * @param   PackageEvidenceScope  $scope  Package-only or complete authoring evidence.
     *
     * @return  list<PackageFinding>  Syntax and, when requested, strict-types findings.
     *
     * @since   0.1.0
     */
    public function phpFindings(string $path, string $contents, PackageEvidenceScope $scope): array
    {
        $violations = [];
        $tokens = null;
        try {
            $tokens = token_get_all($contents, TOKEN_PARSE);
        } catch (ParseError $failure) {
            $violations[] = new PackageFinding(
                'code.php.syntax',
                sprintf('PHP syntax failure in %s: %s', $path, $failure->getMessage()),
                $path,
            );
        }
        if ($scope->includesAuthoring() && (!is_array($tokens) || !$this->declaresStrictTypes($tokens))) {
            $violations[] = new PackageFinding(
                'code.php.strict_types',
                sprintf('PHP file %s must declare strict_types=1.', $path),
                $path,
            );
        }

        return $violations;
    }

    /**
     * Detect unresolved scaffold and unfinished-work markers in packaged text.
     *
     * @param   string  $path      Package path quoted in the violation.
     * @param   string  $contents  Text contents.
     *
     * @return  list<PackageFinding>  Text-encoding or unresolved-marker findings.
     *
     * @since   0.1.0
     */
    public function markerViolations(string $path, string $contents): array
    {
        if (!mb_check_encoding($contents, 'UTF-8')) {
            return [new PackageFinding(
                'source.text.encoding',
                sprintf('Text file %s is not valid UTF-8.', $path),
                $path,
            )];
        }
        if (
            preg_match('/@@[A-Z0-9_]+@@|\{\{[A-Z0-9_]+\}\}/D', $contents) === 1
            || preg_match('/\b(?:TODO|FIXME)\b/', $contents) === 1
        ) {
            return [new PackageFinding(
                'source.marker.unresolved',
                sprintf('Unresolved marker remains in %s.', $path),
                $path,
            )];
        }

        return [];
    }

    /**
     * Check the manifest references that can be resolved without autoloading extension classes.
     *
     * The provider class, every declared migration, every declared asset and every contributed template
     * must resolve to a path the package actually carries. A manifest that names a class the package
     * does not ship is not a style problem: it is a package that will fail when its provider is resolved.
     *
     * @param   ExtensionManifest  $manifest  Strict parsed package manifest.
     * @param   list<string>       $paths     Every path the package carries.
     *
     * @return  list<PackageFinding>  Sorted findings; empty when every declared reference resolves.
     *
     * @since   0.1.0
     */
    public function referenceViolations(ExtensionManifest $manifest, array $paths): array
    {
        $present = array_fill_keys($paths, true);
        $violations = [];
        $autoload = $manifest->autoload();
        $classes = [$manifest->serviceProvider(), ...$manifest->migrations()];
        foreach ($classes as $class) {
            $resolved = false;
            foreach ($autoload as $prefix => $directory) {
                if (!str_starts_with($class, $prefix)) {
                    continue;
                }
                $candidate = rtrim($directory, '/') . '/'
                    . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (isset($present[$candidate])) {
                    $resolved = true;
                    break;
                }
            }
            if (!$resolved) {
                $violations[] = new PackageFinding(
                    'manifest.reference.class_missing',
                    sprintf('Manifest reference class %s does not resolve to a packaged PHP file.', $class),
                );
            }
        }
        foreach ($manifest->assets() as $asset) {
            if (!isset($present[$asset])) {
                $violations[] = new PackageFinding(
                    'manifest.reference.asset_missing',
                    sprintf('Manifest reference asset %s is missing.', $asset),
                    $asset,
                );
            }
        }
        $contributions = $manifest->contributions();
        foreach ($contributions->administratorViews() as $view) {
            $path = 'templates/views/administrator/' . $view->template;
            if (!isset($present[$path])) {
                $violations[] = new PackageFinding(
                    'manifest.reference.template_missing',
                    sprintf('Manifest reference template %s is missing.', $path),
                    $path,
                );
            }
        }
        foreach ($contributions->portalTemplates() as $template) {
            $path = 'templates/views/portal/' . $template->template;
            if (!isset($present[$path])) {
                $violations[] = new PackageFinding(
                    'manifest.reference.template_missing',
                    sprintf('Manifest reference template %s is missing.', $path),
                    $path,
                );
            }
        }

        usort($violations, static fn (PackageFinding $left, PackageFinding $right): int => [
            $left->code,
            $left->path ?? '',
            $left->message,
        ] <=> [
            $right->code,
            $right->path ?? '',
            $right->message,
        ]);

        return $violations;
    }

    /**
     * Decide whether an entry is a text format subject to marker scanning.
     *
     * @param   string  $path  Package path.
     *
     * @return  bool  True for supported text extensions and conventional text file names.
     *
     * @since   0.1.0
     */
    public function isTextPath(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['php', 'json', 'md', 'twig', 'yaml', 'yml', 'xml', 'css', 'js'], true)
            || in_array(basename($path), ['README', 'LICENSE'], true);
    }

    /**
     * Report whether a path names packaged PHP source.
     *
     * @param   string  $path  Package path.
     *
     * @return  bool  True when the entry is a `.php` file in any letter case.
     *
     * @since   0.1.0
     */
    public function isPhpPath(string $path): bool
    {
        return str_ends_with(strtolower($path), '.php');
    }

    /**
     * Require strict scalar semantics as the first executable source declaration.
     *
     * Token inspection prevents a comment or string containing `declare(strict_types=1)` from satisfying
     * the gate. PHP's parser separately rejects a real strict-types declaration that appears too late.
     *
     * @param   list<array{int, string, int}|string>  $tokens  Parsed PHP source tokens.
     *
     * @return  bool  True only for an actual leading `declare(strict_types=1);` statement.
     *
     * @since   0.1.0
     */
    private function declaresStrictTypes(array $tokens): bool
    {
        $significant = [];
        foreach ($tokens as $token) {
            if (
                is_array($token)
                && in_array($token[0], [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
            ) {
                continue;
            }
            $significant[] = $token;
        }
        if (count($significant) < 7) {
            return false;
        }

        return is_array($significant[0])
            && $significant[0][0] === T_DECLARE
            && $significant[1] === '('
            && is_array($significant[2])
            && $significant[2][0] === T_STRING
            && strtolower($significant[2][1]) === 'strict_types'
            && $significant[3] === '='
            && is_array($significant[4])
            && $significant[4][0] === T_LNUMBER
            && $significant[4][1] === '1'
            && $significant[5] === ')'
            && $significant[6] === ';';
    }
}
