import urllib.request
import ssl

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

BASE = "https://ags.raptor.unaux.com"

files = [
    "/bin/migrate.php",
    "/deploy/deploy.sh",
    "/dashboard_schema.sql",
    "/package.json",
    "/Dockerfile",
    "/README.md",
    "/docker-compose.prod.yml",
    "/app/config/config.php"
]

for f in files:
    url = BASE + f
    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
    try:
        r = urllib.request.urlopen(req, context=ctx, timeout=5)
        body = r.read().decode('utf-8', errors='ignore')
        print(f"[OK] {f}: HTTP {r.status} | Len: {len(body)}")
        if len(body) < 500:
            print("   Content:", body[:200].replace('\n', ' '))
    except urllib.error.HTTPError as e:
        print(f"[HTTP {e.code}] {f}")
    except Exception as e:
        print(f"[ERR] {f}: {e}")
