const crypto = require('crypto');

const a = Buffer.from("f655ba9d09a112d4968c63579db590b4", "hex");
const b = Buffer.from("98344c2eee86c3994890592585b49f80", "hex");
const c = Buffer.from("f2e4b059332eb7200303aecce5d43a10", "hex");

const decipher = crypto.createDecipheriv('aes-128-cbc', a, b);
decipher.setAutoPadding(false);
let decrypted = decipher.update(c);
decrypted = Buffer.concat([decrypted, decipher.final()]);

console.log("__test=" + decrypted.toString('hex'));
