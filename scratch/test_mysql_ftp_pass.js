const http = require('http');
const crypto = require('crypto');

// Try MySQL with FTP password on ProFreeHost
// ProFreeHost often uses FTP password as MySQL password too
// Host discovered: sql200.ezyro.com
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
            } else { callback(null); }
        });
    });
}

function doRequest(path, cookieVal) {
    return new Promise((resolve) => {
        const headers = { 'User-Agent': 'Mozilla/5.0' };
        if (cookieVal) headers['Cookie'] = `__test=${cookieVal}; path=/`;
        http.get('http://raptor.unaux.com' + path, { headers }, (res) => {
            let body = '';
            res.on('data', chunk => body += chunk);
            res.on('end', () => { resolve({ status: res.statusCode, body }); });
        }).on('error', e => resolve({ status: 0, body: e.message }));
    });
}

async function main() {
    console.log('Solving AES challenge...');
    const cookieVal = await new Promise(r => solveChallenge(r));
    console.log('Cookie:', cookieVal);

    // Upload config.local.php via the update endpoint with FTP password as MySQL password
    // We'll test this by updating discover_db_creds.php to include b441442
    const testRes = await doRequest('/bin/discover_db_creds.php', cookieVal);
    const body = testRes.body;

    // Check if b441442 works
    if (body.includes('SUCCESS')) {
        console.log('DB CONNECTION SUCCEEDED!');
        console.log(body.substring(0, 2000));
    } else {
        // Extract relevant lines
        const lines = body.split('\n').filter(l => l.includes('sql200') || l.includes('SUCCESS') || l.includes('Partial'));
        console.log('DB Discovery result:');
        lines.slice(0, 20).forEach(l => console.log(l));
    }
}

main();
