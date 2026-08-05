import urllib.request
import ssl

EC2 = "https://ags.raptor.unaux.com"
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

endpoints = [
    "/bin/migrate.php",
    "/public/index.php?update=raptor2026",
    "/bin/patch_crm_live.php",
    "/bin/alter_employees.php",
    "/deploy/deploy.sh"
]

print("=== Checking EC2 migration/update entry points ===")
for ep in endpoints:
    url = EC2 + ep
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
        with urllib.request.urlopen(req, timeout=10, context=ctx) as resp:
            body = resp.read(1000).decode('utf-8', errors='ignore')
            print(f"[HTTP {resp.status}] {ep} (length: {len(body)})")
            if len(body) < 1000:
                print(f"   Output: {body.strip()[:300]}")
    except Exception as e:
        print(f"[ERR] {ep}: {e}")
