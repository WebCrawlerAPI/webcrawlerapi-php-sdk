#!/bin/bash

# WebCrawlerAPI PHP SDK Test Runner

echo "🧪 WebCrawlerAPI PHP SDK Test Suite"
echo "====================================="

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo "📦 Installing dependencies..."
    composer install
fi

echo ""
echo "🔧 Running Unit Tests..."
echo "------------------------"
vendor/bin/phpunit tests/Unit --testdox

echo ""
echo "🌐 Running Integration Tests (requires API key)..."
echo "---------------------------------------------------"
echo "ℹ️  Set WEBCRAWLER_API_KEY environment variable to run integration tests"
if [ -n "$WEBCRAWLER_API_KEY" ]; then
    vendor/bin/phpunit tests/Integration --testdox
else
    echo "⚠️  Integration tests skipped (no API key)"
fi

echo ""
echo "📊 Running All Tests..."
echo "-----------------------"
vendor/bin/phpunit --testdox

echo ""
echo "✅ Test run completed!"
echo ""
echo "📝 To run tests with coverage (requires xdebug):"
echo "   vendor/bin/phpunit --coverage-html coverage/"
echo ""
echo "🚀 To run only unit tests:"
echo "   vendor/bin/phpunit tests/Unit"
echo ""
echo "🌐 To run only integration tests:"
echo "   WEBCRAWLER_API_KEY=your-key vendor/bin/phpunit tests/Integration"