// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
  testDir: './tests',
  /* Run tests in files in parallel */
  fullyParallel: true,
  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,
  /* Retry on CI only */
  retries: process.env.CI ? 2 : 0,
  /* Opt out of parallel tests on CI. */
  workers: process.env.CI ? 1 : undefined,
  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: [
    ['html', { outputFolder: 'test-results/html-report' }],
    ['json', { outputFile: 'test-results/test-results.json' }],
    ['junit', { outputFile: 'test-results/junit.xml' }]
  ],
  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    /* Base URL to use in actions like `await page.goto('/')`. */
    baseURL: 'https://forexsignale.trade/staging',
    
    /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
    trace: 'on-first-retry',
    
    /* Screenshot on failure */
    screenshot: 'only-on-failure',
    
    /* Video recording on failure */
    video: 'retain-on-failure',
    
    /* Timeout for each action */
    actionTimeout: 15000,
    
    /* Navigation timeout */
    navigationTimeout: 30000,
    
    /* Ignore HTTPS errors for staging environment */
    ignoreHTTPSErrors: true,
    
    /* Accept downloads */
    acceptDownloads: true,
    
    /* Viewport size */
    viewport: { width: 1280, height: 720 },
    
    /* User agent */
    userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36'
  },

  /* Configure projects for major browsers */
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },

    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },

    {
      name: 'webkit',
      use: { ...devices['Desktop Safari'] },
    },

    /* Test against mobile viewports. */
    {
      name: 'Mobile Chrome',
      use: { ...devices['Pixel 5'] },
    },
    {
      name: 'Mobile Safari',
      use: { ...devices['iPhone 12'] },
    },

    /* Test against branded browsers. */
    {
      name: 'Microsoft Edge',
      use: { ...devices['Desktop Edge'], channel: 'msedge' },
    },
    {
      name: 'Google Chrome',
      use: { ...devices['Desktop Chrome'], channel: 'chrome' },
    },
  ],

  /* Run your local dev server before starting the tests */
  webServer: undefined, // We're testing against live staging server

  /* Global timeout for each test */
  timeout: 60000,

  /* Expected conditions timeout */
  expect: {
    /* Maximum time expect() should wait for the condition to be met. */
    timeout: 10000
  },

  /* Folders to ignore */
  testIgnore: [
    'node_modules/**',
    'test-results/**',
    'playwright-report/**'
  ],

  /* Output directory for test artifacts */
  outputDir: 'test-results/artifacts',

  /* Global setup and teardown */
  globalSetup: undefined,
  globalTeardown: undefined,

  /* Configure test metadata */
  metadata: {
    'test-suite': 'MCP Checkbox Functionality Tests',
    'project': 'WordPress TODO Plugin',
    'environment': 'staging',
    'target-url': 'https://forexsignale.trade/staging'
  }
});