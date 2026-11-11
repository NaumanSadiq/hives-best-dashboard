import {chromium,expect} from '@playwright/test';
import {readFile} from 'node:fs/promises';
import assert from 'node:assert/strict';
const credentials=JSON.parse(await readFile('storage/qa-login.json','utf8'));
const browser=await chromium.launch({channel:'chrome',headless:true});
const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
const base='http://127.0.0.1:8010',dialog=page.getByRole('dialog');
async function close(){await dialog.getByRole('button',{name:'Close dialog'}).click();await dialog.waitFor({state:'hidden'});}
try{
await page.goto(base+'/login');await page.getByLabel('Admin email').fill(credentials.email);await page.getByLabel('Password',{exact:true}).fill(credentials.password);await page.getByRole('button',{name:/Sign in/}).click();await page.waitForURL(base+'/');
const results=[];
for(const width of [1366,1440,390,320]){
await page.setViewportSize({width,height:768});await page.goto(base+'/orders');await expect(page.locator('.order-list-entry')).toHaveCount(25);
const region=page.getByRole('region',{name:'Order records'});await expect(region).toBeVisible();
await page.waitForFunction(()=>{const el=document.querySelector('.records-scroll');return el?.style.maxHeight;});
assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
if(width>1000)assert(await page.locator('.listing-footer').evaluate(el=>el.getBoundingClientRect().bottom<=innerHeight));
await region.evaluate(el=>el.scrollTop=el.scrollHeight);await expect(page.locator('.order-list-entry').last()).toBeInViewport();
await region.evaluate(el=>el.scrollTop=0);const pencil=page.locator('.order-pencil').first();const label=await pencil.getAttribute('aria-label');await pencil.click();await expect(dialog.locator('#amount-reason')).toBeVisible();await expect(dialog.locator('.payment-history')).toHaveCount(0);await close();
await page.locator('.order-details-hit').first().click();await expect(dialog.getByRole('heading',{name:'Batch allocation'})).toBeVisible();await close();
await page.screenshot({path:`storage/app/qa/density-orders-${width}.png`});
await page.locator('[dusk=nextPage]:visible').click();await expect(page.locator('.order-list-entry')).toHaveCount(3);
await page.goto(base+'/customers');const customers=page.getByRole('region',{name:'Customer records'});await expect(customers).toBeVisible();assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.screenshot({path:`storage/app/qa/density-customers-${width}.png`});
await page.locator('#customer-list-search').fill('Buyer 0');await expect(page.locator('.customer-row')).toHaveCount(1,{timeout:20000});await page.locator('.customer-row').getByRole('button',{name:/^View /}).click();await expect(dialog.locator('.order-pencil')).toHaveCount(1);await dialog.locator('.order-pencil').click();await expect(dialog.locator('#amount-reason')).toBeVisible();await close();
await page.goto(base+'/?tab=charts');await expect(page.locator('#pane-charts')).toBeVisible();await expect(page.locator('#pane-charts .order-list-entry')).toHaveCount(5);await page.locator('#pane-charts .order-pencil').first().click();await expect(dialog.locator('#amount-reason')).toBeVisible();await close();await page.getByRole('tab',{name:'Overview',exact:true}).click();await expect(page.locator('#pane-overview')).toBeVisible();await page.screenshot({path:`storage/app/qa/density-dashboard-${width}.png`});await page.goto(base+'/stock');assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));results.push({width,pencil:label});
}
await page.setViewportSize({width:1366,height:768});await page.goto(base+'/orders');
await page.locator('#date-from').click();await expect(page.locator('.flatpickr-calendar.open')).toBeVisible();await page.keyboard.press('Escape');await page.locator('#date-from').fill('2026-11-01');await page.locator('#date-to').fill('2026-11-01');await page.locator('#date-to').press('Tab');await page.getByRole('button',{name:'Apply date range'}).click();await expect(page.locator('.order-list-entry')).toHaveCount(25);
const filter=page.getByRole('combobox',{name:'Filter fulfilment'});await filter.locator('..').click();await page.locator('.ts-dropdown:visible .option').filter({hasText:/^Delivered$/}).click();await expect(page.locator('.order-list-entry')).toHaveCount(6);await page.getByRole('button',{name:'Reset filters',exact:true}).first().click();await expect(page.locator('.order-list-entry')).toHaveCount(25);await page.getByRole('region',{name:'Order records'}).focus();await page.keyboard.press('End');
assert.deepEqual(errors,[]);console.log(JSON.stringify({result:'passed',results,filters:true,datePicker:true,errors}));
}catch(e){await page.screenshot({path:'storage/app/qa/density-failure.png'});throw e;}finally{await browser.close();}

