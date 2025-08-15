<?php
declare(strict_types=1);

namespace App\Http\Runtime;

use Swoole\Atomic;

final class Shutdown
{
    private static ?Atomic $flag = null;

    private static function init(): void
    {
        if (!self::$flag) {
            self::$flag = new Atomic(0);
        }
    }

    /** Called by server lifecycle events to begin graceful stop */
    public static function markStopping(): void
    {
        self::init();
        self::$flag->set(1);
    }

    /** Query inside hot paths to avoid starting new work */
    public static function isStopping(): bool
    {
        self::init();
        return self::$flag->get() === 1;
    }
}