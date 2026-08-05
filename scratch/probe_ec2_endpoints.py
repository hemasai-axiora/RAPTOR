import urllib.request
import ssl

EC2 = "https://ags.raptor.unaux.com"
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

test_paths = [
    "/bin/alter_employees.php",
    "/bin/migrate.php",
    "/bin/seed.php",
    "/bin/schema.php",
    "/bin/setup.php",
    "/bin/install.php",
    "/bin/check.php",
    "/public/index.php",
    "/public/patch.php",
    "/public/updaters.php",
    "/public/update.php",
    "/public/api/session-status.php",
]

print("=== Probing EC2 Endpoints ===")
for path in test_paths:
    url = EC2 + path
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
        with urllib.request.urlopen(req, timeout=5, context=ctx) as resp:
            body = resp.read(500).decode('utf-8', errors='ignore')
            print(f"[HTTP {resp.status}] {path} ({len(body)} bytes)")
            if not body.startswith("<!DOCTYPE html>"):
                print(f"   Excerpt: {body.strip()[:200]}")
    except urllib.error.HTTPError as e:
        print(f"[HTTP {e.code}] {path}")
    except Exception as e:
        print(f"[ERR] {path}: {e}")
