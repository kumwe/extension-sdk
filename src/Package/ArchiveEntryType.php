<?php

declare(strict_types=1);

namespace Kumwe\Extension\Package;

/**
 * What a single entry in an extension archive is, as classified from the archive directory.
 *
 * `ZipArchiveReader` decides the case from the entry name and the Unix mode in the ZIP external
 * attributes, before anything is expanded. `PackageSafetyInspector` then reports two facts it
 * could not make from a path and a size alone: a link is reported before expansion, and only a regular
 * file at the archive root can satisfy the required `kumwe.json` manifest.
 *
 * @since  0.1.0
 */
enum ArchiveEntryType: string
{
    /**
     * A regular file carrying payload bytes, and the only kind that can serve as the package manifest.
     *
     * @since  0.1.0
     */
    case File = 'file';
    /**
     * A path entry with no payload of its own, so both of its recorded sizes are zero.
     *
     * @since  0.1.0
     */
    case Directory = 'directory';
    /**
     * An entry whose mode marks it a symbolic link and must be surfaced as unsafe metadata.
     *
     * A link inside a package is a way to redirect a later write outside the deployment, or to smuggle a
     * reference to a host file into the extension tree, so no packaging need justifies allowing one.
     *
     * @since  0.1.0
     */
    case SymbolicLink = 'symbolic_link';

    /**
     * A filesystem object that is neither a regular file, directory nor symbolic link.
     *
     * FIFOs, sockets and device nodes have no legitimate portable package meaning and are reported before
     * extraction rather than being treated as ordinary files.
     *
     * @since  0.2.0
     */
    case Special = 'special';
}
