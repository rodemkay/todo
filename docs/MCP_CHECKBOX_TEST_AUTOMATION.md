# 🧪 MCP Checkbox Test Automation Suite

## 📋 Overview
Comprehensive test automation suite for verifying MCP (Multi-Channel Platform) server checkbox functionality in the WordPress TODO plugin. These tests ensure reliable checkbox behavior, proper data persistence, and consistent user experience.

## 🎯 Test Scope

### Core Functionality Tests
1. **Default State Verification** - Ensures correct initial checkbox states
2. **Save/Load Persistence** - Verifies data saves and loads correctly
3. **Visual Consistency** - Checks that UI state matches checkbox state
4. **AJAX Operations** - Tests form submission and response handling
5. **Database Persistence** - Verifies data integrity in database
6. **Edge Cases** - Tests boundary conditions and error scenarios

### Performance Tests
1. **Rapid State Changes** - Tests UI responsiveness under load
2. **Network Timeout Handling** - Verifies graceful degradation
3. **Memory Usage** - Ensures efficient resource utilization

## 🔧 Technical Configuration

### Expected MCP Server Defaults
```javascript
const MCP_SERVERS = {
  'Context7': true,      // ✅ Default: checked
  'Playwright': false,   // ❌ Default: unchecked  
  'Filesystem': true,    // ✅ Default: checked
  'GitHub': false,       // ❌ Default: unchecked
  'Puppeteer': false,    // ❌ Default: unchecked
  'Docker': false,       // ❌ Default: unchecked
  'YouTube': false,      // ❌ Default: unchecked
  'Database': false,     // ❌ Default: unchecked
  'shadcn/ui': false     // ❌ Default: unchecked
};
```

### Test Environment
- **Target URL:** `https://forexsignale.trade/staging/wp-admin`
- **Browser Support:** Chrome, Firefox, Safari, Edge, Mobile browsers
- **Viewport:** Desktop (1280x720) and Mobile (375x667)
- **Authentication:** WordPress admin credentials required

## 🚀 Quick Start

### Installation
```bash
# Install dependencies (if not already installed)
npm install

# Install Playwright browsers
npm run test:install

# Install system dependencies
npm run test:install-deps
```

### Running Tests
```bash
# Run all MCP checkbox tests
npm run test:mcp

# Run with visual browser (for debugging)
npm run test:mcp:headed

# Run in debug mode (step-through)
npm run test:mcp:debug

# Run all tests
npm test

# View test report
npm run test:report
```

## 📊 Test Cases

### 1. Default State Verification Test
```javascript
test('should load new TODO page with correct default MCP checkbox states')
```
- ✅ Verifies Context7 and Filesystem are checked by default
- ✅ Ensures all other servers are unchecked initially
- ✅ Counts total available MCP server options (≥9)

### 2. Save/Load Persistence Test
```javascript
test('should maintain checkbox states when creating and saving a TODO')
```
- ✅ Creates new TODO with modified checkbox states
- ✅ Saves form and extracts TODO ID from response
- ✅ Reloads saved TODO and verifies persistence
- ✅ Confirms modified states match saved states

### 3. Visual Consistency Test
```javascript
test('should have visual consistency between checkbox state and styling')
```
- ✅ Checks that CSS classes match checkbox states
- ✅ Verifies `mcp-checked` class presence/absence
- ✅ Reports any visual-state inconsistencies

### 4. AJAX Operations Test
```javascript
test('should handle AJAX save operations correctly')
```
- ✅ Monitors network requests during form submission
- ✅ Verifies AJAX endpoints respond correctly
- ✅ Checks for success indicators after save
- ✅ Confirms no JavaScript errors occur

### 5. Database Persistence Test
```javascript
test('should validate MCP server data persistence in database')
```
- ✅ Creates TODO with specific MCP configuration
- ✅ Tests edge cases (no selections, all selections)
- ✅ Verifies data integrity after reload
- ✅ Confirms accurate server list storage

