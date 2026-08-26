<?php

declare(strict_types=1);

/**
 * Regex Blacklist Extension for FreshRSS
 *
 * Prevents articles from being imported based on regex pattern matching.
 * Patterns are matched against the article title and content.
 * Articles matching any pattern are blocked from import (return null).
 *
 * @package FreshRSS
 * @subpackage Extensions
 * @license AGPL-3.0-or-later
 */
final class RegexBlacklistExtension extends Minz_Extension {

    private const CONFIG_KEY = 'global_patterns';
    private const STATS_KEY = 'stats';
    private const MAX_MATCH_LENGTH = 10000;

    /**
     * Initialize the extension
     */
    public function init(): void {
        $this->registerHook(
            Minz_HookType::EntryBeforeInsert,
            [$this, 'filterEntryOnImport']
        );
    }

    /**
     * Hook handler for EntryBeforeInsert
     *
     * @param FreshRSS_Entry|null $entry The entry to evaluate
     * @return FreshRSS_Entry|null The entry if allowed, null to block
     */
    public function filterEntryOnImport(?FreshRSS_Entry $entry): ?FreshRSS_Entry {
        if ($entry === null) {
            return null;
        }

        try {
            $patterns = $this->getPatterns();
            if ($this->matchesPatterns($entry, $patterns)) {
                $this->updateStats();
                return null;
            }

            return $entry;

        } catch (Exception $e) {
            Minz_Log::error('[RegexBlacklist] Exception during filtering: ' . $e->getMessage());
            return $entry;
        }
    }

    /**
     * Get patterns from configuration
     *
     * @return string[]
     */
    private function getPatterns(): array {
        $patternsStr = $this->getUserConfigurationString(self::CONFIG_KEY) ?? '';
        if ($patternsStr === '') {
            return [];
        }

        $patterns = array_map('trim', explode("\n", $patternsStr));
        return array_filter($patterns, static function (string $p): bool {
            return $p !== '';
        });
    }

    /**
     * Check if entry matches any pattern
     *
     * @param string[] $patterns
     */
    private function matchesPatterns(FreshRSS_Entry $entry, array $patterns): bool {
        if (empty($patterns)) {
            return false;
        }

        $title = $entry->getTitle() ?? '';
        $content = substr($entry->getContent() ?? '', 0, self::MAX_MATCH_LENGTH);

        foreach ($patterns as $pattern) {
            if ($this->matchesPattern($title, $content, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if pattern matches title or content
     */
    private function matchesPattern(string $title, string $content, string $pattern): bool {
        $regex = '~' . $pattern . '~i';

        $titleResult = @preg_match($regex, $title);
        $contentResult = @preg_match($regex, $content);

        if ($titleResult === false || $contentResult === false) {
            Minz_Log::warning('[RegexBlacklist] Invalid regex pattern: ' . $pattern);
            return false;
        }

        return $titleResult === 1 || $contentResult === 1;
    }

    /**
     * Update statistics
     */
    private function updateStats(): void {
        $stats = $this->getUserConfigurationString(self::STATS_KEY) ?? '{}';
        $statsArray = json_decode($stats, true);
        if (!is_array($statsArray)) {
            $statsArray = [];
        }

        $statsArray['global'] = (int) ($statsArray['global'] ?? 0) + 1;
        $this->setUserConfigurationValue(self::STATS_KEY, json_encode($statsArray));
    }

    /**
     * Handle configuration form submission
     */
    public function handleConfigureAction(): void {
        if (Minz_Request::isPost()) {
            $patterns = trim(Minz_Request::paramString(self::CONFIG_KEY, plaintext: true));
            if ($patterns !== ($this->getUserConfigurationString(self::CONFIG_KEY) ?? '')) {
                $this->setUserConfigurationValue(self::STATS_KEY, '{}');
            }
            $this->setUserConfigurationValue(self::CONFIG_KEY, $patterns);
            Minz_Log::notice('[RegexBlacklist] Configuration updated');
        }
    }
}
