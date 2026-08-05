import urllib.request
import ssl

EC2 = "https://ags.raptor.unaux.com"
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

url = f"{EC2}/public/index.php?diag=raptor2026"

print(f"Requesting Diagnostic from EC2: {url}...")
try:
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
    with urllib.request.urlopen(req, timeout=30, context=ctx) as resp:
        body = resp.read().decode('utf-8', errors='ignore')
        print(f"HTTP {resp.status} ({len(body)} bytes)\n")
        # Extract pre section
        if '<pre' in body:
            start = body.find('<pre')
            end = body.find('</pre>', start)
            pre = body[start:end] if end != -1 else body[start:]
            import re
            clean = re.sub(r'<[^>]+>', '', pre)
            print(clean.encode('ascii', errors='ignore').decode('ascii'))
        else:
            print("No <pre> tag found.")
except Exception as e:
    print(f"Error: {e}")
