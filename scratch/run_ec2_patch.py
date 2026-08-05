import urllib.request
import ssl
import re

url = "https://ags.raptor.unaux.com/public/patch.php"

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

print(f"Triggering Smart Patch on EC2: {url}...")
try:
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
    with urllib.request.urlopen(req, timeout=30, context=ctx) as resp:
        body = resp.read().decode('utf-8', errors='ignore')
        print(f"HTTP {resp.status} ({len(body)} bytes)\n")
        clean = re.sub(r'<[^>]+>', '\n', body)
        clean = re.sub(r'\n+', '\n', clean).strip()
        lines = [line.strip() for line in clean.split('\n') if any(k in line for k in ['Patched', 'Migration', 'Checking', 'Complete', '✔', 'SUCCESS', 'Error', 'notice'])]
        for l in lines:
            print(l.encode('ascii', errors='ignore').decode('ascii'))
except Exception as e:
    print(f"Error: {e}")
