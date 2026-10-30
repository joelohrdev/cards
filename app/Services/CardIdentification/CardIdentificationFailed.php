<?php

namespace App\Services\CardIdentification;

use RuntimeException;

/**
 * The vision model could not be reached, declined, or answered with something we can't use.
 */
class CardIdentificationFailed extends RuntimeException {}
