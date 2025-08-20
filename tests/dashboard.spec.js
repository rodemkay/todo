// tests/dashboard.spec.js
const { test, expect } = require('@playwright/test');

const DASHBOARD_URL = 'https://forexsignale.trade/staging/wp-admin/admin.php?page=wp-project-todos';
const WP_USER = 'ForexSignale';
const WP_PASS = '.Foret333doka?';

test.describe('TODO Dashboard', () => {
  
  test.beforeEach(async ({ page }) => {
    // Login to WordPress
    await page.goto('https://forexsignale.trade/staging/wp-login.php');
    await page.fill('#user_login', WP_USER);
    await page.fill('#user_pass', WP_PASS);
    await page.click('#wp-submit');
    await page.waitForURL('**/wp-admin/**');
  });

  test('Dashboard renders all filter buttons', async ({ page }) => {
    await page.goto(DASHBOARD_URL);
    
    // Check all filter buttons exist
    await expect(page.locator('.wsj-filter-btn').filter({ hasText: 'Alle' })).toBeVisible();
    await expect(page.locator('.wsj-filter-btn').filter({ hasText: 'Offen' })).toBeVisible();
    await expect(page.locator('.wsj-filter-btn').filter({ hasText: 'In Bearbeitung' })).toBeVisible();
    await expect(page.locator('.wsj-filter-btn').filter({ hasText: 'Abgeschlossen' })).toBeVisible();
    await expect(page.locator('.wsj-filter-btn').filter({ hasText: 'Blockiert' })).toBeVisible();
    await expect(page.locator('.wsj-filter-btn').filter({ hasText: '⏰ CRON' })).toBeVisible();
    
    // Take screenshot
    await page.screenshot({ path: 'tests/screenshots/dashboard-filters.png' });
  });

  test('Claude Toggle appears in each task row', async ({ page }) => {
    await page.goto(DASHBOARD_URL);
    
    // Wait for table to load
    await page.waitForSelector('.todo-table tbody tr');
    
    // Check if first row has Claude toggle
    const firstRow = page.locator('.todo-table tbody tr').first();
    const claudeToggle = firstRow.locator('.claude-toggle-btn');
    
    // Claude toggle should exist and show either ❌ or ✓
    await expect(claudeToggle).toBeVisible();
    const toggleText = await claudeToggle.textContent();
    expect(toggleText).toMatch(/[❌✓] Claude/);
    
    // Take screenshot
    await page.screenshot({ path: 'tests/screenshots/dashboard-claude-toggle.png' });
  });

  test('Can toggle Claude status for individual task', async ({ page }) => {
    await page.goto(DASHBOARD_URL);
    
    // Wait for table
    await page.waitForSelector('.todo-table tbody tr');
    
    // Get first task's Claude toggle
    const firstRow = page.locator('.todo-table tbody tr').first();
    const claudeToggle = firstRow.locator('.claude-toggle-btn');
    
    // Get initial state
    const initialText = await claudeToggle.textContent();
    const isInitiallyEnabled = initialText.includes('✓');
    
    // Click toggle
    await claudeToggle.click();
    
    // Wait for AJAX to complete
    await page.waitForTimeout(1000);
    
    // Check state changed
    const newText = await claudeToggle.textContent();
    if (isInitiallyEnabled) {
      expect(newText).toContain('❌');
    } else {
      expect(newText).toContain('✓');
    }
  });

  test('Bulk actions dropdown is visible', async ({ page }) => {
    await page.goto(DASHBOARD_URL);
    
    // Check bulk actions
    await expect(page.locator('#bulk-action-selector')).toBeVisible();
    
    // Check "Neue Aufgabe" button
    await expect(page.locator('.button-primary').filter({ hasText: 'Neue Aufgabe' })).toBeVisible();
  });

  test('Filter buttons work correctly', async ({ page }) => {
    await page.goto(DASHBOARD_URL);
    
    // Click "Offen" filter
    await page.locator('.wsj-filter-btn').filter({ hasText: 'Offen' }).click();
    await page.waitForURL('**/wp-admin/admin.php?page=wp-project-todos&filter_status=offen**');
    
    // Check active state
    await expect(page.locator('.wsj-filter-btn.active').filter({ hasText: 'Offen' })).toBeVisible();
    
    // Click "In Bearbeitung" filter
    await page.locator('.wsj-filter-btn').filter({ hasText: 'In Bearbeitung' }).click();
    await page.waitForURL('**/wp-admin/admin.php?page=wp-project-todos&filter_status=in_progress**');
    
    // Check active state changed
    await expect(page.locator('.wsj-filter-btn.active').filter({ hasText: 'In Bearbeitung' })).toBeVisible();
  });

  test('Table displays task information correctly', async ({ page }) => {
    await page.goto(DASHBOARD_URL);
    
    // Wait for table
    await page.waitForSelector('.todo-table');
    
    // Check table headers
    await expect(page.locator('th').filter({ hasText: 'TITEL / BESCHREIBUNG' })).toBeVisible();
    await expect(page.locator('th').filter({ hasText: 'STATUS / PRIORITÄT' })).toBeVisible();
    await expect(page.locator('th').filter({ hasText: 'CLAUDE / ANHÄNGE' })).toBeVisible();
    await expect(page.locator('th').filter({ hasText: 'ERSTELLT / GEÄNDERT' })).toBeVisible();
    await expect(page.locator('th').filter({ hasText: 'VERZEICHNIS / AKTIONEN' })).toBeVisible();
    
    // Take full page screenshot
    await page.screenshot({ path: 'tests/screenshots/dashboard-full.png', fullPage: true });
  });
});

