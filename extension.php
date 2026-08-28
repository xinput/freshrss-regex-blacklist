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
    private const LOG_KEY = 'log';
    private const SEEN_KEY = 'blocked_seen';
    private const MAX_MATCH_LENGTH = 10000;
    private const MAX_LOG_ENTRIES = 200;
    private const MAX_LOG_TITLE_LENGTH = 300;
    private const MAX_SEEN_ENTRIES = 5000;
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
            $match = $this->findMatchingRule($entry, $rules);

            if ($match !== null) {
                [$index, $matchedField, $matchedPattern] = $match;

                // FreshRSS retries import of a blocked entry on every feed refresh —
                // since it's never inserted, there's no dedup record to recognize it
                // as "already seen" next time. Without this, the same article would
                // inflate blocked_count/log on every poll instead of counting once.
                if (!$this->wasAlreadyBlocked($entry)) {
                    $rules[$index]['blocked_count'] = (int) ($rules[$index]['blocked_count'] ?? 0) + 1;
                    $this->saveRules($rules);
                    $this->recordBlockedEntry($entry, $rules[$index], $matchedField, $matchedPattern);
                }

                return null;
            }

            return $entry;

        } catch (Exception $e) {
            Minz_Log::error('[RegexBlacklist] Exception during filtering: ' . $e->getMessage());
            return $entry;
        }
    }

    /**
     * Exposed for configure.phtml, since the view script runs outside the
     * class body and can't reach a private const via self:: or $this::.
     */
    public function getMaxLogEntries(): int {
        return self::MAX_LOG_ENTRIES;
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
     * @return array{0:int,1:string,2:string}|null Matched rule index, the specific
     *         haystack field that matched ('title'/'content'/'author' — never
     *         'both', even if the rule's match_field is 'both'), and the pattern line that matched.
     */
    private function findMatchingRule(FreshRSS_Entry $entry, array $rules): ?array {
        foreach ($rules as $index => $rule) {
            $match = $this->matchesRule($entry, $rule);
            if ($match !== null) {
                return [$index, $match[0], $match[1]];
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed> $rule
     * @return array{0:string,1:string}|null The matched haystack field and pattern line, or null if no match.
     */
    private function matchesRule(FreshRSS_Entry $entry, array $rule): ?array {
        if (!(bool) ($rule['enabled'] ?? true)) {
            return null;
        }

        $patterns = $this->splitPatterns((string) ($rule['pattern'] ?? ''));
        if (empty($patterns)) {
            return null;
        }

        $feedIds = array_map('intval', (array) ($rule['feed_ids'] ?? []));
        if (!empty($feedIds) && !in_array($entry->feedId(), $feedIds, true)) {
            return null;
        }

        $matchField = (string) ($rule['match_field'] ?? 'both');
        if (!in_array($matchField, self::MATCH_FIELDS, true)) {
            $matchField = 'both';
        }

        $haystacks = $this->getHaystacks($entry, $matchField);

        foreach ($patterns as $pattern) {
            $regex = '~' . $pattern . '~i';

            foreach ($haystacks as $field => $haystack) {
                $result = @preg_match($regex, $haystack);
                if ($result === false) {
                    Minz_Log::warning('[RegexBlacklist] Invalid regex in rule "' . ($rule['name'] ?? '') . '": ' . $pattern);
                    continue 2; // skip this one bad pattern, keep checking the rule's other patterns
                }
                if ($result === 1) {
                    return [$field, $pattern];
                }
            }
        }

        return null;
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
     * @return array<string,string> Keyed by the specific field the text came from
     *         ('title'/'content'/'author'), so a match can be attributed precisely
     *         even when the rule's match_field is 'both'.
     */
    private function getHaystacks(FreshRSS_Entry $entry, string $matchField): array {
        switch ($matchField) {
            case 'title':
                return ['title' => $entry->title()];
            case 'content':
                return ['content' => substr($entry->content(), 0, self::MAX_MATCH_LENGTH)];
            case 'author':
                return ['author' => $entry->author()];
            default:
                return [
                    'title' => $entry->title(),
                    'content' => substr($entry->content(), 0, self::MAX_MATCH_LENGTH),
                ];
        }
    }

    /**
     * A stable per-article key: guid is what FreshRSS itself uses to recognize
     * "the same entry" across repeated feed refreshes, scoped to the feed since
     * guids are only guaranteed unique within a feed.
     */
    private function dedupKey(FreshRSS_Entry $entry): string {
        $guid = method_exists($entry, 'guid') ? $entry->guid() : '';
        if ($guid === '') {
            $guid = $entry->link() . '|' . $entry->title(); // fallback for a feed with no/empty guid
        }
        return $entry->feedId() . '|' . $guid;
    }

    /**
     * @return string[]
     */
    private function getSeen(): array {
        $raw = $this->getUserConfigurationString(self::SEEN_KEY) ?? '[]';
        $seen = json_decode($raw, true);
        return is_array($seen) ? array_values($seen) : [];
    }

    /**
     * @param string[] $seen
     */
    private function saveSeen(array $seen): void {
        $this->setUserConfigurationValue(self::SEEN_KEY, json_encode($seen));
    }

    /**
     * True if this exact article was already counted/logged as blocked before.
     * Records it as seen (oldest evicted past MAX_SEEN_ENTRIES) as a side effect
     * of a "no" answer, so a bounded set can't grow forever on a noisy blacklist.
     */
    private function wasAlreadyBlocked(FreshRSS_Entry $entry): bool {
        $key = $this->dedupKey($entry);
        $seen = $this->getSeen();

        if (in_array($key, $seen, true)) {
            return true;
        }

        $seen[] = $key;
        if (count($seen) > self::MAX_SEEN_ENTRIES) {
            $seen = array_slice($seen, count($seen) - self::MAX_SEEN_ENTRIES);
        }
        $this->saveSeen($seen);

        return false;
    }

    /**
     * Loads the blocked-article log (most recent first).
     *
     * @return array<int,array<string,mixed>>
     */
    private function getLog(): array {
        $raw = $this->getUserConfigurationString(self::LOG_KEY) ?? '[]';
        $log = json_decode($raw, true);
        return is_array($log) ? array_values($log) : [];
    }

    /**
     * @param array<int,array<string,mixed>> $log
     */
    private function saveLog(array $log): void {
        $this->setUserConfigurationValue(self::LOG_KEY, json_encode($log));
    }

    /**
     * Prepends a blocked-article record to the log, capped at MAX_LOG_ENTRIES
     * so the config value can't grow unbounded on a noisy blacklist.
     *
     * @param array<string,mixed> $rule
     */
    private function recordBlockedEntry(FreshRSS_Entry $entry, array $rule, string $matchedField, string $matchedPattern): void {
        $title = $entry->title();
        if (strlen($title) > self::MAX_LOG_TITLE_LENGTH) {
            $title = substr($title, 0, self::MAX_LOG_TITLE_LENGTH) . '…';
        }

        $log = $this->getLog();
        array_unshift($log, [
            'time' => time(),
            'rule_id' => $rule['id'] ?? '',
            'rule_name' => $rule['name'] ?? '',
            'feed_id' => $entry->feedId(),
            'title' => $title,
            'author' => $entry->author(),
            'link' => method_exists($entry, 'link') ? $entry->link() : '',
            'matched_field' => $matchedField,
            'matched_pattern' => $matchedPattern,
        ]);

        if (count($log) > self::MAX_LOG_ENTRIES) {
            $log = array_slice($log, 0, self::MAX_LOG_ENTRIES);
        }

        $this->saveLog($log);
    }

    /**
     * Handle configuration form submission
     */
    public function handleConfigureAction(): void {
        if (!Minz_Request::isPost()) {
            return;
        }

        if (Minz_Request::paramString('clear_log') !== '') {
            $this->saveLog([]);
            Minz_Log::notice('[RegexBlacklist] Blocked-article log cleared');
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
