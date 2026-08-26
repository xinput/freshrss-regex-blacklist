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
        $extension = $this->createMockExtension([$this->rule(pattern: 'sponsor', feedId: 42)]);

        $entryOnScopedFeed = $this->createMockEntry('sponsored post', '', '', 42);
        $entryOnOtherFeed = $this->createMockEntry('sponsored post', '', '', 7);

        $this->assertNull($extension->filterEntryOnImport($entryOnScopedFeed), 'Rule should apply to its scoped feed');
        $this->assertSame($entryOnOtherFeed, $extension->filterEntryOnImport($entryOnOtherFeed), 'Rule should not apply to a different feed');
    }

    public function testFeedScopeZeroMeansAllFeeds(): void {
        $extension = $this->createMockExtension([$this->rule(pattern: 'sponsor', feedId: 0)]);

        $entry = $this->createMockEntry('sponsored post', '', '', 999);

        $this->assertNull($extension->filterEntryOnImport($entry), 'feed_id 0 should mean "all feeds"');
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
                0 => ['id' => 'r1', 'name' => 'Renamed', 'pattern' => 'sponsor', 'match_field' => 'both', 'feed_id' => '0', 'enabled' => '1'],
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
                0 => ['id' => '', 'name' => 'Empty row from Add Rule', 'pattern' => '', 'match_field' => 'both', 'feed_id' => '0'],
                1 => ['id' => '', 'name' => 'Real rule', 'pattern' => 'sponsor', 'match_field' => 'both', 'feed_id' => '0', 'enabled' => '1'],
            ],
        ]);

        $extension->handleConfigureAction();

        $rules = json_decode($extension->getUserConfigurationString('rules') ?? '[]', true);
        $this->assertCount(1, $rules, 'Row with an empty pattern should be dropped');
        $this->assertSame('Real rule', $rules[0]['name']);
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function rule(
        ?string $id = null,
        string $name = 'Test rule',
        string $pattern = '',
        string $matchField = 'both',
        int $feedId = 0,
        bool $enabled = true,
        int $blockedCount = 0
    ): array {
        return [
            'id' => $id ?? bin2hex(random_bytes(4)),
            'name' => $name,
            'pattern' => $pattern,
            'match_field' => $matchField,
            'feed_id' => $feedId,
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
