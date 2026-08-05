const http = require('http');
const crypto = require('crypto');

function solveChallenge(callback) {
    http.get('http://raptor.unaux.com/public/index.php', { headers: { 'User-Agent': 'Mozilla/5.0' } }, (res) => {
        let body = '';
        res.on('data', chunk => body += chunk);
        res.on('end', () => {
            const aM = body.match(/a=toNumbers\(["']([0-9a-f]+)["']\)/);
            const bM = body.match(/b=toNumbers\(["']([0-9a-f]+)["']\)/);
            const cM = body.match(/c=toNumbers\(["']([0-9a-f]+)["']\)/);
            if (aM && bM && cM) {
                const key = Buffer.from(aM[1], "hex");
                const iv  = Buffer.from(bM[1], "hex");
                const ct  = Buffer.from(cM[1], "hex");
                const d = crypto.createDecipheriv('aes-128-cbc', key, iv);
                d.setAutoPadding(false);
                callback(Buffer.concat([d.update(ct), d.final()]).toString('hex'));
            } else { callback(null); }
        });
    });
}

function request(path, cookie) {
    return new Promise(resolve => {
        const headers = { 'User-Agent': 'Mozilla/5.0' };
        if (cookie) headers['Cookie'] = `__test=${cookie}; path=/`;
        http.get('http://raptor.unaux.com' + path, { headers }, res => {
            let body = '';
            res.on('data', c => body += c);
            res.on('end', () => resolve({ status: res.statusCode, body }));
        }).on('error', e => resolve({ status: 0, body: e.message }));
    });
}

async function main() {
    const cookie = await new Promise(r => solveChallenge(r));
    console.log('Cookie:', cookie);

    console.log('\n--- Running Full Setup Script ---');
    const r = await request('/bin/full_setup.php', cookie);
    console.log(`HTTP ${r.status} (${r.body.length} bytes)`);
    if (!r.body.includes('aes.js')) {
        console.log(r.body);
    } else {
        console.log('(AES challenge - cookie issue)');
        console.log(r.body.substring(0, 500));
    }
}

main();
