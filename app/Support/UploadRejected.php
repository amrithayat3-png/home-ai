<?php

namespace App\Support;

use RuntimeException;

/**
 * A file that was not accepted, with a message an officer can read.
 * The page shows it as an error, the many-files upload shows it on that file's row.
 */
class UploadRejected extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'document')
    {
        parent::__construct($message);
    }
}
