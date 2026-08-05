import urllib.request
import ssl

EC2 = "https://ags.raptor.unaux.com"
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

url = f"{EC2}/public/index.php?update=raptor2026"

print(f"Triggering Self-Updater on EC2: {url}...")
try:
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
    with urllib.request.urlopen(req, timeout=120, context=ctx) as resp:
        body = resp.read().decode('utf-8', errors='ignore')
        print(f"HTTP {resp.status} ({len(body)} bytes)\n")
        print(body)
except Exception as e:
    print(f"Error: {e}")
