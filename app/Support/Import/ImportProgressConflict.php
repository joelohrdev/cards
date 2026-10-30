<?php

namespace App\Support\Import;

use RuntimeException;

/**
 * Thrown when another run of the same import has already committed the rows this run was working on.
 */
class ImportProgressConflict extends RuntimeException
{
    //
}
