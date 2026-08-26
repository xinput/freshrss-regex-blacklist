<?php

declare(strict_types=1);

// Define constants
if (!defined('FRESHRSS_ENV')) {
    define('FRESHRSS_ENV', 'test');
}

// Mock FreshRSS_Entry if not available
if (!class_exists('FreshRSS_Entry')) {
    class FreshRSS_Entry {
        private $id;
        private $feedId = 1;
        private $title = '';
        private $content = '';

        public function getTitle(): ?string {
            return $this->title ?: null;
        }

        public function _title(string $title = null): ?string {
            if ($title !== null) {
                $this->title = $title;
            }
            return $this->title;
        }

        public function getContent(): ?string {
            return $this->content ?: null;
        }

        public function _content(string $content = null): ?string {
            if ($content !== null) {
                $this->content = $content;
            }
            return $this->content;
        }

        public function getFeedId(): ?int {
            return $this->feedId;
        }

        public function _feedId(int $feedId = null): ?int {
            if ($feedId !== null) {
                $this->feedId = $feedId;
            }
            return $this->feedId;
        }

        public function _id(string $id = null): ?string {
            if ($id !== null) {
                $this->id = $id;
            }
            return $this->id;
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

        public static function paramString(string $key, bool $plaintext = false): string {
            return (string) (self::$params[$key] ?? '');
        }
    }
}

// Load the extension
require_once __DIR__ . '/../extension.php';
