import urllib.request
import ssl

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

BASE = "https://ags.raptor.unaux.com"

apis = [
    "/public/api/session-status.php",
    "/public/api/session-extend.php",
    "/bin/check_roles.php",
    "/bin/alter_employees.php",
    "/bin/alter_tasks.php"
]

for api in apis:
    url = BASE + api
    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
    try:
        r = urllib.request.urlopen(req, context=ctx, timeout=5)
        body = r.read().decode('utf-8', errors='ignore')
        print(f"[OK] {api}: HTTP {r.status} | Len: {len(body)}")
        print("   Body:", body[:300].replace('\n', ' '))
    except urllib.error.HTTPError as e:
        body = e.read().decode('utf-8', errors='ignore')
        print(f"[HTTP {e.code}] {api}: {body[:200]}")
    except Exception as e:
        print(f"[ERR] {api}: {e}")
