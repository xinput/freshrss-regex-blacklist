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
class RegexBlacklistExtension extends Minz_Extension {

    const GLOBAL_CONFIG_KEY = 'global_patterns';
    const STATS_CONFIG_KEY = 'stats';
    const MAX_MATCH_LENGTH = 10000;

    /**
     * Initialize the extension
     */
    public function init(): void {
        $this->registerHook(
            Minz_HookType::EntryBeforeInsert,
            'filterEntryOnImport'
        );
    }

    /**
     * Hook handler for EntryBeforeInsert
     * 
     * @param FreshRSS_Entry $entry The entry to evaluate
     * @return FreshRSS_Entry|null The entry if allowed, null to block
     */
    public function filterEntryOnImport(?FreshRSS_Entry $entry): ?FreshRSS_Entry {
        if ($entry === null) {
            return null;
        }

        try {
            $globalPatterns = $this->getPatterns(self::GLOBAL_CONFIG_KEY);
            if ($this->matchesPatterns($entry, $globalPatterns)) {
                $this->logFilter($entry, 'global');
                $this->updateStats('global');
                return null;
            }

            return $entry;

        } catch (Exception $e) {
            _log('error', '[RegexBlacklist] Exception during filtering: ' . $e->getMessage());
            return $entry;
        }
    }

    /**
     * Get patterns from configuration
     */
    private function getPatterns(string $configKey): array {
        $patternsStr = $this->getConfig($configKey, '');
        if (empty($patternsStr)) {
            return [];
        }

        $patterns = array_map('trim', explode("\n", $patternsStr));
        return array_filter($patterns, static function (string $p): bool {
            return !empty($p);
        });
    }

    /**
     * Check if entry matches any pattern
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
            _log('warn', '[RegexBlacklist] Invalid regex pattern: ' . $pattern);
            return false;
        }

        return $titleResult === 1 || $contentResult === 1;
    }

    /**
     * Log filtered article (debug only)
     */
    private function logFilter(FreshRSS_Entry $entry, string $source): void {
        if (defined('FRESHRSS_ENV') && FRESHRSS_ENV === 'development') {
            _log('debug', '[RegexBlacklist] Blocked: "' . $entry->getTitle() . '" from ' . $source);
        }
    }

    /**
     * Update statistics
     */
    private function updateStats(string $source): void {
        $stats = $this->getConfig(self::STATS_CONFIG_KEY, '{}');
        $statsArray = json_decode($stats, true) ?? [];
        
        if (!isset($statsArray[$source])) {
            $statsArray[$source] = 0;
        }
        
        $statsArray[$source]++;
        $this->setConfig(self::STATS_CONFIG_KEY, json_encode($statsArray));
    }

    /**
     * Handle configuration form submission
     */
    public function handleConfigureAction(): void {
        if ($this->isPost()) {
            $globalPatterns = $this->getPost('global_patterns', '');
            if ($globalPatterns !== $this->getConfig(self::GLOBAL_CONFIG_KEY, '')) {
                $this->setConfig(self::STATS_CONFIG_KEY, '{}');
            }
            $this->setConfig(self::GLOBAL_CONFIG_KEY, $globalPatterns);
            _log('info', '[RegexBlacklist] Configuration updated');
        }
    }
}
