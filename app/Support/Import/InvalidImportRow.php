<?php

namespace App\Support\Import;

use RuntimeException;

/**
 * A spreadsheet row that can't be imported. The message is shown to the user beside the row number.
 */
class InvalidImportRow extends RuntimeException
{
    //
}
