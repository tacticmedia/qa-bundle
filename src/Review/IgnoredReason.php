<?php

declare(strict_types=1);

namespace TacticMedia\QaBundle\Review;

/**
 * The reason the catalog ignored an entry. The review page converts these tokens
 * into text. No value here is user-facing text.
 */
enum IgnoredReason: string
{
    case UnparsableName = 'unparsable_name';
    case MissingSidecar = 'missing_sidecar';
    case OrphanSidecar = 'orphan_sidecar';
    case UnrecognizedDirectory = 'unrecognized_directory';
}
