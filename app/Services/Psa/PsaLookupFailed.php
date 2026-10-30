<?php

namespace App\Services\Psa;

use RuntimeException;

/**
 * PSA's API could not answer: bad credentials, an outage, or a network failure.
 */
class PsaLookupFailed extends RuntimeException {}
