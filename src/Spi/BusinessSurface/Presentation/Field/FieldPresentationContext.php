<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessSurface\Presentation\Field;

/**
 * Closed contexts in which a generated business field can be presented.
 *
 * @since  0.1.0
 */
enum FieldPresentationContext: string
{
    /** Collection cell. @since 0.1.0 */
    case List = 'list';

    /** Record detail value. @since 0.1.0 */
    case Detail = 'detail';

    /** Create-form editor. @since 0.1.0 */
    case Create = 'create';

    /** Update-form editor. @since 0.1.0 */
    case Update = 'update';

    /** Query filter editor. @since 0.1.0 */
    case Filter = 'filter';

    /** Cursor-bounded relationship selector. @since 0.1.0 */
    case Relation = 'relation';

    /** Revision-history value. @since 0.1.0 */
    case History = 'history';

    /**
     * Report whether this context accepts submitted input.
     *
     * @return  bool  True for create, update, filter and relation contexts.
     *
     * @since   0.1.0
     */
    public function edits(): bool
    {
        return in_array($this, [self::Create, self::Update, self::Filter, self::Relation], true);
    }
}
