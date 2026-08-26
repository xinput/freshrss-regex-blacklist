.PHONY: help install test lint check clean

help:
	@echo "FreshRSS Regex Blacklist - Development Commands"
	@echo ""
	@echo "Setup:"
	@echo "  make install          Install dependencies"
	@echo ""
	@echo "Testing:"
	@echo "  make test             Run PHPUnit tests"
	@echo "  make test-coverage    Run tests with coverage report"
	@echo ""
	@echo "Code Quality:"
	@echo "  make lint             Run PHPStan and PHPCS"
	@echo "  make phpstan          Run PHPStan analysis"
	@echo "  make phpcs            Run PHPCS linting"
	@echo "  make check            Run tests + lint"
	@echo ""
	@echo "Maintenance:"
	@echo "  make clean            Remove generated files"

install:
	composer install

test:
	composer test

test-coverage:
	composer test-coverage

lint:
	composer lint

phpstan:
	composer phpstan

phpcs:
	composer phpcs

check:
	composer check

clean:
	rm -rf vendor/
	rm -rf coverage/
	rm -f .phpunit.result.cache

.DEFAULT_GOAL := help
