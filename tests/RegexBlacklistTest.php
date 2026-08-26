<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class RegexBlacklistTest extends TestCase {

    public function testBlocksArticleMatchingGlobalPattern(): void {
        $entry = $this->createMockEntry(
            'Sponsored Content: Check This Out',
            'This is sponsored content'
        );

        $extension = $this->createMockExtension(['global_patterns' => 'sponsor']);
        $result = $extension->filterEntryOnImport($entry);

        $this->assertNull($result, 'Article should be blocked for matching sponsor pattern');
    }

    public function testAllowsArticleNotMatchingPattern(): void {
        $entry = $this->createMockEntry(
            'Regular Tech News',
            'This is legitimate tech news'
        );

        $extension = $this->createMockExtension(['global_patterns' => 'sponsor']);
        $result = $extension->filterEntryOnImport($entry);

        $this->assertSame($entry, $result, 'Article should be allowed when not matching pattern');
    }

    public function testCaseInsensitiveMatching(): void {
        $entry = $this->createMockEntry(
            'SPONSORED CONTENT',
            'Not important'
        );

        $extension = $this->createMockExtension(['global_patterns' => 'sponsor']);
        $result = $extension->filterEntryOnImport($entry);

        $this->assertNull($result, 'Should match case-insensitively');
    }

    public function testRegexAlternation(): void {
        $patterns = 'clickbait|click-bait|clck-bait';
        
        $entry1 = $this->createMockEntry('clickbait headline', '');
        $entry2 = $this->createMockEntry('click-bait article', '');
        $entry3 = $this->createMockEntry('legitimate news', '');

        $extension = $this->createMockExtension(['global_patterns' => $patterns]);

        $this->assertNull($extension->filterEntryOnImport($entry1));
        $this->assertNull($extension->filterEntryOnImport($entry2));
        $this->assertSame($entry3, $extension->filterEntryOnImport($entry3));
    }

    public function testAnchorPatterns(): void {
        $extension = $this->createMockExtension(['global_patterns' => '^\\[AD\\]']);
        
        $entry1 = $this->createMockEntry('[AD] Something', '');
        $entry2 = $this->createMockEntry('Something [AD]', '');

        $this->assertNull($extension->filterEntryOnImport($entry1), 'Should match at start');
        $this->assertSame($entry2, $extension->filterEntryOnImport($entry2), 'Should not match in middle');
    }

    public function testMultiplePatterns(): void {
        $patterns = "sponsor\nadvertise\n\\[ad\\]";
        $extension = $this->createMockExtension(['global_patterns' => $patterns]);

        $entryA = $this->createMockEntry('sponsored content', '');
        $entryB = $this->createMockEntry('advertisement', '');
        $entryC = $this->createMockEntry('[ad] link', '');
        $entryD = $this->createMockEntry('real news', '');

        $this->assertNull($extension->filterEntryOnImport($entryA));
        $this->assertNull($extension->filterEntryOnImport($entryB));
        $this->assertNull($extension->filterEntryOnImport($entryC));
        $this->assertSame($entryD, $extension->filterEntryOnImport($entryD));
    }

    public function testContentMatching(): void {
        $entry = $this->createMockEntry(
            'Interesting Article',
            'Some text here with sponsored mention'
        );

        $extension = $this->createMockExtension(['global_patterns' => 'sponsor']);
        $result = $extension->filterEntryOnImport($entry);

        $this->assertNull($result, 'Should match in content');
    }

    public function testHandlesNullEntry(): void {
        $extension = $this->createMockExtension(['global_patterns' => 'pattern']);
        $result = $extension->filterEntryOnImport(null);

        $this->assertNull($result, 'Should return null for null entry');
    }

    public function testHandlesInvalidRegex(): void {
        $entry = $this->createMockEntry('Title', 'Content');
        $extension = $this->createMockExtension(['global_patterns' => '[invalid(regex']);
        $result = $extension->filterEntryOnImport($entry);

        $this->assertNotNull($result, 'Should return entry for invalid regex (fail-safe)');
    }

    public function testPatternContainingSlashMatches(): void {
        $entry = $this->createMockEntry(
            'Interesting article',
            'Read more at https://ads.example.com/promo'
        );

        $extension = $this->createMockExtension(['global_patterns' => 'https?://ads\\.example\\.com']);
        $result = $extension->filterEntryOnImport($entry);

        $this->assertNull($result, 'Pattern containing literal slashes should still match content');
    }

    public function testHandleConfigureActionSavesPatterns(): void {
        Minz_Request::_setTestParams(['global_patterns' => "sponsor\nadvertise"]);
        $extension = $this->createMockExtension([]);

        $extension->handleConfigureAction();

        $entry = $this->createMockEntry('sponsored content', '');
        $this->assertNull($extension->filterEntryOnImport($entry), 'Saved patterns should be applied on the next check');

        Minz_Request::_resetTest();
    }

    private function createMockEntry(string $title, string $content): FreshRSS_Entry {
        $entry = new FreshRSS_Entry();
        $entry->_title($title);
        $entry->_content($content);
        $entry->_id(uniqid());
        $entry->_feedId(1);
        return $entry;
    }

    private function createMockExtension(array $config): RegexBlacklistExtension {
        $extension = new RegexBlacklistExtension();
        foreach ($config as $key => $value) {
            $extension->testSetUserConfiguration($key, $value);
        }
        return $extension;
    }
}
