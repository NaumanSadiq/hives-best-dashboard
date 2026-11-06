import { spawn } from 'node:child_process';
import assert from 'node:assert/strict';
function run(args) { return new Promise((resolve,reject)=> { const process=spawn('php',['tests/browser/concurrency.php',...args],{windowsHide:true}); let out='',errors=''; process.stdout.on('data',data=>out+=data); process.stderr.on('data',data=>errors+=data); process.on('close',code=>code?reject(new Error(errors||out)):resolve(JSON.parse(out))); }); }
const fixture=await run(['prepare']);
const results=await Promise.all([run(['order',String(fixture.product)]),run(['order',String(fixture.product)])]);
assert.equal(results.filter(r=>r.result==='reserved').length,1);
assert.equal(results.filter(r=>r.result==='insufficient').length,1);
const batch=await run(['inspect',String(fixture.batch)]); assert.equal(batch.reserved,1000); assert.equal(batch.physical,1000);
console.log(JSON.stringify({result:'passed',workers:results.map(r=>r.result),reserved:batch.reserved,physical:batch.physical,database:'hives_best_qa'}));
