import {chromium} from '@playwright/test';
const browser=await chromium.launch({channel:'chrome',headless:true});
try{for(const origin of ['http://hives-best-dashboard.local','https://hives-best-dashboard.local']){
const page=await browser.newPage();let result;
try{const response=await page.goto(origin+'/login');result=await page.evaluate(async()=>({origin:location.origin,secure:window.isSecureContext,clipboardWrite:typeof navigator.clipboard?.write,clipboardItem:typeof window.ClipboardItem,pngSupported:window.ClipboardItem?.supports?.('image/png')??null,permission:await navigator.permissions.query({name:'clipboard-write'}).then(p=>p.state).catch(e=>e.name),title:document.title}));result.status=response.status();}catch(e){result={origin,error:e.message};}
console.log(JSON.stringify(result));await page.close();
}}finally{await browser.close();}
