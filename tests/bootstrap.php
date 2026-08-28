<?php

declare(strict_types=1);

// Define constants
if (!defined('FRESHRSS_ENV')) {
    define('FRESHRSS_ENV', 'test');
}

// Mock FreshRSS_Entry — method names/shape match the real
// app/Models/Entry.php (title(), content(), author(), feedId()), not the
// getX() names this extension used to call, which don't exist on the
// real class.
if (!class_exists('FreshRSS_Entry')) {
    class FreshRSS_Entry {
        private string $title = '';
        private string $content = '';
        private string $author = '';
        private int $feedId = 1;
        private string $link = '';

        public function title(): string {
            return $this->title;
        }

        public function _title(string $title): void {
            $this->title = $title;
        }

        public function content(): string {
            return $this->content;
        }

        public function _content(string $content): void {
            $this->content = $content;
        }

        public function author(): string {
            return $this->author;
        }

        public function _author(string $author): void {
            $this->author = $author;
        }

        public function feedId(): int {
            return $this->feedId;
        }

        public function _feedId(int $feedId): void {
            $this->feedId = $feedId;
        }

        public function link(): string {
            return $this->link;
        }

        public function _link(string $link): void {
            $this->link = $link;
        }
    }
}

// Mock Minz_Extension — mirrors the real lib/Minz/Extension.php configuration API
// (getUserConfigurationString/Int/Bool + protected setUserConfigurationValue).
// The real setter is `final protected`, so it can't be called from outside the
// class hierarchy; testSetUserConfiguration() is a test-only public helper that
// seeds the same backing array directly.
if (!class_exists('Minz_Extension')) {
    class Minz_Extension {
        /** @var array<string,mixed> */
        protected array $user_configuration = [];

        protected function registerHook($hookType, $method): void {}

        final public function getUserConfigurationString(string $key): ?string {
            $value = $this->user_configuration[$key] ?? null;
            return is_string($value) ? $value : null;
        }

        final public function getUserConfigurationInt(string $key): ?int {
            $value = $this->user_configuration[$key] ?? null;
            return is_numeric($value) ? (int) $value : null;
        }

        final public function getUserConfigurationBool(string $key): ?bool {
            $value = $this->user_configuration[$key] ?? null;
            return is_bool($value) ? $value : null;
        }

        protected function setUserConfigurationValue(string $key, mixed $value = null): void {
            if ($value === null) {
                unset($this->user_configuration[$key]);
            } else {
                $this->user_configuration[$key] = $value;
            }
        }

        public function getName(): string {
            return 'Regex Blacklist';
        }

        public function getDescription(): string {
            return 'Prevent articles from being imported based on regex patterns';
        }

        public function getFileUrl(string $filename, string $type = '', bool $isStatic = true): string {
            return '/ext.php?f=RegexBlacklist/static/' . $filename;
        }

        /** Test-only: seeds configuration without going through the protected production setter. */
        public function testSetUserConfiguration(string $key, mixed $value): void {
            $this->user_configuration[$key] = $value;
        }
    }
}

// Mock Minz_HookType
if (!class_exists('Minz_HookType')) {
    class Minz_HookType {
        public const EntryBeforeInsert = 'EntryBeforeInsert';
    }
}

// Mock Minz_Log (real logging API — the extension used to call a nonexistent
// global _log() function, which is not part of FreshRSS)
if (!class_exists('Minz_Log')) {
    class Minz_Log {
        public static function debug(string $msg): void {}
        public static function notice(string $msg): void {}
        public static function warning(string $msg): void {}
        public static function error(string $msg): void {}
    }
}

// Mock Minz_View (extension init() registers static assets)
if (!class_exists('Minz_View')) {
    class Minz_View {
        public static function appendScript(string $url, bool $cond = false, bool $defer = true, bool $async = true, string $id = ''): void {}
        public static function appendStyle(string $url, string $media = 'all', bool $cond = false): void {}
    }
}

// Mock Minz_Request — only the subset handleConfigureAction() needs
if (!class_exists('Minz_Request')) {
    class Minz_Request {
        /** @var array<string,mixed> */
        private static array $params = [];
        private static bool $isPostFlag = false;

        /** @param array<string,mixed> $params */
        public static function _setTestParams(array $params, bool $isPost = true): void {
            self::$params = $params;
            self::$isPostFlag = $isPost;
        }

        public static function _resetTest(): void {
            self::$params = [];
            self::$isPostFlag = false;
        }

        public static function isPost(): bool {
            return self::$isPostFlag;
        }

        /** @return array<string|int,mixed> */
        public static function paramArray(string $key, bool $plaintext = false): array {
            $value = self::$params[$key] ?? [];
            return is_array($value) ? $value : [];
        }

        public static function paramString(string $key, string $default = ''): string {
            $value = self::$params[$key] ?? $default;
            return is_string($value) ? $value : $default;
        }
    }
}

// Load the extension
require_once __DIR__ . '/../extension.php';
