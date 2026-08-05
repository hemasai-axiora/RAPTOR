import urllib.request
import ssl

EC2 = "https://ags.raptor.unaux.com"
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

urls = [
    "/bin/migrate.php",
    "/bin/migrate.php?+--status",
    "/bin/migrate.php?--status",
    "/bin/migrate.php?status",
    "/bin/migrate.php?argv[]=test",
    "/bin/migrate.php?argv=1",
]

print("=== Testing /bin/migrate.php parameter passing on EC2 ===")
for path in urls:
    url = EC2 + path
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
        with urllib.request.urlopen(req, timeout=10, context=ctx) as resp:
            body = resp.read(2000).decode('utf-8', errors='ignore')
            print(f"\nURL: {path} -> HTTP {resp.status} ({len(body)} bytes)")
            print(body.strip()[:500])
    except urllib.error.HTTPError as e:
        body = e.read(1000).decode('utf-8', errors='ignore')
        print(f"\nURL: {path} -> HTTP {e.code}")
        print(body.strip()[:300])
    except Exception as e:
        print(f"\nURL: {path} -> ERR: {e}")
