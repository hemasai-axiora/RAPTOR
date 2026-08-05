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
                const a = Buffer.from(aM[1], "hex");
                const b = Buffer.from(bM[1], "hex");
                const c = Buffer.from(cM[1], "hex");
                const decipher = crypto.createDecipheriv('aes-128-cbc', a, b);
                decipher.setAutoPadding(false);
                let decrypted = decipher.update(c);
                decrypted = Buffer.concat([decrypted, decipher.final()]);
                callback(decrypted.toString('hex'));
            } else {
                callback(null);
            }
        });
    });
}

function doRequest(path, cookieVal, label) {
    return new Promise((resolve) => {
        const headers = { 'User-Agent': 'Mozilla/5.0' };
        if (cookieVal) headers['Cookie'] = `__test=${cookieVal}; path=/`;
        
        http.get('http://raptor.unaux.com' + path, { headers }, (res) => {
            let body = '';
            res.on('data', chunk => body += chunk);
            res.on('end', () => {
                console.log(`\n=== ${label} HTTP ${res.statusCode} (${body.length} bytes) ===`);
                if (!body.includes('aes.js')) {
                    console.log(body.substring(0, 4000));
                } else {
                    console.log('(AES challenge still active - cookie not accepted)');
                }
                resolve(body);
            });
        }).on('error', e => { console.log(label, 'Error:', e.message); resolve(''); });
    });
}

async function main() {
    console.log('Step 1: Solving AES challenge...');
    const cookieVal = await new Promise(r => solveChallenge(r));
    console.log('Cookie:', cookieVal);
    
    console.log('\nStep 2: Deploying latest code from GitHub...');
    await doRequest('/public/index.php?update=raptor2026', cookieVal, 'DEPLOY');
    
    console.log('\nStep 3: Running DB credential discovery...');
    await new Promise(r => setTimeout(r, 3000));
    await doRequest('/bin/discover_db_creds.php', cookieVal, 'DB DISCOVERY');
}

main().catch(console.error);
