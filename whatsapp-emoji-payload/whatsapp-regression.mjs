import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const path = 'resources/views/partials/_ready-whatsapp-js.blade.php';
const source = fs.readFileSync(path, 'utf8').replace(/^<script>\s*/, '').replace(/<\/script>\s*$/, '');
for (const cp of ['0x1F44B','0x1F389','0x2728','0x1F4E6','0x1F447','0x1F90D']) {
    assert.ok(source.includes(`String.fromCodePoint(${cp})`), 'Emojis must be browser-built');
}
assert.ok(fs.readFileSync('resources/views/layouts/app.blade.php','utf8').includes("@include('partials._ready-whatsapp-js')"));
const handlers = [], opened = [], copied = [], alerts = [], elements = [];
let current, hasInvoice = true, hasProof = true, blocked = false, requests = [];
const makeElement = tag => {
    const e = {tag, children:[], style:{}, events:{}, appendChild(c){this.children.push(c);},
        setAttribute(){}, remove(){}, select(){}, addEventListener(n,f){this.events[n]=f;},
        querySelector(){return this.actions ??= makeElement('actions');}};
    elements.push(e); return e;
};
const win = {open(url,name){
    if(blocked) return null;
    const tab = {closed:false, location:{href:url}, focus(){this.focused=true;}};
    opened.push({url,name,tab}); return tab;
}};
const context = vm.createContext({window:win, document:{
    body:makeElement('body'), createElement:makeElement, execCommand:()=>true,
    querySelector:()=>({content:'csrf'}), addEventListener(n,f,capture){handlers.push({n,f,capture});}},
    navigator:{clipboard:{async writeText(text){copied.push(text);}}},
    fetch:async(url)=>{requests.push(url); return {ok:true,json:async()=>url==='/check'
        ? {has_invoice:hasInvoice,has_proof:hasProof,generate_invoice_url:'/invoice'}:current};},
    alert:text=>alerts.push(text), console});
vm.runInContext(source,context); vm.runInContext(source,context);
assert.equal(handlers.length,1,'Only one capture handler installed');
assert.equal(handlers[0].capture,true);
async function click(status='ready') {
    const btn={disabled:false,dataset:{checkUrl:'/check',linkUrl:'/link'}};
    const event={stopped:false,target:{closest(selector){
        assert.ok(selector.includes('[data-whatsapp-status="ready"]'));
        assert.ok(selector.includes('[data-whatsapp-status="delivered"]'));
        return ['ready','delivered'].includes(status)?btn:null;
    }},preventDefault(){},stopImmediatePropagation(){this.stopped=true;}};
    await handlers[0].f(event); assert.equal(btn.disabled,false);return event;
}
const chars=[0x1F44B,0x1F389,0x2728,0x1F4E6,0x1F447,0x1F90D].map(cp=>String.fromCodePoint(cp));
const samples=['Akbar Sha','Customer B & Sons','عميل C 100%'];
for (const status of ['ready','delivered']) {
for(let i=0;i<samples.length;i++) {
    current={status,customer_name:samples[i],order_number:`26-082${i}`,phone:`97150123456${i}`,share_url:`https://example.test/share/test-${i}?a=1&b=2`,message:'Broken server message \uFFFD'};
    assert.equal((await click(status)).stopped,true);
    const raw=opened[0].tab.location.href, url=new URL(raw), text=url.searchParams.get('text');
    assert.equal(url.origin,'https://web.whatsapp.com');
    assert.equal(url.searchParams.get('phone'),current.phone);
    assert.equal(url.searchParams.getAll('text').length,1);
    for(const char of chars) assert.equal(text.split(char).length-1,2);
    assert.ok(!text.includes('\uFFFD'));
    assert.equal(text.split(current.share_url).length-1,1);
    assert.ok(text.startsWith(`مرحبا ${samples[i]} ${chars[0]}\n\n`));
    assert.ok(text.includes(`\n\n${chars[3]} *ORDER ${current.order_number}*\n\n`));
    assert.ok(text.endsWith('\n\n*Ivory Gifts*'));
    const arStatus = status === 'ready' ? 'طلبك جاهز!' : 'تم تسليم طلبك بنجاح!';
    const enStatus = status === 'ready' ? 'Your order is ready!' : 'Your order has been delivered!';
    const expected = [
        `مرحبا ${current.customer_name} ${chars[0]}`, '', `${arStatus} ${chars[1]}${chars[2]}`, '',
        `${chars[3]} *الطلب ${current.order_number}*`, '',
        `يمكنك مشاهدة الفاتورة وتفاصيل الطلب هنا ${chars[4]}`, '',
        `Hi ${current.customer_name} ${chars[0]}`, '', `${enStatus} ${chars[1]}${chars[2]}`, '',
        `${chars[3]} *ORDER ${current.order_number}*`, '',
        `You can view your invoice & order details here ${chars[4]}`, '', current.share_url, '',
        `شكراً لاختيارك لنا ${chars[5]}`, `Thank you for choosing us ${chars[5]}`, '', '*Ivory Gifts*',
    ].join('\n');
    assert.equal(text,expected,'Exact message must survive one URL decode');
    const copy=elements.findLast(e=>e.textContent==='Copy message');
    await copy.events.click(); assert.equal(copied.at(-1),text);
    console.log(`PASS ${status} customer ${i+1}: decoded URL, six emojis, exact wording, line breaks, bold and one secure link`);
}
}
assert.equal(opened.length,1); assert.equal(opened[0].name,'ivory_whatsapp');
assert.equal(opened.length,1,'Ready and Delivered share the same named tab');
const count=requests.length; assert.equal((await click('pending')).stopped,false);
assert.equal(requests.length,count,'Unrelated buttons untouched');
hasInvoice=false;await click();assert.equal(requests.at(-1),'/check');
hasInvoice=true;hasProof=false;await click();assert.equal(requests.at(-1),'/check');
hasProof=true;opened[0].tab.closed=true;blocked=true;await click();
const button=elements.findLast(e=>e.textContent==='Open WhatsApp');assert.ok(button);
blocked=false;await button.events.click();assert.equal(opened.length,2);
assert.equal(opened[1].name,'ivory_whatsapp');
console.log('PASS Ready/Delivered A/B/C same direct Web tab; invoice/proof gates; popup-block fallback; clipboard matches URL');
