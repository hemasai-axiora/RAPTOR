const http = require('http');
const crypto = require('crypto');

function solve(cb) {
    http.get('http://raptor.unaux.com/public/index.php', {headers:{'User-Agent':'Mozilla/5.0'}}, (r) => {
        let b = '';
        r.on('data', c => b += c);
        r.on('end', () => {
            const aM = b.match(/a=toNumbers\(["']([0-9a-f]+)["']\)/);
            const bM = b.match(/b=toNumbers\(["']([0-9a-f]+)["']\)/);
            const cM = b.match(/c=toNumbers\(["']([0-9a-f]+)["']\)/);
            if (aM && bM && cM) {
                const d = crypto.createDecipheriv('aes-128-cbc', Buffer.from(aM[1],'hex'), Buffer.from(bM[1],'hex'));
                d.setAutoPadding(false);
                cb(Buffer.concat([d.update(Buffer.from(cM[1],'hex')), d.final()]).toString('hex'));
            } else cb(null);
        });
    });
}

solve(cookie => {
    const headers = {'User-Agent': 'Mozilla/5.0'};
    if (cookie) headers['Cookie'] = '__test=' + cookie + '; path=/';
    http.get('http://raptor.unaux.com/bin/env_probe.php', {headers}, (r) => {
        let b = '';
        r.on('data', c => b += c);
        r.on('end', () => {
            console.log('HTTP', r.statusCode, '(', b.length, 'bytes)');
            if (!b.includes('aes.js')) {
                console.log(b);
            } else {
                console.log('AES challenge active');
                console.log(b.substring(0, 200));
            }
        });
    });
});
