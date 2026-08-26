# FreshRSS Regex Blacklist Extension

A powerful FreshRSS extension that prevents articles from being imported based on regex pattern matching against title and content.

## Features

- **Regex-based filtering** at import time (prevents database bloat)
- **Global patterns** applied to all feeds
- **Case-insensitive matching** by default
- **Statistics tracking** to monitor blocked articles
- **Fail-safe design** — invalid patterns don't break imports
- **Per-field matching** — checks both title and content

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
3. Enter regex patterns (one per line)
4. Click **Save Configuration**

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
2. Each article's **title** and **content** are tested against patterns
3. If **any pattern matches**, the article is **blocked from import**
4. Matched articles never enter your database
5. Statistics are tracked for monitoring

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
