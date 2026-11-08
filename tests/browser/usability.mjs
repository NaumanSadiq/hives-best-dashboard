import { chromium } from '@playwright/test';
import { readFile, mkdir } from 'node:fs/promises';
import { spawn } from 'node:child_process';
import assert from 'node:assert/strict';
const base='http://127.0.0.1:8010';
async function fixture(...args) { return new Promise((resolve,reject)=>{const p=spawn('php',['tests/browser/usability-fixture.php',...args],{windowsHide:true});let out='',err='';p.stdout.on('data',d=>out+=d);p.stderr.on('data',d=>err+=d);p.on('close',code=>code?reject(new Error(err||out)):resolve(JSON.parse(out)));}); }
const data=await fixture(); const credentials=JSON.parse(await readFile('storage/qa-login.json','utf8'));
await mkdir('storage/app/qa',{recursive:true});
const browser=await chromium.launch({channel:process.env.QA_BROWSER||'chrome',headless:true});
const context=await browser.newContext({viewport:{width:1440,height:1050}});const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
async function select(label,text,p=page) {const control=p.getByRole('combobox',{name:label,exact:true});await control.locator('..').click();await p.locator('.ts-dropdown:visible .option').filter({hasText:text}).first().click();}
async function saved() {await page.getByRole('dialog').waitFor({state:'hidden'});}
async function stockRow(p,reference) {
    await p.goto(base+'/stock');
    for(let i=0;i<12;i++) {const row=p.getByRole('row').filter({has:p.getByText(reference,{exact:true})});if(await row.count())return row;
        const next=p.locator('.pagination button').filter({hasText:'›'});if(!await next.count())throw new Error('Batch not found: '+reference);
        await Promise.all([p.waitForResponse(r=>r.url().includes('/livewire')&&r.request().method()==='POST'),next.last().click()]);
    }throw new Error('Batch pagination limit');
}
async function checkWidth(p,label){const [scroll,width]=await p.evaluate(()=>[document.documentElement.scrollWidth,innerWidth]);assert(scroll<=width,label+' overflow '+scroll+'/'+width);}
try {
await page.goto(base+'/login');const favicon=page.locator('link[rel="icon"]');assert((await favicon.getAttribute('href')).endsWith('/favicon.svg'));assert.equal((await page.request.get(await favicon.getAttribute('href'))).status(),200);
await page.getByLabel('Admin email').fill(credentials.email);await page.getByLabel('Password',{exact:true}).fill(credentials.password);await page.getByRole('button',{name:/Sign in/}).click();await page.waitForURL(base+'/');
await page.getByRole('heading',{name:'Business reconciliation'}).waitFor();
await page.screenshot({path:'storage/app/qa/usability-desktop-hero.png',fullPage:true});
// Edit an unused batch, including validation and date/select persistence.
let row=await stockRow(page,data.unusedRef);await row.getByRole('button',{name:'Edit Batch',exact:true}).click();let dialog=page.getByRole('dialog');await dialog.locator('#batch-original_quantity').fill('1200');await dialog.locator('#batch-purchase').fill('1250');
await dialog.locator('#batch-received_on').click();await page.locator('.flatpickr-calendar.open').waitFor();await page.screenshot({path:'storage/app/qa/usability-calendar-modal.png'});await page.locator('.flatpickr-calendar.open').getByRole('button',{name:'Today',exact:true}).click();assert.equal(await dialog.locator('#batch-received_on').inputValue(),data.today);
await dialog.locator('#batch-received_on').click();await page.locator('.flatpickr-calendar.open').waitFor();assert(await page.locator('.flatpickr-calendar.open .flatpickr-day.flatpickr-disabled').count()>0);await dialog.getByRole('heading',{name:'Edit Batch',exact:true}).click();assert.equal(await page.locator('.flatpickr-calendar.open').count(),0);
await dialog.getByRole('combobox',{name:'Stock pool',exact:true}).locator('..').click();await page.locator('.ts-dropdown:visible').waitFor();await page.screenshot({path:'storage/app/qa/usability-dropdown-modal.png'});await page.keyboard.press('Escape');assert(await dialog.isVisible());
await dialog.getByRole('combobox',{name:'Stock pool',exact:true}).locator('..').click();await page.locator('.ts-dropdown:visible').waitFor();await dialog.getByRole('heading',{name:'Edit Batch',exact:true}).click();assert.equal(await page.locator('.ts-dropdown:visible').count(),0);
await dialog.locator('#batch-reference').fill(data.batchRef);await dialog.locator('#batch-reason').fill('Correct supplier details');await dialog.getByRole('button',{name:'Preview correction',exact:true}).click();await dialog.locator('[id="error-batch.reference"]').filter({hasText:/taken/}).waitFor();assert.equal(await dialog.locator('#batch-original_quantity').inputValue(),'1200');assert.equal(await dialog.locator('#batch-received_on').inputValue(),data.today);
await dialog.locator('#batch-received_on').click();await page.locator('.flatpickr-calendar.open').waitFor();await page.keyboard.press('Escape');assert(await dialog.isVisible());
await dialog.locator('#batch-reference').fill(data.unusedRef+'-EDITED');await dialog.getByRole('button',{name:'Preview correction',exact:true}).click();await dialog.getByRole('button',{name:'Confirm correction'}).click();await saved();
row=await stockRow(page,data.unusedRef+'-EDITED');assert((await row.innerText()).includes('1,200 g'));assert((await row.innerText()).includes('PKR 1,250.00'));
// Used batches expose only the safe fields.
row=await stockRow(page,data.batchRef);await row.getByRole('button',{name:'Edit Batch',exact:true}).click();await dialog.waitFor();assert(await dialog.locator('#batch-original_quantity').isEnabled());assert(await dialog.locator('#batch-purchase').isEnabled());assert(await dialog.locator('#batch-received_on').isEnabled());await dialog.getByRole('button',{name:'Close dialog'}).click();await saved();
// All dates includes the October order. Edit it to 1,000 g, then cancel.
await page.goto(base+'/orders');await page.getByRole('button').filter({hasText:data.number}).click();await dialog.getByRole('button',{name:'Edit items / customer',exact:true}).click();await dialog.locator('#quantity-0').fill('2');await dialog.getByRole('button',{name:'Save & reserve stock'}).click();await dialog.getByRole('heading',{name:'Batch allocation'}).waitFor();
assert.equal((await fixture('inspect',String(data.batch))).reserved,1000);
const stock=await context.newPage();row=await stockRow(stock,data.batchRef);assert((await row.innerText()).includes('9,000 g'));
page.once('dialog',d=>d.accept());await dialog.getByRole('button',{name:'Cancel order',exact:true}).click();await dialog.locator('.status-cancelled').waitFor();
await stock.waitForFunction(ref=>[...document.querySelectorAll('tbody tr')].find(tr=>tr.querySelector('strong')?.textContent===ref)?.children[5]?.textContent.includes('10,000 g'),data.batchRef);
const state=await fixture('inspect',String(data.batch));assert.equal(state.reserved,0);assert.equal(state.physical,10000);
await dialog.getByRole('button',{name:'Close dialog'}).click();await saved();await select('Filter fulfilment','Cancelled');await page.getByRole('button').filter({hasText:data.number}).waitFor();
await page.getByLabel('Search orders by number or customer').fill(data.number);await page.getByRole('button').filter({hasText:data.number}).waitFor();
await page.getByLabel('From',{exact:true}).fill(data.today);await page.getByLabel('From',{exact:true}).press('Tab');await page.getByRole('button',{name:'Apply date range'}).click();await page.getByText('No matching orders',{exact:true}).waitFor();await page.getByRole('button',{name:'Reset filters',exact:true}).first().click();await page.getByRole('button').filter({hasText:data.number}).waitFor();assert.equal(await page.getByLabel('From',{exact:true}).inputValue(),'');
// A separate valid reservation is kept intact.
await fixture('active',String(data.product));await stock.goto(base+'/stock');const afterActive=await fixture('inspect',String(data.batch));assert.equal(afterActive.reserved,1500);
// Keyboard controls, mobile modal placement, date sync and no overflow.
for(const width of [390,320]) {await page.setViewportSize({width,height:844});await page.goto(base+'/');await checkWidth(page,'Dashboard '+width);
await page.screenshot({path:'storage/app/qa/usability-mobile-'+width+'.png',fullPage:true});
await page.goto(base+'/stock');await page.getByRole('button',{name:'Receive Stock',exact:true}).first().click();await dialog.waitFor();const pool=dialog.getByRole('combobox',{name:'Stock pool',exact:true});await pool.focus();await pool.press('ArrowDown');await page.locator('.ts-dropdown:visible').waitFor();await pool.press('ArrowDown');await pool.press('Enter');await dialog.locator('#batch-received_on').click();await page.locator('.flatpickr-calendar.open').waitFor();await checkWidth(page,'Calendar '+width);const box=await page.locator('.flatpickr-calendar.open').boundingBox();assert(box.x>=0&&box.x+box.width<=width+1);await page.screenshot({path:'storage/app/qa/usability-mobile-calendar-'+width+'.png'});await page.keyboard.press('Escape');assert(await dialog.isVisible());await dialog.getByRole('button',{name:'Close dialog'}).click();await saved();}
assert.deepEqual(errors,[]);console.log(JSON.stringify({result:'passed',browser:process.env.QA_BROWSER||'chrome',editThenCancel:{physical:state.physical,reserved:state.reserved,available:state.physical-state.reserved},separateActiveReservation:afterActive.reserved,unusedBatchEdits:true,usedBasisEditable:true,themedControls:true,filterResetSync:true,dashboardAndFavicon:true,widths:[320,390,1440],consoleErrors:errors.length}));
}catch(error){await page.screenshot({path:'storage/app/qa/usability-failure.png',fullPage:true});console.error(JSON.stringify({errors,url:page.url()}));throw error;}finally{await browser.close();}
