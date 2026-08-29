<?php

declare(strict_types=1);

namespace KumweContract\ManifestSix;

use Kumwe\Producer\Render\BlockRenderer;
use Kumwe\Producer\Render\Properties;
use Kumwe\Producer\Render\RenderState;
use Kumwe\Producer\Render\SafeMarkup;

/**
 * Safe executable preview half of the fixture's signed grid host binding.
 *
 * @since  2.0.0
 */
final readonly class GridPreviewRenderer implements BlockRenderer
{
    /**
     * Present the bounded grid column count as semantic inner markup through Producer's escaping discipline.
     *
     * @param   \stdClass    $node   Canonical Blueprint node validated by Producer.
     * @param   string       $scope  Host-issued CSS scope for this node.
     * @param   RenderState  $state  Per-render Producer services; unused by this layout-only fixture.
     *
     * @return  string  Escaped semantic inner HTML for Producer's owner-scoped wrapper.
     *
     * @since   2.0.0
     */
    public function render(
        \stdClass $node,
        string $scope,
        RenderState $state,
    ): string {
        $columns = Properties::integerProperty(Properties::property($node, 'columns'), 1, 12, 1);
        $text = sprintf('Contributed grid: %d columns', $columns);

        return '<p class="studio-preview-extension-grid" data-scope="'
            . SafeMarkup::escapeAttribute($scope) . '">' . SafeMarkup::escapeHtml($text) . '</p>';
    }
}
