import {chromium, expect} from '@playwright/test';
import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';

const base = 'https://hives-best-dashboard.local';
const out = 'storage/app/qa/storefront-collection';
await mkdir(out,{recursive:true});
const browser = await chromium.launch({channel:'chrome'});
const errors = [];
try {
    for (const width of [1440,768,390,320]) {
        const mobile = width < 700;
        const context = await browser.newContext({viewport:{width,height:mobile?844:900},hasTouch:mobile});
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        await page.goto(base);
        await page.evaluate(()=>document.fonts.ready);
        for (const id of ['ghee','1kg','ghee','1kg']) {
            await page.locator('[data-choice="'+id+'"]').click();
        }
        await expect(page.locator('.product-stage')).toHaveAttribute('data-exchange','settled');
        await expect(page.locator('.featured-meta a')).toHaveAttribute('href','#product-1kg');
        await page.locator('[data-choice="1kg"]').focus();
        await page.keyboard.press('End');
        await expect(page.locator('.featured-meta a')).toHaveAttribute('href','#product-ghee');
        await page.keyboard.press('Home');
        await expect(page.locator('.featured-meta a')).toHaveAttribute('href','#product-1kg');
        if (mobile) await page.getByRole('button',{name:'Open navigation'}).click();
        await page.getByRole('link',{name:'Our products',exact:true}).click();
        await expect(page).toHaveURL(/#products$/);
        await expect(page.locator('.product-price')).toHaveText(['PKR 2,100','PKR 4,100','PKR 5,000']);
        await expect(page.locator('#products a, #story, .hero-copy a')).toHaveCount(0);
        assert(await page.locator('a[href^="#"]').evaluateAll(links=>links.every(a=>document.getElementById(a.hash.slice(1)))));
        assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
        const prices=await page.locator('.product-price').evaluateAll(nodes=>nodes.map(n=>{const r=document.createRange();r.selectNodeContents(n);return {lines:new Set([...r.getClientRects()].map(rect=>rect.top)).size,top:n.getBoundingClientRect().top};}));
        assert(prices.every(p=>p.lines===1));
        if(!mobile) assert(prices.every(p=>Math.abs(p.top-prices[0].top)<1));
        for(const jar of await page.locator('.jar-preview').all()){
            await jar.scrollIntoViewIfNeeded();
            const height = await jar.evaluate(e=>e.closest('article').offsetHeight);
            if(mobile){
                await jar.tap();
            }else{
                await jar.hover();
                await page.waitForTimeout(250);
                await page.mouse.move(0,0);
                await page.waitForTimeout(65);
                assert(await jar.evaluate(e=>e.querySelector('.photo-stack').getAnimations()[0].playbackRate<0));
                await jar.hover();
            }
            await expect(jar).toHaveAttribute('aria-pressed','true');
            await page.waitForTimeout(860);
            assert(await jar.evaluate(e=>{
                const stack=e.querySelector('.photo-stack');
                const lid=e.querySelector('.jar-cap').getBoundingClientRect();
                return Math.abs(new DOMMatrix(getComputedStyle(stack).transform).a-1.06)<.001 && lid.top>=e.getBoundingClientRect().top;
            }));
            assert.equal(await jar.evaluate(e=>e.closest('article').offsetHeight),height);
            if(mobile) await jar.tap(); else await page.mouse.move(0,0);
            await expect(jar).toHaveAttribute('aria-pressed','false');
        }
        const first=page.locator('.jar-preview').first();
        if(mobile){
            await first.scrollIntoViewIfNeeded();
            const box=await first.boundingBox(), x=box.x+box.width/2, y=box.y+box.height*.8;
            const start=await page.evaluate(()=>scrollY);
            const session=await context.newCDPSession(page);
            await session.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[{x,y}]});
            for(let i=1;i<=8;i++)await session.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{x,y:y-i*14}]});
            await session.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
            await expect.poll(()=>page.evaluate(()=>scrollY)).toBeGreaterThan(start+30);
            await expect(first).toHaveAttribute('aria-pressed','false');
        }
        await page.emulateMedia({reducedMotion:'reduce'});
        await page.keyboard.press('Tab');
        await first.focus();
        await expect(first).toHaveAttribute('aria-pressed','true');
        assert(await first.evaluate(e=>e.getAnimations({subtree:true}).every(a=>a.playState==='paused' && a.currentTime===820)));
        await page.keyboard.press('Tab');
        await expect(first).toHaveAttribute('aria-pressed','false');
        await page.locator('#faq-question-0').click();
        await expect(page.locator('#faq-answer-0')).toHaveAttribute('aria-hidden','false');
        await page.locator('#faq-question-0').click();
        await expect(page.locator('img[src$="campaign.webp"]')).toHaveCount(1);
        await page.locator('.faq-art img').evaluate(i=>i.decode());
        await page.evaluate(()=>{document.activeElement.blur();scrollTo({top:0,behavior:'instant'});});
        await page.screenshot({path:out+'/page-'+width+'.png',fullPage:true});
        await page.locator('#products').scrollIntoViewIfNeeded();
        await page.screenshot({path:out+'/collection-'+width+'.png'});
        await page.goto(base+'/admin/login');
        await expect(page.getByLabel('Admin email')).toBeVisible();
        await expect(page.locator('.golden-page')).toHaveCount(0);
        console.log('Passed',width);
        await context.close();
    }
    const size={width:1440,height:900};
    const context=await browser.newContext({viewport:size,recordVideo:{dir:out,size}});
    const page=await context.newPage();
    await page.goto(base+'/#products');
    await page.evaluate(()=>document.fonts.ready);
    for(const id of ['500g','ghee']){
        await page.locator('#product-'+id+' .jar-preview').hover();
        await page.waitForTimeout(1250);
        await page.mouse.move(5,10);
        await page.waitForTimeout(1100);
    }
    const video=page.video();
    await context.close();
    await video.saveAs(out+'/honey-and-ghee.webm');
    assert.deepEqual(errors,[]);
}finally{await browser.close();}
