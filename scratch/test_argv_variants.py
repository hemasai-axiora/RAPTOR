import urllib.request
import ssl

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

BASE = "https://ags.raptor.unaux.com/bin/migrate.php"

variants = [
    "",
    "?+--status",
    "?--status",
    "?argv=1",
    "?argv[]=1",
    "?argv[]=--status"
]

for v in variants:
    url = BASE + v
    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
    try:
        r = urllib.request.urlopen(req, context=ctx, timeout=5)
        body = r.read().decode('utf-8', errors='ignore')
        print(f"\nVariant [{v}]: HTTP {r.status} | Len: {len(body)}")
        print("Snippet:", body[:400].replace('\n', ' '))
    except urllib.error.HTTPError as e:
        body = e.read().decode('utf-8', errors='ignore')
        print(f"\nVariant [{v}]: HTTP {e.code}")
        print("Snippet:", body[:400].replace('\n', ' '))
