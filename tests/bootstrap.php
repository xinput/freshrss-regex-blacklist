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

// Mock Minz_Extension
if (!class_exists('Minz_Extension')) {
    class Minz_Extension {
        protected $config = [];
        protected $posted = [];

        protected function registerHook($hookType, $method) {}

        protected function getConfig(string $key, $default = null) {
            return $this->config[$key] ?? $default;
        }

        protected function setConfig(string $key, $value): void {
            $this->config[$key] = $value;
        }

        protected function getPost(string $key, $default = null) {
            return $this->posted[$key] ?? $default;
        }

        protected function isPost(): bool {
            return !empty($this->posted);
        }

        public function getName(): string {
            return 'Regex Blacklist';
        }

        public function getDescription(): string {
            return 'Prevent articles from being imported based on regex patterns';
        }
    }
}

// Mock Minz_HookType
if (!class_exists('Minz_HookType')) {
    class Minz_HookType {
        public const EntryBeforeInsert = 'EntryBeforeInsert';
    }
}

// Mock logging function
if (!function_exists('_log')) {
    function _log(string $level, string $message): void {
        // Mock implementation
    }
}

// Load the extension
require_once __DIR__ . '/../extension.php';
