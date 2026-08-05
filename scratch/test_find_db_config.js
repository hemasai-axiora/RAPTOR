const https = require('https');
const http = require('http');
const crypto = require('crypto');

// Step 1: Compute the __test cookie from ProFreeHost AES challenge
function solveChallenge(url, callback) {
    const mod = url.startsWith('https') ? https : http;
    mod.get(url, { headers: { 'User-Agent': 'Mozilla/5.0' } }, (res) => {
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
                // No challenge, proceed
                callback(null);
            }
        });
    }).on('error', (e) => {
        console.log('Error:', e.message);
        callback(null);
    });
}

// Step 2: Request config.php and diag.php to find DB settings
solveChallenge('http://raptor.unaux.com/public/index.php', (cookieVal) => {
    console.log('AES Cookie:', cookieVal);

    const endpoints = [
        '/public/index.php?diag=raptor2026',
        '/public/diag.php?key=raptor2026',
        '/app/config/config.php',
        '/public/index.php?update=raptor2026'
    ];

    for (const ep of endpoints) {
        const url = 'http://raptor.unaux.com' + ep + '&i=1';
        const headers = { 'User-Agent': 'Mozilla/5.0' };
        if (cookieVal) headers['Cookie'] = `__test=${cookieVal}; path=/`;
        
        http.get(url, { headers }, (res) => {
            let body = '';
            res.on('data', chunk => body += chunk);
            res.on('end', () => {
                console.log(`\n=== ${ep} HTTP ${res.statusCode} Len:${body.length} ===`);
                if (body.length < 5000 && !body.includes('aes.js')) {
                    console.log(body.substring(0, 2000));
                } else if (body.includes('DB_HOST') || body.includes('database')) {
                    console.log('Contains DB info!');
                    console.log(body.substring(0, 2000));
                }
            });
        }).on('error', e => console.log(ep, 'Error:', e.message));
    }
});
