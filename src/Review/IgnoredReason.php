<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Review;

/**
 * Why the catalog passed an entry over. The review page turns these tokens into
 * words; nothing here is user-facing text.
 */
enum IgnoredReason: string
{
    case UnparsableName = 'unparsable_name';
    case MissingSidecar = 'missing_sidecar';
    case OrphanSidecar = 'orphan_sidecar';
    case UnrecognizedDirectory = 'unrecognized_directory';
}
