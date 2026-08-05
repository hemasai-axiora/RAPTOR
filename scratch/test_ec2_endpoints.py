import urllib.request
import ssl

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

BASE = "https://ags.raptor.unaux.com"

endpoints = [
    "/public/index.php?route=updater",
    "/public/updater.php",
    "/public/patch.php",
    "/public/diag.php",
    "/bin/migrate.php",
    "/deploy.sh",
    "/deploy/deploy.sh",
    "/health-check.sh",
    "/api/health",
    "/public/api/health",
    "/storage/logs/laravel.log",
    "/storage/logs/app.log"
]

print("=== Scanning EC2 Endpoints ===")
for ep in endpoints:
    url = BASE + ep
    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
    try:
        r = urllib.request.urlopen(req, context=ctx, timeout=5)
        body = r.read().decode('utf-8', errors='ignore')
        print(f"[OK] {ep}: HTTP {r.status} | Length {len(body)}")
        if 'Raptor' not in body[:200] and 'html' not in body[:50].lower():
            print("   Content:", body[:300])
        elif 'Fatal' in body or 'Exception' in body or 'Success' in body:
            print("   Body:", body[:200])
    except urllib.error.HTTPError as e:
        body = e.read().decode('utf-8', errors='ignore')
        print(f"[HTTP {e.code}] {ep} | Length {len(body)}")
    except Exception as e:
        print(f"[ERR] {ep}: {e}")
