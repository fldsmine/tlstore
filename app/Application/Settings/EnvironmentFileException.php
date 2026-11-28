<?php

namespace App\Application\Settings;

use RuntimeException;

/** Raised when the deployment's `.env` file cannot be read or written. */
final class EnvironmentFileException extends RuntimeException {}
