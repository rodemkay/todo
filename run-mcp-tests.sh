#!/bin/bash

# 🧪 MCP Checkbox Test Runner Script
# Quick test execution with proper setup and reporting

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo -e "${BLUE}🧪 MCP Checkbox Test Automation Suite${NC}"
echo -e "${CYAN}===================================${NC}"
echo

# Function to print status messages
print_status() {
    echo -e "${CYAN}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    print_error "Node.js is not installed. Please install Node.js to run tests."
    exit 1
fi

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    print_error "npm is not installed. Please install npm to run tests."
    exit 1
fi

# Check if package.json exists
if [ ! -f "package.json" ]; then
    print_error "package.json not found. Please run this script from the project root directory."
    exit 1
fi

print_status "Checking dependencies..."

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
    print_warning "node_modules not found. Installing dependencies..."
    npm install
else
    print_success "Dependencies found"
fi

# Check if Playwright is installed
if [ ! -d "node_modules/@playwright" ]; then
    print_warning "Playwright not found. Installing Playwright..."
    npm install @playwright/test
fi

# Install Playwright browsers if needed
if [ ! -d "~/.cache/playwright" ] && [ ! -d "node_modules/.playwright" ]; then
    print_status "Installing Playwright browsers..."
    npx playwright install
fi

# Check network connectivity to staging server
print_status "Checking staging server connectivity..."
if curl -s --max-time 10 -I "https://forexsignale.trade/staging/wp-admin" > /dev/null; then
    print_success "Staging server is accessible"
else
    print_warning "Staging server may not be accessible. Tests might fail."
fi

echo
echo -e "${BLUE}🚀 Starting MCP Checkbox Tests${NC}"
echo -e "${CYAN}==============================${NC}"

# Parse command line arguments
MODE="normal"
BROWSER=""
PROJECT=""
HEADED=false
DEBUG=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --headed)
            HEADED=true
            shift
            ;;
        --debug)
            DEBUG=true
            shift
            ;;
        --browser=*)
            BROWSER="${1#*=}"
            shift
            ;;
        --project=*)
            PROJECT="${1#*=}"
            shift
            ;;
        --ui)
            MODE="ui"
            shift
            ;;
        --help|-h)
            echo "MCP Checkbox Test Runner"
            echo
            echo "Usage: $0 [OPTIONS]"
            echo
            echo "Options:"
            echo "  --headed              Run tests in headed mode (visible browser)"
            echo "  --debug               Run tests in debug mode (step-through)"
            echo "  --ui                  Run tests in UI mode (interactive)"
            echo "  --browser=BROWSER     Run tests on specific browser (chromium, firefox, webkit)"
            echo "  --project=PROJECT     Run specific project configuration"
            echo "  --help, -h           Show this help message"
            echo
            echo "Examples:"
            echo "  $0                           # Run all MCP tests normally"
            echo "  $0 --headed                  # Run with visible browser"
            echo "  $0 --debug                   # Run in debug mode"
            echo "  $0 --browser=firefox         # Run only in Firefox"
            echo "  $0 --project='Mobile Chrome' # Run mobile tests"
            echo
            exit 0
            ;;
        *)
            print_error "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Build the test command
TEST_CMD="npx playwright test mcp-checkbox-tests.spec.js"

# Add options based on flags
if [ "$HEADED" = true ]; then
    TEST_CMD="$TEST_CMD --headed"
fi

if [ "$DEBUG" = true ]; then
    TEST_CMD="$TEST_CMD --debug"
fi

if [ ! -z "$BROWSER" ]; then
    TEST_CMD="$TEST_CMD --project=$BROWSER"
fi

if [ ! -z "$PROJECT" ]; then
    TEST_CMD="$TEST_CMD --project='$PROJECT'"
fi

if [ "$MODE" = "ui" ]; then
    TEST_CMD="npx playwright test --ui"
fi

# Display test configuration
print_status "Test Configuration:"
echo "  - Mode: $MODE"
echo "  - Headed: $HEADED"
echo "  - Debug: $DEBUG"
if [ ! -z "$BROWSER" ]; then
    echo "  - Browser: $BROWSER"
fi
if [ ! -z "$PROJECT" ]; then
    echo "  - Project: $PROJECT"
fi

echo
print_status "Executing: $TEST_CMD"
echo

# Run the tests
if eval "$TEST_CMD"; then
    echo
    print_success "✅ All MCP checkbox tests completed successfully!"
    
    # Check if report was generated
    if [ -d "test-results" ]; then
        print_status "Test results saved to: test-results/"
    fi
    
    if [ -d "playwright-report" ]; then
        print_status "HTML report available at: playwright-report/index.html"
        echo
        echo -e "${CYAN}💡 Tip:${NC} Run 'npm run test:report' to view the detailed HTML report"
    fi
    
    echo
    print_success "Test automation suite completed successfully! 🎉"
    
else
    echo
    print_error "❌ Some tests failed or encountered errors"
    
    if [ -d "test-results" ]; then
        print_status "Check test results in: test-results/"
    fi
    
    if [ -d "playwright-report" ]; then
        print_status "View detailed report: playwright-report/index.html"
        echo
        echo -e "${CYAN}💡 Tip:${NC} Run 'npm run test:report' to view the HTML report"
    fi
    
    echo
    print_warning "Consider running with --headed or --debug for troubleshooting"
    exit 1
fi