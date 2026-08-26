# Development Guide

## Quick Start

### 1. Setup

```bash
chmod +x setup.sh
./setup.sh
```

### 2. Run Tests

```bash
make test
```

### 3. Code Quality

```bash
make check
```

## Project Structure

```
xExtension-RegexBlacklist/
├── extension.php              # Main extension class
├── configure.phtml            # Configuration UI
├── metadata.json              # Extension metadata
├── README.md                  # User documentation
├── DEVELOPMENT.md             # This file
├── EXAMPLES.md                # Example patterns
├── composer.json              # Dependencies
├── phpunit.xml                # Test configuration
├── tests/
│   ├── bootstrap.php          # Test setup
│   └── RegexBlacklistTest.php # Unit tests
└── Makefile
```

## Common Commands

| Command | Purpose |
|---------|---------|
| `make test` | Run unit tests |
| `make check` | Tests + linting |
| `make clean` | Remove generated files |
| `composer test-coverage` | Test with coverage report |

## Git Workflow

1. Create branch: `git checkout -b feature/my-feature`
2. Make changes and run tests: `make check`
3. Commit: `git commit -m "feat: description"`
4. Push: `git push origin feature/my-feature`
5. Open PR and wait for CI/CD

## Making Changes

### Adding Tests

Add tests to `tests/RegexBlacklistTest.php`:

```php
public function testMyFeature(): void {
    $entry = $this->createMockEntry('Title', 'Content');
    $extension = $this->createMockExtension(['global_patterns' => 'pattern']);
    $result = $extension->filterEntryOnImport($entry);
    
    $this->assertNull($result);
}
```

### Adding Features

1. Write test first (TDD)
2. Implement in `extension.php`
3. Run tests to verify
4. Run lint checks: `make phpstan` and `make phpcs`

## Deployment

### To FreshRSS

```bash
cp -r . /path/to/freshrss/extensions/xExtension-RegexBlacklist/
```

### Docker

```bash
docker cp . container:/var/www/FreshRSS/extensions/xExtension-RegexBlacklist/
```

## Useful Resources

- FreshRSS Docs: https://freshrss.github.io/FreshRSS/
- PHP Regex: https://www.php.net/manual/en/function.preg-match.php
- PHPUnit: https://phpunit.de/

## Need Help?

1. Read README.md
2. Check EXAMPLES.md
3. Look at test cases
4. Run in dev mode: `FRESHRSS_ENV=development`
