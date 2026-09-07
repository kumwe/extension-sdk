<?php

declare(strict_types=1);

namespace KumweContract\ManifestThree;

use InvalidArgumentException;
use Kumwe\BusinessSurface\Contract\Presentation\Field\FieldPresentationInput;
use Kumwe\BusinessSurface\Contract\Presentation\Field\FieldPresentationModel;
use Kumwe\BusinessSurface\Contract\Presentation\Field\FieldPresenter;
use Kumwe\BusinessSurface\Contract\Presentation\Field\FieldWidget;

/**
 * Presents the manifest-3 compatibility grade as markup-free output.
 *
 * The presenter exists so the schema-3 field-presentation surface is exercised by a real implementation
 * of the public `FieldPresenter` contract rather than by a declaration alone.
 *
 * @since  2.0.0
 */
final readonly class GradeFieldPresenter implements FieldPresenter
{
    /**
     * @param \Kumwe\CanonicalJson\CanonicalEncoder $canonicalEncoder Host-granted canonical admission port.
     * @since 0.3.0
     */
    public function __construct(private \Kumwe\CanonicalJson\CanonicalEncoder $canonicalEncoder)
    {
    }

    /**
     * Render the disclosed grade as read-only text in every declared context.
     *
     * @param   FieldPresentationInput  $request  Typed field metadata and already disclosed value.
     *
     * @return  FieldPresentationModel  Markup-free model the generated core templates consume.
     *
     * @throws  InvalidArgumentException  When the disclosed value is not a string or null.
     *
     * @since   2.0.0
     */
    public function present(FieldPresentationInput $request): FieldPresentationModel
    {
        $value = $request->value;
        if ($value !== null && !is_string($value)) {
            throw new InvalidArgumentException('A compatibility grade must be a string or null.');
        }

        return new FieldPresentationModel(
            $request->handle,
            $request->label,
            $request->context,
            FieldWidget::Output,
            $value ?? '',
            null,
            false,
            $request->required,
            $this->canonicalEncoder,
            $request->errors,
            [],
        );
    }
}
