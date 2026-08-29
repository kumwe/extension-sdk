<?php

declare(strict_types=1);

namespace Kumwe\Extension\Package;

/**
 * What inspection established about a package's bill of materials or provenance.
 *
 * The SDK reports all four observable states and leaves their policy meaning to the consuming host.
 * A document is verified, absent, present but invalid, or not inspected because package safety blocked
 * content expansion. Nothing in this enum admits or refuses a package; it is evidence for caller policy.
 *
 * @since  0.1.0
 */
enum PackageAttestationState: string
{
    /**
     * Package safety findings prevented attestation bytes from being expanded.
     *
     * @since  0.2.0
     */
    case NotInspected = 'not_inspected';

    /**
     * The document was present and agreed with the package bytes it describes.
     *
     * @since  0.1.0
     */
    case Verified = 'verified';

    /**
     * The package carried no such document, so nothing was claimed and nothing was checked.
     *
     * @since  0.1.0
     */
    case Absent = 'absent';

    /**
     * A document was present but could not be parsed or reconciled with the package.
     *
     * @since  0.2.0
     */
    case Invalid = 'invalid';
}
