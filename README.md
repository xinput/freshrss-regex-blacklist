# FreshRSS Regex Blacklist Extension

A FreshRSS extension that prevents articles from being imported based on named regex rules, each scoped to a match field and (optionally) a specific feed — similar to TTRSS's filter rules.

## Features

- **Named rules** — each rule has its own name, pattern, match field, and feed scope
- **Regex-based filtering** at import time (prevents database bloat)
- **Per-rule feed scope** — apply to one specific feed, or all feeds
- **Per-rule match field** — Title only, Content only, Title + Content, or Author
- **Built-in regex tester** — paste sample text and see live match/no-match while editing a rule
- **Per-rule blocked-count** to monitor which rules are actually firing
- **Case-insensitive matching** by default
- **Fail-safe design** — invalid patterns don't break imports, they're just skipped (with a warning logged)

## Installation

### Option 1: Clone from Git

```bash
cd /path/to/freshrss/extensions
git clone https://github.com/yourusername/freshrss-regex-blacklist.git xExtension-RegexBlacklist
```

### Option 2: Manual Installation

1. Download this archive
2. Extract to `FreshRSS/extensions/xExtension-RegexBlacklist`
3. Ensure the directory contains: `extension.php`, `configure.phtml`, `metadata.json`

### Option 3: Docker

```yaml
volumes:
  - ./freshrss-regex-blacklist:/var/www/FreshRSS/extensions/xExtension-RegexBlacklist
```

## Usage

1. Go to **Settings → Extensions** in FreshRSS
2. Find **Regex Blacklist** and click **Configure**
3. Click **+ Add Rule** for each blocking rule you want:
   - **Rule Name** — a label to identify it (e.g. "Block sponsored posts")
   - **Regex Pattern** — PHP PCRE syntax, no delimiters (e.g. `sponsor`)
   - **Match** — which field(s) to test the pattern against: Title + Content, Title only, Content only, or Author
   - **Applies to Feed** — a specific subscribed feed, or "All feeds"
   - **Test** — opens an inline tester: paste sample text and see live match/no-match against your pattern
4. Click **Save Configuration**

The first enabled rule that matches an incoming article blocks it from import. Each rule's **Blocked** column shows how many articles it has blocked so far.

## Pattern Examples

```regex
# Simple substring match
sponsor
advertisement

# Start of title
^Ad:

# Alternation
clickbait|click-bait
(sponsor|sponsored|sponsorship)

# Tags
\[ad\]
\[SPONSORED\]
```

## How It Works

1. When FreshRSS refreshes feeds, new articles are intercepted
2. Rules are checked in order; a rule only applies if it's enabled and its feed scope matches (or it's scoped to "All feeds")
3. The rule's match field(s) are tested against its pattern
4. On the first matching rule, the article is **blocked from import** and that rule's blocked-count is incremented
5. Matched articles never enter your database

## Performance

- Per-article overhead: 0.5-2ms (depends on pattern complexity)
- For 100 articles: ~50-200ms total
- Minimal memory footprint

## Troubleshooting

### Extension not showing in Settings

- Verify folder name is `xExtension-RegexBlacklist`
- Check file permissions
- Clear FreshRSS cache

### Patterns not working

- Test pattern in online regex tester (PHP PCRE)
- Remember: patterns are **case-insensitive**
- Don't include delimiters (use `sponsor` not `/sponsor/`)

## License

AGPL-3.0-or-later
