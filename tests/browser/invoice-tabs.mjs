import {chromium,expect} from '@playwright/test';
import {readFile} from 'node:fs/promises';
import {spawn} from 'node:child_process';
import assert from 'node:assert/strict';
const fixture=JSON.parse(await readFile('storage/app/qa/controls-fixture.json','utf8'));
const state=await new Promise((resolve,reject)=>{const proc=spawn('php',['tests/browser/controls-fixture.php','inspect'],{windowsHide:true});let output='';proc.stdout.on('data',chunk=>output+=chunk);proc.on('exit',code=>code?reject(Error(output)):resolve(JSON.parse(output)));});
assert.equal(state.database,fixture.database);assert.match(state.database,/^hives_best_controls_qa_\d+$/);
const credentials=JSON.parse(await readFile('storage/qa-login.json','utf8')),base='http://127.0.0.1:8010';
const browser=await chromium.launch({channel:'chrome',headless:true});const context=await browser.newContext(),page=await context.newPage();
try{
await page.goto(base+'/login');await page.getByLabel('Admin email').fill(credentials.email);await page.getByLabel('Password',{exact:true}).fill(credentials.password);await page.getByRole('button',{name:/Sign in/}).click();await page.waitForURL(base+'/');
await page.goto(base+'/orders?q=Repeat');const original=page.url();await page.locator('.listing-name').first().click();const modal=page.locator('.modal');await expect(modal.getByRole('button',{name:'Issue & View Invoice',exact:false})).toBeVisible();
const opened=context.waitForEvent('page');await modal.getByRole('button',{name:'Issue & View Invoice',exact:false}).click();const invoice=await opened;await invoice.waitForURL(base+'/invoices/**');await expect(invoice.locator('#download-invoice')).toHaveAttribute('href',/^blob:/,{timeout:30000});assert.equal(page.url(),original);await expect(modal).toBeVisible();await invoice.close();
console.log(JSON.stringify({result:'passed',isolatedDatabase:state.database,initialIssueInNewTab:true,originalFiltersAndDialogRetained:true}));
}finally{await browser.close();}