test.describe('New Task Page', () => {
  
  test.beforeEach(async ({ page }) => {
    // Login
    await page.goto('https://forexsignale.trade/staging/wp-login.php');
    await page.fill('#user_login', WP_USER);
    await page.fill('#user_pass', WP_PASS);
    await page.click('#wp-submit');
    await page.waitForURL('**/wp-admin/**');
  });

  test('New task page has all required fields', async ({ page }) => {
    await page.goto('https://forexsignale.trade/staging/wp-admin/admin.php?page=wp-project-todos&action=new');
    
    // Check title field
    await expect(page.locator('input[name="title"]')).toBeVisible();
    
    // Check description field
    await expect(page.locator('textarea[name="description"]')).toBeVisible();
    
    // Check status buttons
    await expect(page.locator('input[value="offen"]')).toBeVisible();
    await expect(page.locator('input[value="in_progress"]')).toBeVisible();
    await expect(page.locator('input[value="completed"]')).toBeVisible();
    await expect(page.locator('input[value="blocked"]')).toBeVisible();
    
    // Check priority buttons
    await expect(page.locator('input[value="niedrig"]')).toBeVisible();
    await expect(page.locator('input[value="mittel"]')).toBeVisible();
    await expect(page.locator('input[value="hoch"]')).toBeVisible();
    await expect(page.locator('input[value="kritisch"]')).toBeVisible();
    
    // Check working directory dropdown
    await expect(page.locator('select[name="arbeitsverzeichnis"]')).toBeVisible();
    
    // Check Claude checkbox
    await expect(page.locator('input[name="bearbeiten"]')).toBeVisible();
    
    // Take screenshot
    await page.screenshot({ path: 'tests/screenshots/new-task-page.png', fullPage: true });
  });

  test('Can save task without redirect', async ({ page }) => {
    await page.goto('https://forexsignale.trade/staging/wp-admin/admin.php?page=wp-project-todos&action=new');
    
    // Fill form
    await page.fill('input[name="title"]', 'Test Task - ' + Date.now());
    await page.fill('textarea[name="description"]', 'This is a test task created by Playwright');
    
    // Select status
    await page.check('input[value="offen"]');
    
    // Select priority
    await page.check('input[value="mittel"]');
    
    // Check "Nur Speichern (ohne Redirect)" button exists
    const saveWithoutRedirectBtn = page.locator('button').filter({ hasText: 'Nur Speichern (ohne Redirect)' });
    await expect(saveWithoutRedirectBtn).toBeVisible();
    
    // Click save without redirect
    await saveWithoutRedirectBtn.click();
    
    // Should stay on same page
    await page.waitForTimeout(2000);
    expect(page.url()).toContain('action=new');
    
    // Check for success message
    await expect(page.locator('.notice-success')).toBeVisible();
  });

  test('MCP Server checkboxes are visible', async ({ page }) => {
    await page.goto('https://forexsignale.trade/staging/wp-admin/admin.php?page=wp-project-todos&action=new');
    
    // Scroll to MCP section
    await page.evaluate(() => {
      const mcpSection = document.querySelector('.mcp-server-integration');
      if (mcpSection) mcpSection.scrollIntoView();
    });
    
    // Check MCP server options
    await expect(page.locator('label').filter({ hasText: 'Context7 MCP' })).toBeVisible();
    await expect(page.locator('label').filter({ hasText: 'Playwright MCP' })).toBeVisible();
    
    // Take screenshot of MCP section
    await page.screenshot({ path: 'tests/screenshots/new-task-mcp.png' });
  });
});