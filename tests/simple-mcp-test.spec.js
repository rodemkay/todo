import { test, expect } from '@playwright/test';

/**
 * Simple MCP Checkbox Test
 * Basic verification of checkbox functionality
 */

const BASE_URL = 'https://forexsignale.trade/staging/wp-admin';
const NEW_TODO_PAGE = `${BASE_URL}/admin.php?page=todo-new`;

test.describe('MCP Checkbox Basic Tests', () => {
  
  test.beforeEach(async ({ page }) => {
    // Navigate to WordPress admin
    await page.goto(BASE_URL);
    
    // Handle login if needed
    const loginForm = await page.locator('#loginform').isVisible().catch(() => false);
    if (loginForm) {
      await page.fill('#user_login', 'ForexSignale');
      await page.fill('#user_pass', '.Foret333doka?');
      await page.click('#wp-submit');
      await page.waitForLoadState('networkidle');
    }
  });

  test('should load MCP checkboxes with correct defaults', async ({ page }) => {
    await page.goto(NEW_TODO_PAGE);
    await page.waitForLoadState('networkidle');
    
    // Wait for MCP checkboxes to load
    await page.waitForSelector('input[name="mcp_servers[]"]', { timeout: 10000 });
    
    // Count total checkboxes
    const checkboxCount = await page.locator('input[name="mcp_servers[]"]').count();
    console.log(`Found ${checkboxCount} MCP server checkboxes`);
    
    // Verify we have at least the expected servers
    expect(checkboxCount).toBeGreaterThanOrEqual(9);
    
    // Get checkbox states using JavaScript
    const checkboxStates = await page.evaluate(() => {
      const checkboxes = document.querySelectorAll('input[name="mcp_servers[]"]');
      const results = {
        total: checkboxes.length,
        checked: [],
        unchecked: [],
        allStates: {}
      };
      
      checkboxes.forEach((cb) => {
        const value = cb.value;
        const checked = cb.checked;
        results.allStates[value] = checked;
        
        if (checked) {
          results.checked.push(value);
        } else {
          results.unchecked.push(value);
        }
      });
      
      return results;
    });
    
    console.log('Checkbox states:', JSON.stringify(checkboxStates, null, 2));
    
    // Verify default servers are checked
    const expectedDefaults = ['context7', 'filesystem'];
    expectedDefaults.forEach(server => {
      expect(checkboxStates.checked).toContain(server);
    });
    
    // Take screenshot for verification
    await page.screenshot({
      path: '/home/rodemkay/www/react/plugin-todo/test-results/mcp-checkboxes-simple-test.png',
      fullPage: true
    });
  });

  test('should handle checkbox state changes', async ({ page }) => {
    await page.goto(NEW_TODO_PAGE);
    await page.waitForLoadState('networkidle');
    
    // Wait for checkboxes
    await page.waitForSelector('input[name="mcp_servers[]"]', { timeout: 10000 });
    
    // Get initial states
    const initialStates = await page.evaluate(() => {
      const checkboxes = document.querySelectorAll('input[name="mcp_servers[]"]');
      return Array.from(checkboxes).map(cb => ({
        value: cb.value,
        checked: cb.checked
      }));
    });
    
    console.log('Initial states:', initialStates);
    
    // Try to toggle a checkbox (playwright server)
    const playwrightCheckbox = page.locator('input[name="mcp_servers[]"][value="playwright"]');
    
    if (await playwrightCheckbox.isVisible()) {
      const wasChecked = await playwrightCheckbox.isChecked();
      await playwrightCheckbox.click();
      const nowChecked = await playwrightCheckbox.isChecked();
      
      // Verify state changed
      expect(nowChecked).toBe(!wasChecked);
      console.log(`Playwright checkbox: ${wasChecked} → ${nowChecked}`);
    }
    
    // Verify visual consistency
    const visualCheck = await page.evaluate(() => {
      const checkboxes = document.querySelectorAll('input[name="mcp_servers[]"]');
      const inconsistencies = [];
      
      checkboxes.forEach((cb) => {
        const mcpItem = cb.closest('.mcp-item');
        if (mcpItem) {
          const hasCheckedClass = mcpItem.classList.contains('mcp-checked');
          const isChecked = cb.checked;
          
          if (hasCheckedClass !== isChecked) {
            inconsistencies.push({
              value: cb.value,
              checkboxState: isChecked,
              visualState: hasCheckedClass
            });
          }
        }
      });
      
      return inconsistencies;
    });
    
    expect(visualCheck).toHaveLength(0);
    console.log('Visual consistency check passed');
  });
});