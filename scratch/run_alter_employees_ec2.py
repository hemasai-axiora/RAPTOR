import urllib.request
import ssl

url = "https://ags.raptor.unaux.com/bin/alter_employees.php"

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

print(f"Requesting {url}...")
try:
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
    with urllib.request.urlopen(req, timeout=15, context=ctx) as resp:
        body = resp.read().decode('utf-8', errors='ignore')
        print(f"HTTP {resp.status} ({len(body)} bytes)\n")
        print(body)
except Exception as e:
    print(f"Error: {e}")
