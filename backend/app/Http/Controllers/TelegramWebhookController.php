<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives bot updates (commands) from Telegram.
 *
 * Security: Telegram sends the secret token configured via setWebhook in the
 * X-Telegram-Bot-Api-Secret-Token header; requests without a matching token
 * are dropped. The webhook is fully disabled until TELEGRAM_WEBHOOK_SECRET
 * is configured, so it can never process spoofed updates by accident.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramCommandHandler $handler): JsonResponse
    {
        $secret = (string) config('services.telegram.webhook_secret', '');
        $given = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        if ($secret === '' || ! hash_equals($secret, $given)) {
            return response()->json(['ok' => false], 403);
        }

        $handler->handle($request->all());

        return response()->json(['ok' => true]);
    }
}
