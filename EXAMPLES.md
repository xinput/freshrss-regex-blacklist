# Regex Pattern Examples

Paste a whole block below directly into one rule's **Regex Pattern(s)** field — each line is matched independently (OR semantics), so a themed group like "Block Sponsored/Promotional Content" works well as a single rule with multiple lines.

## Content Filtering

### Block Sponsored/Promotional Content

```regex
sponsor
advertisement
ad\s?choice
promoted
promotional
```

### Block Clickbait Headlines

```regex
clickbait|click-bait
you won't believe
things that
(this|that) one (trick|hack)
doctors hate
```

### Block Affiliate Links

```regex
\baff?iliate\b
ref=
refid=
utm_source=
amazon.*?tag=
```

## Metadata Filtering

### Block by Tags

```regex
\[sponsored\]
\[ad\]
\[advertisement\]
```

### Block by Prefix

```regex
^\[AD\]
^Sponsored:
^Ad:
^Promotional:
```

## Topic-Based Filtering

### Block Sports

```regex
\bsports?\b
\b(nfl|nba|nhl|mlb|soccer|football)\b
\bgame\s*(recap|highlight)
```

### Block Entertainment/Celebrity

```regex
celebrity|gossip|paparazzi
reality\s?tv|reality show
```

## Format-Based Filtering

### Block Listicles

```regex
^\d+\s(ways?|things?|tips?|tricks?|reasons?)
\(part \d+\)
```

### Block "Best Of" Articles

```regex
best\s(of|in)\s
top\s\d+\s
greatest\s(hits|moments)
year.*in review
```

## Testing Tips

1. Use online regex tester: https://regex101.com/ (select PHP PCRE)
2. Remember: FreshRSS patterns are **case-insensitive**
3. Don't include delimiters: use `sponsor` not `/sponsor/`
4. Test patterns before deploying

## Performance Tips

- Simple patterns are fastest: `sponsor` before `(sponsor|sponsorship|sponsored)`
- Use anchors: `^Ad:` is faster than `Ad:.*title`
- Avoid excessive backtracking in complex patterns
