<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Base for per-feature loggers that write to their own file instead of laravel.log.
 *
 * Subclasses only declare a CHANNEL matching a channel in config/logging.php. Call
 * sites then use the subclass exactly like the Log facade — controllers alias it on
 * import (`use App\Support\XyzLog as Log;`), so existing Log::info()/warning()/error()
 * calls land in the dedicated file with no other edits.
 *
 * Every entry is stamped with who did it and which request it came from, which is
 * what makes a per-feature log readable when several users are working at once.
 *
 * @method static void emergency(string $message, array $context = [])
 * @method static void alert(string $message, array $context = [])
 * @method static void critical(string $message, array $context = [])
 * @method static void error(string $message, array $context = [])
 * @method static void warning(string $message, array $context = [])
 * @method static void notice(string $message, array $context = [])
 * @method static void info(string $message, array $context = [])
 * @method static void debug(string $message, array $context = [])
 * @method static void log(string $level, string $message, array $context = [])
 */
abstract class ChannelLog
{
    /** Channel name as defined in config/logging.php. */
    public const CHANNEL = 'stack';

    private const LEVELS = [
        'emergency', 'alert', 'critical', 'error',
        'warning', 'notice', 'info', 'debug', 'log',
    ];

    /**
     * Forward any Log facade method (info, warning, error, debug, log, ...) to the
     * dedicated channel, merging the actor/request stamp into the context array.
     */
    public static function __callStatic(string $method, array $arguments)
    {
        // Log::log($level, $message, $context) puts the context third; every other
        // level helper puts it second.
        $contextIndex = $method === 'log' ? 2 : 1;

        if (\in_array($method, self::LEVELS, true)) {
            $context = $arguments[$contextIndex] ?? [];
            $arguments[$contextIndex] = \is_array($context)
                ? \array_merge(self::stamp(), $context)
                : $context;
        }

        return Log::channel(static::CHANNEL)->{$method}(...$arguments);
    }

    /**
     * The underlying channel logger, for callers that want it directly.
     */
    public static function channel()
    {
        return Log::channel(static::CHANNEL);
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
