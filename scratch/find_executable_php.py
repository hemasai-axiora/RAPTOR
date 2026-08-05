import urllib.request
import ssl

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

BASE = "https://ags.raptor.unaux.com"

candidates = [
    "/bin/migrate.php",
    "/bin/seed.php",
    "/bin/setup.php",
    "/bin/init.php",
    "/bin/schema.php",
    "/public/index.php",
    "/public/sw.js",
    "/public/logo.png",
    "/app/core/Database.php",
    "/app/core/Model.php",
    "/app/core/Policy.php",
    "/app/core/PermissionService.php"
]

print("=== Scanning Executable PHP Files ===")
for path in candidates:
    url = BASE + path
    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
    try:
        r = urllib.request.urlopen(req, context=ctx, timeout=5)
        body = r.read().decode('utf-8', errors='ignore')
        print(f"[OK] {path}: HTTP {r.status} | Len: {len(body)}")
        if len(body) > 0 and len(body) < 1000:
            print("   Content:", body[:300].replace('\n', ' '))
    except urllib.error.HTTPError as e:
        print(f"[HTTP {e.code}] {path}")
    except Exception as e:
        print(f"[ERR] {path}: {e}")
