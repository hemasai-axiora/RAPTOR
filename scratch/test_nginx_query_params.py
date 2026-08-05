import urllib.request
import ssl

EC2 = "https://ags.raptor.unaux.com"
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

urls = [
    "/public/index.php?url=diag&diag=raptor2026",
    "/public/index.php?url=update&update=raptor2026",
    "/index.php?diag=raptor2026",
    "/index.php?update=raptor2026",
]

print("=== Testing Query Parameters on Nginx ===")
for path in urls:
    url = EC2 + path
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
        with urllib.request.urlopen(req, timeout=10, context=ctx) as resp:
            body = resp.read(2000).decode('utf-8', errors='ignore')
            print(f"\nURL: {path} -> HTTP {resp.status} ({len(body)} bytes)")
            print(body.strip()[:400])
    except Exception as e:
        print(f"\nURL: {path} -> ERR: {e}")
