const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    
    // Login
    await page.goto('https://forexsignale.trade/staging/wp-login.php');
    await page.fill('input[name="log"]', 'ForexSignale');
    await page.fill('input[name="pwd"]', '.Foret333doka?');
    await page.click('input[type="submit"]');
    
    // Wait for dashboard
    await page.waitForSelector('.wp-admin', { timeout: 10000 });
    
    // Navigate to TODO page
    await page.goto('https://forexsignale.trade/staging/wp-admin/admin.php?page=todo');
    await page.waitForSelector('.wsj-dashboard-table', { timeout: 10000 });
    
    // Check for Upload button
    const uploadButtons = await page.$$eval('button', buttons => 
        buttons.filter(btn => btn.textContent.includes('Upload') || btn.textContent.includes('📤'))
            .map(btn => ({
                text: btn.textContent.trim(),
                visible: btn.offsetParent !== null,
                onclick: btn.getAttribute('onclick')
            }))
    );
    
    console.log('Upload Buttons found:', uploadButtons.length);
    uploadButtons.forEach((btn, i) => {
        console.log(`Button ${i+1}:`, btn);
    });
    
    // Take screenshot
    await page.screenshot({ 
        path: '/home/rodemkay/www/react/plugin-todo/upload-button-test.png',
        fullPage: false 
    });
    
    await browser.close();
    
    console.log('\n✅ Test complete! Check upload-button-test.png');
})().catch(console.error);