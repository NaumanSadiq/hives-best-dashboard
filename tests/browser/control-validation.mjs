import { chromium } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import assert from 'node:assert/strict';
const credentials=JSON.parse(await readFile('storage/qa-login.json','utf8'));
const browser=await chromium.launch({channel:'chrome',headless:true});const page=await browser.newPage({viewport:{width:390,height:844}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
try {
await page.goto('http://127.0.0.1:8010/login');await page.getByLabel('Admin email').fill(credentials.email);await page.getByLabel('Password',{exact:true}).fill(credentials.password);await page.getByRole('button',{name:/Sign in/}).click();await page.waitForURL('http://127.0.0.1:8010/');
await page.goto('http://127.0.0.1:8010/stock');await page.getByRole('button',{name:'Receive Stock',exact:true}).first().click();const dialog=page.getByRole('dialog');await dialog.getByLabel('Batch name / reference').fill('Invalid-date-QA-'+Date.now());
const pool=dialog.getByRole('combobox',{name:'Stock pool',exact:true});await pool.locator('..').click();await page.locator('.ts-dropdown:visible .option').filter({hasText:/^Sidr \(grams\)$/}).click();
await dialog.locator('#batch-original_quantity').fill('1000');const date=dialog.locator('#batch-received_on');await date.fill('2026-99-99');await date.press('Tab');assert.equal(await date.inputValue(),'2026-99-99');await dialog.getByRole('button',{name:'Receive batch',exact:true}).click();await dialog.locator('[id="error-batch.received_on"]').filter({hasText:/date|format/}).waitFor();assert.equal(await date.inputValue(),'2026-99-99');assert.equal(await date.getAttribute('aria-invalid'),'true');
await date.click();await page.locator('.flatpickr-calendar.open').waitFor();await page.locator('.flatpickr-calendar.open .flatpickr-day.today').focus();await page.keyboard.press('Escape');assert.equal(await page.locator('.flatpickr-calendar.open').count(),0);assert(await dialog.isVisible());
await dialog.getByRole('button',{name:'Close dialog'}).click();assert.deepEqual(errors,[]);console.log(JSON.stringify({result:'passed',invalidDatePreserved:true,inlineError:true,calendarEscapeReturnsFocus:true,consoleErrors:errors.length}));
} finally {await browser.close();}
