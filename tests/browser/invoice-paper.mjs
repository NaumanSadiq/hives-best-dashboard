import { chromium, expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import assert from 'node:assert/strict';

const fixture = JSON.parse(await readFile('storage/app/qa/invoice-paper.json', 'utf8'));
assert.match(fixture.database, /^hives_best_[a-z_]*qa_\d+$/);
const credentials = JSON.parse(await readFile('storage/qa-login.json', 'utf8'));
const browser = await chromium.launch({ channel: 'chrome' });
const base = 'http://127.0.0.1:8010', results = [];
try {
    for (const dpr of [1, 2]) {
        const context = await browser.newContext({ deviceScaleFactor: dpr, viewport: { width: 1366, height: 768 } });
        const page = await context.newPage();
        await page.goto(base + '/login');
        await page.getByLabel('Admin email').fill(credentials.email);
        await page.getByLabel('Password', { exact: true }).fill(credentials.password);
        await page.getByRole('button', { name: /Sign in/ }).click();
        await page.waitForURL(base + '/');
        for (const width of [1366, 1440, 390, 320]) {
            await page.setViewportSize({ width, height: 768 });
            for (const kind of ['short', 'long']) {
                await page.goto(base + '/invoices/' + fixture[kind]);
                await expect(page.locator('#copy-invoice')).toBeEnabled({ timeout: 30000 });
                const result = await page.evaluate(async () => {
                    const sheet = document.querySelector('#invoice-sheet'), frame = sheet.parentElement;
                    const blob = await fetch(document.querySelector('#invoice-preview').src).then(r => r.blob());
                    const bitmap = await createImageBitmap(blob), canvas = document.createElement('canvas');
                    canvas.width = bitmap.width; canvas.height = bitmap.height;
                    const ctx = canvas.getContext('2d'); ctx.drawImage(bitmap, 0, 0);
                    const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
                    const white = (x, y) => { const i = (y * canvas.width + x) * 4; return [0, 1, 2, 3].every(n => pixels[i + n] === 255); };
                    let edgesWhite = true;
                    for (let y = 0; y < canvas.height; y++) edgesWhite &&= white(0, y) && white(canvas.width - 1, y);
                    for (let x = 0; x < canvas.width; x++) edgesWhite &&= white(x, 0) && white(x, canvas.height - 1);
                    const logo = document.querySelector('.invoice-brand img'), lc = document.createElement('canvas');
                    lc.width = logo.naturalWidth; lc.height = logo.naturalHeight; const lctx = lc.getContext('2d'); lctx.drawImage(logo, 0, 0);
                    const logoPixels = lctx.getImageData(0, 0, lc.width, lc.height).data;
                    return { edgesWhite, pngWidth: canvas.width, pngHeight: canvas.height, sheetHeight: sheet.offsetHeight, frameWidth: frame.clientWidth, sheetWidth: sheet.offsetWidth, pageFits: document.documentElement.scrollWidth <= innerWidth, logoTransparent: logoPixels[3] === 0, rows: sheet.querySelectorAll('tbody tr').length };
                });
                assert(result.edgesWhite && result.logoTransparent && result.pageFits);
                assert.equal(result.pngWidth, 1600);
                assert.equal(result.pngHeight, result.sheetHeight * 2);
                assert(result.frameWidth <= result.sheetWidth);
                assert.equal(result.rows, kind === 'long' ? 25 : 1);
                const download = page.waitForEvent('download'); await page.locator('#download-invoice').click();
                const saved = await download; const bytes = await readFile(await saved.path());
                const expected = await page.evaluate(async () => Array.from(new Uint8Array(await fetch(document.querySelector('#invoice-preview').src).then(r => r.arrayBuffer()))));
                assert.deepEqual(bytes, Buffer.from(expected));
                await page.screenshot({ path: `storage/app/qa/paper-${kind}-${width}-${dpr}.png` });
                results.push({ width, dpr, kind, ...result, downloadMatches: true });
            }
        }
        await context.close();
    }
    console.log(JSON.stringify({ result: 'passed', results }));
} finally { await browser.close(); }
