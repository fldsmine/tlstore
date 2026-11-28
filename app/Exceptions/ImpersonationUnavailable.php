<?php

namespace App\Exceptions;

use RuntimeException;

/** Raised when an impersonation hand-off cannot be issued or redeemed. */
final class ImpersonationUnavailable extends RuntimeException {}
