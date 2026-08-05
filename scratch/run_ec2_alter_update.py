import urllib.request
import ssl

url = "https://ags.raptor.unaux.com/bin/alter_employees.php?update=1"
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

print(f"Triggering alter_employees.php?update=1 on EC2: {url}...")
try:
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
    with urllib.request.urlopen(req, timeout=60, context=ctx) as resp:
        body = resp.read().decode('utf-8', errors='ignore')
        print(f"HTTP {resp.status} ({len(body)} bytes)\n")
        print(body[:2000])
except Exception as e:
    print("Error:", e)
