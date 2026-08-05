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

solveChallenge((cookieVal) => {
    const doRequest = (path, label) => {
        const headers = { 'User-Agent': 'Mozilla/5.0' };
        if (cookieVal) headers['Cookie'] = `__test=${cookieVal}; path=/`;
        
        http.get('http://raptor.unaux.com' + path, { headers }, (res) => {
            let body = '';
            res.on('data', chunk => body += chunk);
            res.on('end', () => {
                console.log(`\n=== ${label} HTTP ${res.statusCode} ===`);
                if (!body.includes('aes.js')) {
                    console.log(body.substring(0, 3000));
                } else {
                    console.log('(still getting AES challenge, cookie not set)');
                }
            });
        });
    };

    // Step 1: Trigger update to deploy find_mysql_host.php
    doRequest('/public/index.php?update=raptor2026', 'UPDATE');
    
    setTimeout(() => {
        // Step 2: Run the MySQL host scanner
        doRequest('/bin/find_mysql_host.php', 'MYSQL HOST SCAN');
    }, 10000);
});
