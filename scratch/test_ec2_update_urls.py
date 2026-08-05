import urllib.request
import ssl

EC2 = "https://ags.raptor.unaux.com"
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

urls = [
    "/bin/migrate.php?update=1",
    "/bin/migrate.php?noupdate=0",
    "/public/index.php?update=1",
    "/public/index.php?update=raptor2026",
    "/bin/alter_employees.php?update=1",
]

print("=== Triggering updates on EC2 ===")
for path in urls:
    url = EC2 + path
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
        with urllib.request.urlopen(req, timeout=15, context=ctx) as resp:
            body = resp.read(2000).decode('utf-8', errors='ignore')
            print(f"\nURL: {path} -> HTTP {resp.status} ({len(body)} bytes)")
            print(body.strip()[:600])
    except urllib.error.HTTPError as e:
        body = e.read(1000).decode('utf-8', errors='ignore')
        print(f"\nURL: {path} -> HTTP {e.code}")
        print(body.strip()[:400])
    except Exception as e:
        print(f"\nURL: {path} -> ERR: {e}")
