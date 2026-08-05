const http = require('http');
const crypto = require('crypto');

function solveChallenge(callback) {
    http.get('http://raptor.unaux.com/public/index.php', (res) => {
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
                const cookieVal = decrypted.toString('hex');
                callback(cookieVal);
            }
        });
    });
}

solveChallenge((cookieVal) => {
    console.log("Using Cookie:", cookieVal);
    const options = {
        hostname: 'raptor.unaux.com',
        path: '/public/index.php?i=1&route=auth/login',
        headers: {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Cookie': '__test=' + cookieVal + '; expires=Thu, 31-Dec-37 23:55:55 GMT; path=/'
        }
    };

    http.get(options, (res) => {
        let body = '';
        res.on('data', chunk => body += chunk);
        res.on('end', () => {
            console.log(`HTTP ${res.statusCode} | Length: ${body.length}`);
            console.log("Response Body:");
            console.log(body.substring(0, 1000));
        });
    });
});
