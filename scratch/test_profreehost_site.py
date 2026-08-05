import urllib.request
import ssl

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

BASE = "http://raptor.unaux.com"

endpoints = [
    "/",
    "/public/",
    "/public/index.php",
    "/public/index.php?route=auth/login",
    "/public/index.php?route=followups/index"
]

print("=== Checking raptor.unaux.com (ProFreeHost Server 185.27.134.34) ===")
for ep in endpoints:
    url = BASE + ep
    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
    try:
        r = urllib.request.urlopen(req, context=ctx, timeout=5)
        body = r.read().decode('utf-8', errors='ignore')
        print(f"[OK] {ep}: HTTP {r.status} | Len: {len(body)}")
        if 'Raptor' in body or 'login' in body.lower():
            print("   Content:", body[:200].replace('\n', ' '))
    except urllib.error.HTTPError as e:
        body = e.read().decode('utf-8', errors='ignore')
        print(f"[HTTP {e.code}] {ep}")
    except Exception as e:
        print(f"[ERR] {ep}: {e}")
