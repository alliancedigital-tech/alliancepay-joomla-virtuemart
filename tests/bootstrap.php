<?php
/**
 * Test bootstrap: Composer autoload plus the few Joomla classes that pure-logic tests touch.
 */

namespace {
    require dirname(__DIR__) . '/vendor/autoload.php';
}

namespace Joomla\CMS\Log {

if (!class_exists(Log::class, false)) {
    /** Minimal stand-in for Joomla's logger: remembers the messages so tests can assert on them. */
    class Log
    {
        public const ALL = 30719;
        public const ERROR = 8;
        public const WARNING = 16;
        public const INFO = 64;

        /** @var array<int, array{0: string, 1: int}> */
        public static array $messages = [];

        public static function addLogger(array $options, int $priorities = self::ALL, array $categories = []): void
        {
        }

        public static function add(string $message, int $priority = self::INFO, string $category = ''): void
        {
            self::$messages[] = [$message, $priority];
        }
    }
}
}

namespace Joomla\CMS\Language {

if (!class_exists(Text::class, false)) {
    /** Minimal stand-in for Joomla's Text: returns the key, followed by the arguments when there are any. */
    class Text
    {
        public static function _(string $key): string
        {
            return $key;
        }

        public static function sprintf(string $key, ...$args): string
        {
            return $args === [] ? $key : $key . '[' . implode('|', $args) . ']';
        }
    }
}
}
