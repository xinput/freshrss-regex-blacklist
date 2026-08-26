<?php

declare(strict_types=1);

/**
 * Regex Blacklist Extension for FreshRSS
 *
 * Prevents articles from being imported based on regex pattern matching.
 * Rules are named, each with one or more patterns (one per line, matched
 * with OR semantics), which entry field(s) to match against (title /
 * content / both / author), and an optional feed scope (one or more
 * specific feeds, or all feeds). The first matching enabled rule blocks
 * the article from import (return null).
 *
 * @package FreshRSS
 * @subpackage Extensions
 * @license AGPL-3.0-or-later
 */
final class RegexBlacklistExtension extends Minz_Extension {

    private const RULES_KEY = 'rules';
    private const MAX_MATCH_LENGTH = 10000;
    private const MATCH_FIELDS = ['title', 'content', 'both', 'author'];

    /**
     * Initialize the extension
     */
    public function init(): void {
        $this->registerHook(
            Minz_HookType::EntryBeforeInsert,
            [$this, 'filterEntryOnImport']
        );
        // async: false — a deferred (non-async) script runs in document order,
        // guaranteed before DOMContentLoaded. With async (the default), the
        // script can finish loading and execute *after* DOMContentLoaded has
        // already fired on a fast-parsing page, silently skipping the
        // 'DOMContentLoaded' listener registered inside it.
        Minz_View::appendScript($this->getFileUrl('script.js'), false, true, false);
        Minz_View::appendStyle($this->getFileUrl('style.css'));
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
            $rules = $this->getRules();
            $matchedIndex = $this->findMatchingRuleIndex($entry, $rules);

            if ($matchedIndex !== null) {
                $rules[$matchedIndex]['blocked_count'] = (int) ($rules[$matchedIndex]['blocked_count'] ?? 0) + 1;
                $this->saveRules($rules);
                return null;
            }

            return $entry;

        } catch (Exception $e) {
            Minz_Log::error('[RegexBlacklist] Exception during filtering: ' . $e->getMessage());
            return $entry;
        }
    }

    /**
     * Load rules from configuration
     *
     * @return array<int,array<string,mixed>>
     */
    private function getRules(): array {
        $raw = $this->getUserConfigurationString(self::RULES_KEY) ?? '[]';
        $rules = json_decode($raw, true);
        return is_array($rules) ? array_values($rules) : [];
    }

    /**
     * @param array<int,array<string,mixed>> $rules
     */
    private function saveRules(array $rules): void {
        $this->setUserConfigurationValue(self::RULES_KEY, json_encode($rules));
    }

    /**
     * @param array<int,array<string,mixed>> $rules
     */
    private function findMatchingRuleIndex(FreshRSS_Entry $entry, array $rules): ?int {
        foreach ($rules as $index => $rule) {
            if ($this->matchesRule($entry, $rule)) {
                return $index;
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed> $rule
     */
    private function matchesRule(FreshRSS_Entry $entry, array $rule): bool {
        if (!(bool) ($rule['enabled'] ?? true)) {
            return false;
        }

        $patterns = $this->splitPatterns((string) ($rule['pattern'] ?? ''));
        if (empty($patterns)) {
            return false;
        }

        $feedIds = array_map('intval', (array) ($rule['feed_ids'] ?? []));
        if (!empty($feedIds) && !in_array($entry->feedId(), $feedIds, true)) {
            return false;
        }

        $matchField = (string) ($rule['match_field'] ?? 'both');
        if (!in_array($matchField, self::MATCH_FIELDS, true)) {
            $matchField = 'both';
        }

        $haystacks = $this->getHaystacks($entry, $matchField);

        foreach ($patterns as $pattern) {
            $regex = '~' . $pattern . '~i';

            foreach ($haystacks as $haystack) {
                $result = @preg_match($regex, $haystack);
                if ($result === false) {
                    Minz_Log::warning('[RegexBlacklist] Invalid regex in rule "' . ($rule['name'] ?? '') . '": ' . $pattern);
                    continue 2; // skip this one bad pattern, keep checking the rule's other patterns
                }
                if ($result === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Splits a rule's pattern field into individual patterns — one per
     * (non-blank) line, matched with OR semantics.
     *
     * @return string[]
     */
    private function splitPatterns(string $raw): array {
        $lines = array_map('trim', explode("\n", $raw));
        return array_values(array_filter($lines, static function (string $line): bool {
            return $line !== '';
        }));
    }

    /**
     * Parses the comma-separated feed id list submitted by the configure
     * form (see configure.phtml / script.js — a hidden field kept in sync
     * with a <select multiple>, since Minz_Request::paramArray() only
     * supports two levels of nesting and can't carry a per-rule array of
     * feed ids directly).
     *
     * @return int[]
     */
    private function parseFeedIdsCsv(string $csv): array {
        $ids = array_map('intval', array_filter(array_map('trim', explode(',', $csv)), static function (string $v): bool {
            return $v !== '';
        }));
        return array_values(array_unique(array_filter($ids, static function (int $id): bool {
            return $id > 0;
        })));
    }

    /**
     * @return string[]
     */
    private function getHaystacks(FreshRSS_Entry $entry, string $matchField): array {
        switch ($matchField) {
            case 'title':
                return [$entry->title()];
            case 'content':
                return [substr($entry->content(), 0, self::MAX_MATCH_LENGTH)];
            case 'author':
                return [$entry->author()];
            default:
                return [$entry->title(), substr($entry->content(), 0, self::MAX_MATCH_LENGTH)];
        }
    }

    /**
     * Handle configuration form submission
     */
    public function handleConfigureAction(): void {
        if (!Minz_Request::isPost()) {
            return;
        }

        $existingById = [];
        foreach ($this->getRules() as $rule) {
            if (isset($rule['id'])) {
                $existingById[(string) $rule['id']] = $rule;
            }
        }

        $submitted = Minz_Request::paramArray('rules', plaintext: true);
        $rules = [];

        foreach ($submitted as $raw) {
            if (!is_array($raw)) {
                continue;
            }

            $pattern = trim((string) ($raw['pattern'] ?? ''));
            if (empty($this->splitPatterns($pattern))) {
                continue; // skip rows with no non-blank pattern line (e.g. an unfilled "Add Rule" row)
            }

            $id = (string) ($raw['id'] ?? '');
            if ($id === '' || !isset($existingById[$id])) {
                $id = bin2hex(random_bytes(6));
            }

            $matchField = (string) ($raw['match_field'] ?? 'both');
            if (!in_array($matchField, self::MATCH_FIELDS, true)) {
                $matchField = 'both';
            }

            $rules[] = [
                'id' => $id,
                'name' => trim((string) ($raw['name'] ?? '')) ?: 'Unnamed rule',
                'pattern' => $pattern,
                'match_field' => $matchField,
                'feed_ids' => $this->parseFeedIdsCsv((string) ($raw['feed_ids'] ?? '')),
                'enabled' => !empty($raw['enabled']),
                'blocked_count' => (int) ($existingById[$id]['blocked_count'] ?? 0),
            ];
        }

        $this->saveRules($rules);
        Minz_Log::notice('[RegexBlacklist] Configuration updated (' . count($rules) . ' rule(s))');
    }
}