### 6. Edge Cases Test
```javascript
test('should handle edge cases and error conditions')
```
- ✅ Tests saving with zero checkboxes selected
- ✅ Tests saving with all checkboxes selected  
- ✅ Verifies graceful error handling
- ✅ Ensures no data corruption occurs

### 7. Performance Test
```javascript
test('should handle rapid checkbox state changes efficiently')
```
- ✅ Rapidly toggles all checkboxes multiple times
- ✅ Measures response time (< 5 seconds expected)
- ✅ Verifies final state consistency
- ✅ Checks for memory leaks

## 📈 Test Results Interpretation

### Success Criteria
- **✅ All tests pass:** MCP checkbox functionality is working correctly
- **⚠️ Some tests fail:** Indicates specific functionality issues
- **❌ All tests fail:** Major system or configuration problem

### Common Failure Patterns
1. **Default State Failures:** Configuration mismatch, missing defaults
2. **Persistence Failures:** Database issues, AJAX problems
3. **Visual Inconsistencies:** CSS/JavaScript synchronization issues
4. **Performance Issues:** Slow server response, memory leaks

## 🛠️ Troubleshooting

### Test Environment Issues
```bash
# Check if staging server is accessible
curl -I https://forexsignale.trade/staging/wp-admin

# Verify WordPress login credentials
# Check CLAUDE.md for current credentials

# Test network connectivity
ping forexsignale.trade
```

### Common Fixes
1. **Login Failures:** Update credentials in test configuration
2. **Timeout Issues:** Increase timeout values in playwright.config.js
3. **Element Not Found:** Check if UI elements changed
4. **Network Errors:** Verify staging server status

### Debug Mode Usage
```bash
# Run specific test in debug mode
npm run test:mcp:debug -- --grep "should load new TODO page"

# Generate trace for failed test
npm run test:mcp -- --trace=on

# Take screenshots on failure (auto-enabled)
npm run test:mcp -- --screenshot=only-on-failure
```

## 📋 Test Maintenance

### Regular Updates Needed
1. **Server List Changes:** Update `MCP_SERVERS` configuration
2. **UI Changes:** Update element selectors  
3. **New Features:** Add corresponding test cases
4. **Performance Benchmarks:** Adjust timeout thresholds

### Monthly Review Checklist
- [ ] Run full test suite and verify all pass
- [ ] Check for new MCP servers added to system
- [ ] Review test execution time for performance regression
- [ ] Update screenshots and documentation if UI changed
- [ ] Verify test coverage includes all critical paths

## 📊 Metrics & Reporting

### Key Performance Indicators
- **Test Pass Rate:** Target >95%
- **Execution Time:** <2 minutes for full suite
- **Coverage:** All MCP server options tested
- **Stability:** <5% flaky test rate

### Generated Reports
- **HTML Report:** Comprehensive results with screenshots
- **JSON Report:** Machine-readable data for CI/CD
- **JUnit XML:** Integration with build systems
- **Trace Files:** Step-by-step execution details

## 🔄 Continuous Integration

### GitHub Actions Integration
```yaml
# Example workflow step
- name: Run MCP Checkbox Tests
  run: npm run test:mcp
  env:
    CI: true
    PLAYWRIGHT_BROWSERS_PATH: ~/.cache/playwright
```

### Scheduled Testing
- **Daily:** Basic functionality tests
- **Weekly:** Full test suite including performance tests
- **Monthly:** Comprehensive review and maintenance

## 📝 Test Data Management

### Test TODO Creation
- Tests create temporary TODOs with predictable names
- Cleanup handled automatically after test completion
- No impact on production data (staging environment only)

### Data Isolation
- Each test creates unique identifiers (timestamps)
- Tests don't interfere with each other
- Proper cleanup prevents data accumulation

---

## 📞 Support

For test issues or questions:
1. Check this documentation first
2. Review test logs in `test-results/` directory  
3. Run debug mode for detailed analysis
4. Check staging server status and connectivity

**Last Updated:** 2025-01-21  
**Version:** 1.0.0  
**Maintained by:** Claude Code Test Automation