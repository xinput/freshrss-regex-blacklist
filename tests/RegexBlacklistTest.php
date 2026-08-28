<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class RegexBlacklistTest extends TestCase {

    protected function tearDown(): void {
        Minz_Request::_resetTest();
    }

    public function testBlocksArticleMatchingEnabledRule(): void {
        $entry = $this->createMockEntry('Sponsored Content: Check This Out', 'This is sponsored content');
        $extension = $this->createMockExtension([$this->rule(pattern: 'sponsor')]);

        $this->assertNull($extension->filterEntryOnImport($entry), 'Article should be blocked for matching sponsor pattern');
    }

    public function testAllowsArticleNotMatchingAnyRule(): void {
        $entry = $this->createMockEntry('Regular Tech News', 'This is legitimate tech news');
        $extension = $this->createMockExtension([$this->rule(pattern: 'sponsor')]);

        $this->assertSame($entry, $extension->filterEntryOnImport($entry), 'Article should be allowed when not matching any rule');
    }

    public function testCaseInsensitiveMatching(): void {
        $entry = $this->createMockEntry('SPONSORED CONTENT', 'Not important');
        $extension = $this->createMockExtension([$this->rule(pattern: 'sponsor')]);

        $this->assertNull($extension->filterEntryOnImport($entry), 'Should match case-insensitively');
    }

    public function testRegexAlternation(): void {
        $extension = $this->createMockExtension([$this->rule(pattern: 'clickbait|click-bait|clck-bait')]);

        $entry1 = $this->createMockEntry('clickbait headline', '');
        $entry2 = $this->createMockEntry('click-bait article', '');
        $entry3 = $this->createMockEntry('legitimate news', '');

        $this->assertNull($extension->filterEntryOnImport($entry1));
        $this->assertNull($extension->filterEntryOnImport($entry2));
        $this->assertSame($entry3, $extension->filterEntryOnImport($entry3));
    }

    public function testAnchorPatterns(): void {
        $extension = $this->createMockExtension([$this->rule(pattern: '^\\[AD\\]')]);

        $entry1 = $this->createMockEntry('[AD] Something', '');
        $entry2 = $this->createMockEntry('Something [AD]', '');

        $this->assertNull($extension->filterEntryOnImport($entry1), 'Should match at start');
        $this->assertSame($entry2, $extension->filterEntryOnImport($entry2), 'Should not match in middle');
    }

    public function testMultipleRulesAnyMatchBlocks(): void {
        $extension = $this->createMockExtension([
            $this->rule(name: 'sponsor', pattern: 'sponsor'),
            $this->rule(name: 'advertise', pattern: 'advertise'),
            $this->rule(name: 'ad-tag', pattern: '\\[ad\\]'),
        ]);

        $entryA = $this->createMockEntry('sponsored content', '');
        $entryB = $this->createMockEntry('advertisement', '');
        $entryC = $this->createMockEntry('[ad] link', '');
        $entryD = $this->createMockEntry('real news', '');

        $this->assertNull($extension->filterEntryOnImport($entryA));
        $this->assertNull($extension->filterEntryOnImport($entryB));
        $this->assertNull($extension->filterEntryOnImport($entryC));
        $this->assertSame($entryD, $extension->filterEntryOnImport($entryD));
    }

    public function testMultiplePatternsInOneRuleAnyLineMatches(): void {
        $extension = $this->createMockExtension([$this->rule(pattern: "sponsor\nadvertise\n\\[ad\\]")]);

        $entryA = $this->createMockEntry('sponsored content', '');
        $entryB = $this->createMockEntry('advertisement', '');
        $entryC = $this->createMockEntry('[ad] link', '');
        $entryD = $this->createMockEntry('real news', '');

        $this->assertNull($extension->filterEntryOnImport($entryA));
        $this->assertNull($extension->filterEntryOnImport($entryB));
        $this->assertNull($extension->filterEntryOnImport($entryC));
        $this->assertSame($entryD, $extension->filterEntryOnImport($entryD));
    }

    public function testInvalidPatternLineDoesNotSuppressOtherLinesInSameRule(): void {
        $extension = $this->createMockExtension([$this->rule(pattern: "[invalid(regex\nsponsor")]);

        $entry = $this->createMockEntry('sponsored content', '');

        $this->assertNull($extension->filterEntryOnImport($entry), 'A bad line should be skipped, not disable the whole rule');
    }

    public function testMatchFieldTitleOnlyIgnoresContent(): void {
        $entry = $this->createMockEntry('Interesting Article', 'sponsored mention buried in content');
        $extension = $this->createMockExtension([$this->rule(pattern: 'sponsor', matchField: 'title')]);

        $this->assertSame($entry, $extension->filterEntryOnImport($entry), 'Title-only rule should ignore a content-only match');
    }

    public function testMatchFieldContentOnlyIgnoresTitle(): void {
        $entry = $this->createMockEntry('Sponsored Headline', 'unrelated body text');
        $extension = $this->createMockExtension([$this->rule(pattern: 'sponsor', matchField: 'content')]);

        $this->assertSame($entry, $extension->filterEntryOnImport($entry), 'Content-only rule should ignore a title-only match');
    }

    public function testMatchFieldAuthorMatchesAuthorOnly(): void {
        $entry = $this->createMockEntry('Regular title', 'Regular content', 'PromoBot');
        $extension = $this->createMockExtension([$this->rule(pattern: 'promobot', matchField: 'author')]);

        $this->assertNull($extension->filterEntryOnImport($entry), 'Author-field rule should match on author');
    }

    public function testMatchFieldBothChecksTitleAndContent(): void {
        $entry = $this->createMockEntry('Interesting Article', 'Some text here with sponsored mention');
        $extension = $this->createMockExtension([$this->rule(pattern: 'sponsor', matchField: 'both')]);

        $this->assertNull($extension->filterEntryOnImport($entry), 'Should match in content when field is "both"');
    }

    public function testFeedScopeRestrictsToSpecificFeed(): void {
        $extension = $this->createMockExtension([$this->rule(pattern: 'sponsor', feedIds: [42])]);

        $entryOnScopedFeed = $this->createMockEntry('sponsored post', '', '', 42);
        $entryOnOtherFeed = $this->createMockEntry('sponsored post', '', '', 7);

        $this->assertNull($extension->filterEntryOnImport($entryOnScopedFeed), 'Rule should apply to its scoped feed');
        $this->assertSame($entryOnOtherFeed, $extension->filterEntryOnImport($entryOnOtherFeed), 'Rule should not apply to a different feed');
    }

    public function testFeedScopeMultipleFeedsAppliesToAnyOfThem(): void {
        $extension = $this->createMockExtension([$this->rule(pattern: 'sponsor', feedIds: [42, 43])]);

        $entryOnFirstFeed = $this->createMockEntry('sponsored post', '', '', 42);
        $entryOnSecondFeed = $this->createMockEntry('sponsored post', '', '', 43);
        $entryOnOtherFeed = $this->createMockEntry('sponsored post', '', '', 7);

        $this->assertNull($extension->filterEntryOnImport($entryOnFirstFeed));
        $this->assertNull($extension->filterEntryOnImport($entryOnSecondFeed));
        $this->assertSame($entryOnOtherFeed, $extension->filterEntryOnImport($entryOnOtherFeed));
    }

    public function testFeedScopeEmptyMeansAllFeeds(): void {
        $extension = $this->createMockExtension([$this->rule(pattern: 'sponsor', feedIds: [])]);

        $entry = $this->createMockEntry('sponsored post', '', '', 999);

        $this->assertNull($extension->filterEntryOnImport($entry), 'An empty feed_ids list should mean "all feeds"');
    }

    public function testDisabledRuleNeverBlocks(): void {
        $entry = $this->createMockEntry('sponsored content', '');
        $extension = $this->createMockExtension([$this->rule(pattern: 'sponsor', enabled: false)]);

        $this->assertSame($entry, $extension->filterEntryOnImport($entry), 'Disabled rule should not block');
    }

    public function testHandlesNullEntry(): void {
        $extension = $this->createMockExtension([$this->rule(pattern: 'pattern')]);

        $this->assertNull($extension->filterEntryOnImport(null), 'Should return null for null entry');
    }

    public function testHandlesInvalidRegex(): void {
        $entry = $this->createMockEntry('Title', 'Content');
        $extension = $this->createMockExtension([$this->rule(pattern: '[invalid(regex')]);

        $this->assertNotNull($extension->filterEntryOnImport($entry), 'Should return entry for invalid regex (fail-safe)');
    }

    public function testPatternContainingSlashMatches(): void {
        $entry = $this->createMockEntry('Interesting article', 'Read more at https://ads.example.com/promo');
        $extension = $this->createMockExtension([$this->rule(pattern: 'https?://ads\\.example\\.com')]);

        $this->assertNull($extension->filterEntryOnImport($entry), 'Pattern containing literal slashes should still match content');
    }

    public function testBlockedCountIncrementsOnMatch(): void {
        $extension = $this->createMockExtension([$this->rule(id: 'r1', pattern: 'sponsor')]);

        $extension->filterEntryOnImport($this->createMockEntry('sponsored', ''));
        $extension->filterEntryOnImport($this->createMockEntry('sponsored again', ''));
        $extension->filterEntryOnImport($this->createMockEntry('unrelated', ''));

        $rules = json_decode($extension->getUserConfigurationString('rules') ?? '[]', true);
        $this->assertSame(2, $rules[0]['blocked_count']);
    }

    public function testHandleConfigureActionSavesRulesAndPreservesBlockedCount(): void {
        $extension = $this->createMockExtension([$this->rule(id: 'r1', name: 'Old', pattern: 'sponsor', blockedCount: 5)]);

        Minz_Request::_setTestParams([
            'rules' => [
                0 => ['id' => 'r1', 'name' => 'Renamed', 'pattern' => 'sponsor', 'match_field' => 'both', 'feed_ids' => '', 'enabled' => '1'],
            ],
        ]);

        $extension->handleConfigureAction();

        $rules = json_decode($extension->getUserConfigurationString('rules') ?? '[]', true);
        $this->assertCount(1, $rules);
        $this->assertSame('Renamed', $rules[0]['name']);
        $this->assertSame(5, $rules[0]['blocked_count'], 'Blocked count should carry over when the rule id is unchanged');
    }

    public function testHandleConfigureActionSkipsBlankPatternRows(): void {
        $extension = $this->createMockExtension([]);

        Minz_Request::_setTestParams([
            'rules' => [
                0 => ['id' => '', 'name' => 'Empty row from Add Rule', 'pattern' => '', 'match_field' => 'both', 'feed_ids' => ''],
                1 => ['id' => '', 'name' => 'Real rule', 'pattern' => 'sponsor', 'match_field' => 'both', 'feed_ids' => '', 'enabled' => '1'],
            ],
        ]);

        $extension->handleConfigureAction();

        $rules = json_decode($extension->getUserConfigurationString('rules') ?? '[]', true);
        $this->assertCount(1, $rules, 'Row with an empty pattern should be dropped');
        $this->assertSame('Real rule', $rules[0]['name']);
    }

    public function testReimportOfSameArticleDoesNotInflateCountOrLog(): void {
        // FreshRSS retries import of a blocked entry on every feed refresh, since
        // a blocked entry is never inserted and so never gets a dedup record.
        $extension = $this->createMockExtension([$this->rule(id: 'r1', pattern: 'sponsor')]);

        $entry = $this->createMockEntry('Sponsored Content', '');
        $entry->_guid('article-guid-1');

        $extension->filterEntryOnImport($entry);
        $extension->filterEntryOnImport($entry);
        $extension->filterEntryOnImport($entry);

        $rules = json_decode($extension->getUserConfigurationString('rules') ?? '[]', true);
        $log = json_decode($extension->getUserConfigurationString('log') ?? '[]', true);
        $this->assertSame(1, $rules[0]['blocked_count'], 'Re-blocking the same article should only count once');
        $this->assertCount(1, $log, 'Re-blocking the same article should only log once');
    }

    public function testDifferentArticlesWithSameTitleButDifferentGuidBothCount(): void {
        $extension = $this->createMockExtension([$this->rule(pattern: 'sponsor')]);

        $entryA = $this->createMockEntry('Sponsored Content', '');
        $entryA->_guid('guid-a');
        $entryB = $this->createMockEntry('Sponsored Content', '');
        $entryB->_guid('guid-b');

        $extension->filterEntryOnImport($entryA);
        $extension->filterEntryOnImport($entryB);

        $rules = json_decode($extension->getUserConfigurationString('rules') ?? '[]', true);
        $this->assertSame(2, $rules[0]['blocked_count']);
    }

    public function testSameGuidOnDifferentFeedsCountsSeparately(): void {
        $extension = $this->createMockExtension([$this->rule(pattern: 'sponsor', feedIds: [])]);

        $entryFeed1 = $this->createMockEntry('sponsored post', '', '', 1);
        $entryFeed1->_guid('shared-guid');
        $entryFeed2 = $this->createMockEntry('sponsored post', '', '', 2);
        $entryFeed2->_guid('shared-guid');

        $extension->filterEntryOnImport($entryFeed1);
        $extension->filterEntryOnImport($entryFeed2);

        $rules = json_decode($extension->getUserConfigurationString('rules') ?? '[]', true);
        $this->assertSame(2, $rules[0]['blocked_count'], 'Same guid on different feeds should not be treated as a dupe');
    }

    public function testBlockedEntryIsRecordedInLog(): void {
        $extension = $this->createMockExtension([$this->rule(id: 'r1', name: 'Sponsor rule', pattern: "advertise\nsponsor", feedIds: [42])]);

        $entry = $this->createMockEntry('Sponsored Content', 'body text', 'PromoBot', 42);
        $entry->_link('https://example.com/article');

        $extension->filterEntryOnImport($entry);

        $log = json_decode($extension->getUserConfigurationString('log') ?? '[]', true);
        $this->assertCount(1, $log);
        $this->assertSame('r1', $log[0]['rule_id']);
        $this->assertSame('Sponsor rule', $log[0]['rule_name']);
        $this->assertSame(42, $log[0]['feed_id']);
        $this->assertSame('Sponsored Content', $log[0]['title']);
        $this->assertSame('https://example.com/article', $log[0]['link']);
        $this->assertSame('title', $log[0]['matched_field'], 'Should attribute the match to the specific field, not the rule\'s "both" setting');
        $this->assertSame('sponsor', $log[0]['matched_pattern'], 'Should record the specific pattern line that matched');
    }

    public function testAllowedEntryIsNotRecordedInLog(): void {
        $extension = $this->createMockExtension([$this->rule(pattern: 'sponsor')]);

        $extension->filterEntryOnImport($this->createMockEntry('Regular Tech News', ''));

        $log = json_decode($extension->getUserConfigurationString('log') ?? '[]', true);
        $this->assertSame([], $log);
    }

    public function testLogIsPrependedNewestFirstAndCapped(): void {
        $extension = $this->createMockExtension([$this->rule(pattern: 'sponsor')]);

        for ($i = 0; $i < 205; $i++) {
            $extension->filterEntryOnImport($this->createMockEntry('sponsored #' . $i, ''));
        }

        $log = json_decode($extension->getUserConfigurationString('log') ?? '[]', true);
        $this->assertCount(200, $log, 'Log should be capped at MAX_LOG_ENTRIES');
        $this->assertSame('sponsored #204', $log[0]['title'], 'Newest blocked article should be first');
    }

    public function testClearLogRemovesAllEntriesWithoutTouchingRules(): void {
        $extension = $this->createMockExtension([$this->rule(id: 'r1', name: 'Keep me', pattern: 'sponsor', blockedCount: 3)]);
        $extension->filterEntryOnImport($this->createMockEntry('sponsored', ''));

        Minz_Request::_setTestParams(['clear_log' => '1']);
        $extension->handleConfigureAction();

        $log = json_decode($extension->getUserConfigurationString('log') ?? '[]', true);
        $rules = json_decode($extension->getUserConfigurationString('rules') ?? '[]', true);
        $this->assertSame([], $log);
        $this->assertCount(1, $rules, 'Rules should be untouched by a clear-log submission');
        $this->assertSame('Keep me', $rules[0]['name']);
    }

    public function testHandleConfigureActionParsesCommaSeparatedFeedIds(): void {
        $extension = $this->createMockExtension([]);

        Minz_Request::_setTestParams([
            'rules' => [
                0 => ['id' => '', 'name' => 'Scoped rule', 'pattern' => 'sponsor', 'match_field' => 'both', 'feed_ids' => '5, 12,7', 'enabled' => '1'],
            ],
        ]);

        $extension->handleConfigureAction();

        $rules = json_decode($extension->getUserConfigurationString('rules') ?? '[]', true);
        $this->assertSame([5, 12, 7], $rules[0]['feed_ids']);
    }

    /**
     * @param int[] $feedIds
     * @return array<string,mixed>
     */
    private function rule(
        ?string $id = null,
        string $name = 'Test rule',
        string $pattern = '',
        string $matchField = 'both',
        array $feedIds = [],
        bool $enabled = true,
        int $blockedCount = 0
    ): array {
        return [
            'id' => $id ?? bin2hex(random_bytes(4)),
            'name' => $name,
            'pattern' => $pattern,
            'match_field' => $matchField,
            'feed_ids' => $feedIds,
            'enabled' => $enabled,
            'blocked_count' => $blockedCount,
        ];
    }

    private function createMockEntry(string $title, string $content, string $author = '', int $feedId = 1): FreshRSS_Entry {
        $entry = new FreshRSS_Entry();
        $entry->_title($title);
        $entry->_content($content);
        $entry->_author($author);
        $entry->_feedId($feedId);
        return $entry;
    }

    /**
     * @param array<int,array<string,mixed>> $rules
     */
    private function createMockExtension(array $rules): RegexBlacklistExtension {
        $extension = new RegexBlacklistExtension();
        $extension->testSetUserConfiguration('rules', json_encode($rules));
        return $extension;
    }
}
