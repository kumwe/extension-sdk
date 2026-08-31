<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessSurface\Presentation\Field;

/** Safe field presentation strategy with no host-container access. @since 0.2.0 */
interface FieldPresenter
{
    /** @since 0.2.0 */
    public function present(FieldPresentationInput $input): FieldPresentationModel;
}
