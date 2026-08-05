const http = require('http');
const crypto = require('crypto');

function solve(cb) {
    http.get('http://raptor.unaux.com/public/index.php', {headers:{'User-Agent':'Mozilla/5.0'}}, (r) => {
        let b = ''; r.on('data', c => b += c);
        r.on('end', () => {
            const aM=b.match(/a=toNumbers\(["']([0-9a-f]+)["']\)/);
            const bM=b.match(/b=toNumbers\(["']([0-9a-f]+)["']\)/);
            const cM=b.match(/c=toNumbers\(["']([0-9a-f]+)["']\)/);
            if(aM&&bM&&cM){const d=crypto.createDecipheriv('aes-128-cbc',Buffer.from(aM[1],'hex'),Buffer.from(bM[1],'hex'));d.setAutoPadding(false);cb(Buffer.concat([d.update(Buffer.from(cM[1],'hex')),d.final()]).toString('hex'));}else cb(null);
        });
    });
}

function req(path, cookie, label) {
    return new Promise(res => {
        const h = {'User-Agent':'Mozilla/5.0'};
        if(cookie) h['Cookie'] = `__test=${cookie}; path=/`;
        http.get('http://raptor.unaux.com' + path, {headers:h}, (r) => {
            let b = ''; r.on('data', c => b += c);
            r.on('end', () => {
                const ok = r.statusCode < 500;
                const has500 = b.includes('500') || b.includes('Error') || b.includes('Fatal');
                console.log(`${ok&&!has500?'[OK]':'[ERR]'} ${label}: HTTP ${r.statusCode} (${b.length}b)`);
                if(!ok || has500) console.log('  Preview:', b.substring(0,200).replace(/\s+/g,' '));
                res({status: r.statusCode, body: b});
            });
        }).on('error', e => { console.log(`[ERR] ${label}: ${e.message}`); res({status:0, body:''}); });
    });
}

async function main() {
    const cookie = await new Promise(r => solve(r));
    console.log('AES Cookie:', cookie);
    console.log('\n=== Testing All 5 Employee Modules ===\n');
    
    await req('/public/index.php?diag=raptor2026', cookie, 'DB Diagnostic');
    await req('/public/index.php?route=followups/index', cookie, 'Follow-ups');
    await req('/public/index.php?route=leads/index', cookie, 'Leads Manager');
    await req('/public/index.php?route=customers/index', cookie, 'Customer Directory');
    await req('/public/index.php?route=communications/index', cookie, 'Communications');
    await req('/public/index.php?route=meetings/index', cookie, 'Meetings');
    await req('/public/index.php?route=auth/login', cookie, 'Login Page');
    await req('/public/index.php?route=dashboard/index', cookie, 'Dashboard');
    
    console.log('\n=== Done! Check http://raptor.unaux.com/public/index.php?route=auth/login ===');
}
main();
