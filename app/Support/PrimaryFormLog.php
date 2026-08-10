<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Logger for the Primary Application Form (Sectional Titling main application).
 *
 * Writes to the dedicated "primary_form" channel (storage/logs/primary_form.log)
 * instead of laravel.log. Call sites use it exactly like the Log facade — the
 * controllers alias it on import (`use App\Support\PrimaryFormLog as Log;`), so
 * every existing Log::info()/warning()/error() call lands in the dedicated file.
 *
 * Every entry is stamped with who did it and which request it came from, which is
 * what makes a per-form log worth reading when several users are on the form.
 */
class PrimaryFormLog
{
    public const CHANNEL = 'primary_form';

    /**
     * Forward any Log facade method (info, warning, error, debug, log, ...) to the
     * dedicated channel, merging the actor/request stamp into the context array.
     */
    public static function __callStatic(string $method, array $arguments)
    {
        // Log::log($level, $message, $context) puts the context third; every other
        // level helper puts it second.
        $contextIndex = $method === 'log' ? 2 : 1;

        if (in_array($method, ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'], true)) {
            $context = $arguments[$contextIndex] ?? [];
            $arguments[$contextIndex] = is_array($context)
                ? array_merge(self::stamp(), $context)
                : $context;
        }

        return Log::channel(self::CHANNEL)->{$method}(...$arguments);
    }

    /**
     * The underlying channel logger, for callers that want it directly.
     */
    public static function channel()
    {
        return Log::channel(self::CHANNEL);
    }

    /**
     * Actor / request identity attached to every entry.
     */
    private static function stamp(): array
    {
        $request = request();

        return [
            'user_id' => Auth::id(),
            'ip' => $request ? $request->ip() : null,
            'route' => $request ? $request->path() : null,
        ];
    }
}
