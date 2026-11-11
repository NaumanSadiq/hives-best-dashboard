import {chromium,expect} from '@playwright/test';
import {readFile} from 'node:fs/promises';
import assert from 'node:assert/strict';
const credentials=JSON.parse(await readFile('storage/qa-login.json','utf8'));
const browser=await chromium.launch({channel:'chrome',headless:true});
const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];
page.on('pageerror',e=>errors.push(e.message));
const base='http://127.0.0.1:8010',dialog=page.getByRole('dialog');
const close=async()=>{await dialog.getByRole('button',{name:'Close dialog'}).click();await dialog.waitFor({state:'hidden'});};
try{
await page.goto(base+'/login');await page.getByLabel('Admin email').fill(credentials.email);await page.getByLabel('Password',{exact:true}).fill(credentials.password);await page.getByRole('button',{name:/Sign in/}).click();await page.waitForURL(base+'/');
await expect(page.getByRole('tab')).toHaveText(['Overview','Charts','Finance','Stock & Batches','Customers']);
await expect(page.locator('#pane-overview .savings-card')).toHaveCount(1);await expect(page.locator('#pane-overview .current-stock-card')).toHaveCount(1);await expect(page.locator('#pane-overview .orders-panel')).toHaveCount(0);
await page.getByRole('button',{name:'Set opening balance'}).click();await dialog.locator('#savings-amount').fill('1000');await dialog.locator('#savings-note').fill('Layout fixture opening');await dialog.getByRole('button',{name:'Preview movement'}).click();await dialog.getByRole('button',{name:'Confirm savings movement'}).click();await close();
for(const action of ['Add Money','Record Purchase','Update Balance','View History']){await page.getByRole('button',{name:action,exact:true}).click();await expect(dialog).toBeVisible();await close();}
await page.getByRole('link',{name:'View Stock',exact:false}).click();await page.waitForURL('**/stock');await page.goto(base+'/');
for(const width of [1440,390,320]){
await page.setViewportSize({width,height:1000});await page.getByRole('tab',{name:'Overview',exact:true}).click();await expect(page.locator('#pane-overview')).toBeVisible();assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
await page.screenshot({path:`storage/app/qa/layout-overview-${width}.png`,fullPage:true});
await page.getByRole('tab',{name:'Charts',exact:true}).click();await expect(page.locator('#pane-charts')).toBeVisible();await expect(page.locator('#pane-charts .order-list-entry')).toHaveCount(5);await expect(page.locator('.bar-chart')).toBeVisible();await expect(page.locator('.donut')).toBeVisible();assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.screenshot({path:`storage/app/qa/layout-charts-${width}.png`,fullPage:true});
await page.locator('#pane-charts .order-pencil').first().click();await expect(dialog.locator('#amount-reason')).toBeVisible();await close();await expect(page.locator('#pane-charts')).toBeVisible();
}
await page.reload();await expect(page.locator('#pane-charts')).toBeVisible();await page.locator('#date-from').fill('2026-11-01');await page.locator('#date-to').fill('2026-11-03');await page.locator('#date-to').press('Tab');await page.getByRole('button',{name:'Apply date range'}).click();await expect(page.locator('#pane-charts')).toBeVisible();await expect(page.getByRole('link',{name:'View All Orders'})).toHaveAttribute('href',/from=2026-11-01.*to=2026-11-03/);
await page.getByRole('tab',{name:'Overview',exact:true}).click();await expect(page.locator('.current-balances')).toContainText('Current balance · all dates');await expect(page.locator('.current-stock-card')).toContainText('All Dates');await page.getByRole('button',{name:'Clear',exact:true}).click();await expect(page.locator('#date-from')).toHaveValue('');
await page.getByRole('tab',{name:'Overview',exact:true}).focus();await page.keyboard.press('ArrowRight');await expect(page.getByRole('tab',{name:'Charts',exact:true})).toHaveAttribute('aria-selected','true');
assert.deepEqual(errors,[]);console.log(JSON.stringify({result:'passed',widths:[1440,390,320],recentOrders:5,savingsActions:true,stockLink:true,dateFilters:true,keyboardTabs:true,errors}));
}finally{await browser.close();}
