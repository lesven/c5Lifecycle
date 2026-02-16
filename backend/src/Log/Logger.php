<?php
declare(strict_types=1);

namespace C5\Log;

class Logger
{
    private static ?string $logDir = null;

    private static function getLogDir(): string
    {
        if (self::$logDir === null) {
            self::$logDir = __DIR__ . '/../../logs';
            if (!is_dir(self::$logDir)) {
                mkdir(self::$logDir, 0755, true);
            }
        }
        return self::$logDir;
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $logFile = self::getLogDir() . '/' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";

        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
