<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by the queue jobs so Laravel retries the delivery. The message is
 * already sanitised by TelegramClient (no bot token) because it can end up in
 * the failed-jobs table and in the admin console.
 */
final class TelegramDeliveryException extends RuntimeException {}
