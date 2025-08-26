const { test, expect } = require('@playwright/test');

/**
 * Comprehensive MCP Checkbox Test Suite
 * Tests the Multi-Channel Platform server checkbox functionality
 * in the WordPress TODO plugin
 */

// Test configuration
const BASE_URL = 'https://forexsignale.trade/staging/wp-admin';
const NEW_TODO_PAGE = `${BASE_URL}/admin.php?page=todo-new`;
const TODO_LIST_PAGE = `${BASE_URL}/admin.php?page=wp-project-todos`;

// Expected MCP servers and their default states
const MCP_SERVERS = {
  'Context7': true,      // Default: checked
  'Playwright': false,   // Default: unchecked
  'Filesystem': true,    // Default: checked
  'GitHub': false,       // Default: unchecked
  'Puppeteer': false,    // Default: unchecked
  'Docker': false,       // Default: unchecked
  'YouTube': false,      // Default: unchecked
  'Database': false,     // Default: unchecked
  'shadcn/ui': false     // Default: unchecked
};

const EXPECTED_DEFAULTS = ['Context7', 'Filesystem'];

test.describe('MCP Checkbox Functionality', () => {
  
  test.beforeEach(async ({ page }) => {
    // Navigate to WordPress admin and ensure we're logged in
    await page.goto(BASE_URL);
    
    // Check if we need to login
    const loginForm = await page.locator('#loginform').isVisible().catch(() => false);
    if (loginForm) {
      await page.fill('#user_login', 'ForexSignale');
      await page.fill('#user_pass', '.Foret333doka?');
      await page.click('#wp-submit');
      await page.waitForLoadState('networkidle');
    }
  });

  test('should load new TODO page with correct default MCP checkbox states', async ({ page }) => {
    await page.goto(NEW_TODO_PAGE);
    await page.waitForLoadState('networkidle');
    
    // Wait for MCP checkboxes to be visible
    await page.waitForSelector('input[name="mcp_servers[]"]', { timeout: 10000 });
    
    // Get all MCP checkbox states
    const mcpStates = await page.evaluate(() => {
      const checkboxes = document.querySelectorAll('input[name="mcp_servers[]"]');
      const results = {};
      
      checkboxes.forEach((cb) => {
        const label = cb.closest('.mcp-item')?.textContent?.trim() || '';
        // Extract server name from label
        const serverMatch = label.match(/(Context7|Playwright|Filesystem|GitHub|Puppeteer|Docker|YouTube|Database|shadcn\/ui)/);
        if (serverMatch) {
          const serverName = serverMatch[1];
          results[serverName] = cb.checked;
        }
      });
      
      return results;
    });
    
    // Verify default states match expected configuration
    for (const [server, expectedState] of Object.entries(MCP_SERVERS)) {
      expect(mcpStates[server]).toBe(expectedState, 
        `${server} should be ${expectedState ? 'checked' : 'unchecked'} by default`);
    }
    
    // Count total checkboxes
    const totalCheckboxes = Object.keys(mcpStates).length;
    expect(totalCheckboxes).toBeGreaterThanOrEqual(9, 'Should have at least 9 MCP server options');
    
    // Count checked checkboxes
    const checkedServers = Object.entries(mcpStates).filter(([_, checked]) => checked).map(([server, _]) => server);
    expect(checkedServers.sort()).toEqual(EXPECTED_DEFAULTS.sort(), 
      'Only default servers should be checked initially');
  });

  test('should maintain checkbox states when creating and saving a TODO', async ({ page }) => {
    await page.goto(NEW_TODO_PAGE);
    await page.waitForLoadState('networkidle');
    
    // Fill in basic TODO information
    const timestamp = Date.now();
    const todoTitle = `Test MCP Checkbox Save ${timestamp}`;
    
    await page.fill('input[name="title"]', todoTitle);
    await page.fill('textarea[name="description"]', 'Testing MCP checkbox save functionality');
    
    // Verify initial checkbox states
    const initialStates = await getMcpCheckboxStates(page);
    expect(initialStates.checkedServers.sort()).toEqual(EXPECTED_DEFAULTS.sort());
    
    // Modify some checkbox states for testing
    await page.check('input[name="mcp_servers[]"][value="playwright"]');
    await page.uncheck('input[name="mcp_servers[]"][value="context7"]');
    
    // Save the TODO
    await page.click('input[type="submit"][name="submit"]');
    await page.waitForLoadState('networkidle');
    
    // Get the created TODO ID from URL or success message
    const currentUrl = page.url();
    const todoIdMatch = currentUrl.match(/[?&]id=(\d+)/);
    expect(todoIdMatch).not.toBeNull('Should have TODO ID in URL after save');
    
    const todoId = todoIdMatch[1];
    
    // Navigate to edit the saved TODO
    await page.goto(`${BASE_URL}/admin.php?page=todo-new&id=${todoId}`);
    await page.waitForLoadState('networkidle');
    
    // Verify the modified checkbox states were saved
    const savedStates = await getMcpCheckboxStates(page);
    expect(savedStates.checkedServers).toContain('Playwright');
    expect(savedStates.checkedServers).not.toContain('Context7');
    expect(savedStates.checkedServers).toContain('Filesystem'); // Should still be checked
  });

  test('should have visual consistency between checkbox state and styling', async ({ page }) => {
    await page.goto(NEW_TODO_PAGE);
    await page.waitForLoadState('networkidle');
    
    // Check visual styling matches checkbox states
    const visualConsistency = await page.evaluate(() => {
      const checkboxes = document.querySelectorAll('input[name="mcp_servers[]"]');
      const inconsistencies = [];
      
      checkboxes.forEach((cb) => {
        const mcpItem = cb.closest('.mcp-item');
        if (mcpItem) {
          const hasCheckedClass = mcpItem.classList.contains('mcp-checked');
          const isChecked = cb.checked;
          
          if (hasCheckedClass !== isChecked) {
            const serverName = cb.value;
            inconsistencies.push({
              server: serverName,
              checkboxState: isChecked,
              visualState: hasCheckedClass
            });
          }
        }
      });
      
      return inconsistencies;
    });
    
    expect(visualConsistency).toHaveLength(0, 
      `Visual styling should match checkbox states. Inconsistencies: ${JSON.stringify(visualConsistency)}`);
  });

  test('should handle AJAX save operations correctly', async ({ page }) => {
    await page.goto(NEW_TODO_PAGE);
    await page.waitForLoadState('networkidle');
    
    // Fill in TODO form
    const timestamp = Date.now();
    await page.fill('input[name="title"]', `AJAX Test TODO ${timestamp}`);
    await page.fill('textarea[name="description"]', 'Testing AJAX save functionality');
    
    // Modify checkbox states
    await page.check('input[name="mcp_servers[]"][value="github"]');
    await page.check('input[name="mcp_servers[]"][value="docker"]');
    
    // Listen for network requests to verify AJAX calls
    const responses = [];
    page.on('response', response => {
      if (response.url().includes('admin-ajax.php')) {
        responses.push({
          status: response.status(),
          url: response.url()
        });
      }
    });
    
    // Save the form
    await page.click('input[type="submit"][name="submit"]');
    await page.waitForLoadState('networkidle');
    
    // Verify successful save (should have redirected or shown success message)
    const hasSuccessMessage = await page.locator('.notice-success, .updated').isVisible().catch(() => false);
    const hasRedirected = page.url() !== NEW_TODO_PAGE;
    
    expect(hasSuccessMessage || hasRedirected).toBeTruthy('Should show success indication after save');
  });

  test('should validate MCP server data persistence in database', async ({ page }) => {
    await page.goto(NEW_TODO_PAGE);
    await page.waitForLoadState('networkidle');
    
    // Create a TODO with specific MCP configuration
    const timestamp = Date.now();
    const testConfig = ['filesystem', 'github', 'docker'];
    
    await page.fill('input[name="title"]', `DB Persistence Test ${timestamp}`);
    await page.fill('textarea[name="description"]', 'Testing database persistence');
    
    // Uncheck all default checkboxes first
    for (const server of EXPECTED_DEFAULTS) {
      await page.uncheck(`input[name="mcp_servers[]"][value="${server.toLowerCase()}"]`);
    }
    
    // Check only our test configuration
    for (const server of testConfig) {
      await page.check(`input[name="mcp_servers[]"][value="${server}"]`);
    }
    
    // Save and get TODO ID
    await page.click('input[type="submit"][name="submit"]');
    await page.waitForLoadState('networkidle');
    
    const todoIdMatch = page.url().match(/[?&]id=(\d+)/);
    expect(todoIdMatch).not.toBeNull();
    const todoId = todoIdMatch[1];
    
    // Navigate back to edit page to verify persistence
    await page.goto(`${BASE_URL}/admin.php?page=todo-new&id=${todoId}`);
    await page.waitForLoadState('networkidle');
    
    const persistedStates = await getMcpCheckboxStates(page);
    expect(persistedStates.checkedServers.sort()).toEqual(testConfig.map(s => 
      s.charAt(0).toUpperCase() + s.slice(1)).sort());
  });

  test('should handle edge cases and error conditions', async ({ page }) => {
    await page.goto(NEW_TODO_PAGE);
    await page.waitForLoadState('networkidle');
    
    // Test: Save with no checkboxes selected
    await page.fill('input[name="title"]', 'No MCP Servers Test');
    await page.fill('textarea[name="description"]', 'Testing with no MCP servers selected');
    
    // Uncheck all checkboxes
    await page.evaluate(() => {
      document.querySelectorAll('input[name="mcp_servers[]"]').forEach(cb => cb.checked = false);
    });
    
    // Save should still work
    await page.click('input[type="submit"][name="submit"]');
    await page.waitForLoadState('networkidle');
    
    // Should not show error
    const hasError = await page.locator('.notice-error, .error').isVisible().catch(() => false);
    expect(hasError).toBeFalsy('Should not show error when no MCP servers are selected');
    
    // Test: All checkboxes selected
    await page.goto(NEW_TODO_PAGE);
    await page.waitForLoadState('networkidle');
    
    await page.fill('input[name="title"]', 'All MCP Servers Test');
    await page.fill('textarea[name="description"]', 'Testing with all MCP servers selected');
    
    // Check all checkboxes
    await page.evaluate(() => {
      document.querySelectorAll('input[name="mcp_servers[]"]').forEach(cb => cb.checked = true);
    });
    
    await page.click('input[type="submit"][name="submit"]');
    await page.waitForLoadState('networkidle');
    
    // Should save successfully
    const hasSuccess = await page.locator('.notice-success, .updated').isVisible().catch(() => false);
    const hasRedirected = page.url() !== NEW_TODO_PAGE;
    expect(hasSuccess || hasRedirected).toBeTruthy('Should save successfully with all checkboxes checked');
  });
});

