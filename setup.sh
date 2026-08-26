#!/bin/bash

set -e

echo "🚀 FreshRSS Regex Blacklist - Setup"
echo "===================================="
echo ""

echo "Checking PHP..."
php --version
echo "✓ PHP found"
echo ""

echo "Checking Composer..."
if ! command -v composer &> /dev/null; then
    echo "✗ Composer not found. Please install Composer first."
    exit 1
fi
composer --version
echo "✓ Composer found"
echo ""

echo "Installing dependencies..."
composer install --no-interaction
echo "✓ Dependencies installed"
echo ""

echo "Running tests..."
composer test
if [ $? -eq 0 ]; then
    echo "✓ All tests passed!"
else
    echo "✗ Tests failed."
    exit 1
fi
echo ""

echo "===================================="
echo "✓ Setup complete!"
echo ""
echo "Next steps:"
echo "  1. Update metadata.json with your info"
echo "  2. Run 'make help' to see available commands"
echo "  3. Run 'make test' to verify everything works"
echo ""