// Helper function to get MCP checkbox states
async function getMcpCheckboxStates(page) {
  return await page.evaluate(() => {
    const checkboxes = document.querySelectorAll('input[name="mcp_servers[]"]');
    const checkedServers = [];
    const uncheckedServers = [];
    const allServers = {};
    
    checkboxes.forEach((cb) => {
      const label = cb.closest('.mcp-item')?.textContent?.trim() || '';
      const serverMatch = label.match(/(Context7|Playwright|Filesystem|GitHub|Puppeteer|Docker|YouTube|Database|shadcn\/ui)/);
      
      if (serverMatch) {
        const serverName = serverMatch[1];
        allServers[serverName] = cb.checked;
        
        if (cb.checked) {
          checkedServers.push(serverName);
        } else {
          uncheckedServers.push(serverName);
        }
      }
    });
    
    return {
      checkedServers: checkedServers.sort(),
      uncheckedServers: uncheckedServers.sort(),
      allServers,
      totalCount: Object.keys(allServers).length,
      checkedCount: checkedServers.length,
      uncheckedCount: uncheckedServers.length
    };
  });
}

// Performance test for MCP checkbox interactions
test.describe('MCP Checkbox Performance', () => {
  test('should handle rapid checkbox state changes efficiently', async ({ page }) => {
    await page.goto(NEW_TODO_PAGE);
    await page.waitForLoadState('networkidle');
    
    const startTime = Date.now();
    
    // Rapidly toggle all checkboxes multiple times
    for (let i = 0; i < 5; i++) {
      // Check all
      await page.evaluate(() => {
        document.querySelectorAll('input[name="mcp_servers[]"]').forEach(cb => cb.checked = true);
        document.querySelectorAll('input[name="mcp_servers[]"]').forEach(cb => cb.dispatchEvent(new Event('change')));
      });
      
      // Uncheck all
      await page.evaluate(() => {
        document.querySelectorAll('input[name="mcp_servers[]"]').forEach(cb => cb.checked = false);
        document.querySelectorAll('input[name="mcp_servers[]"]').forEach(cb => cb.dispatchEvent(new Event('change')));
      });
    }
    
    const endTime = Date.now();
    const duration = endTime - startTime;
    
    // Should complete within reasonable time (adjust threshold as needed)
    expect(duration).toBeLessThan(5000, 'Rapid checkbox changes should complete within 5 seconds');
    
    // Verify final state is correct
    const finalStates = await getMcpCheckboxStates(page);
    expect(finalStates.checkedCount).toBe(0, 'All checkboxes should be unchecked after test');
  });
});