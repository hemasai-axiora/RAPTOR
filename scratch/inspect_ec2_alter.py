import urllib.request
import ssl

EC2 = "https://ags.raptor.unaux.com"
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

url = EC2 + "/bin/alter_employees.php"

print(f"Requesting {url}...")
try:
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
    with urllib.request.urlopen(req, timeout=10, context=ctx) as resp:
        body = resp.read().decode('utf-8', errors='ignore')
        print(f"HTTP {resp.status}")
        print("Body:", repr(body))
except Exception as e:
    print("Error:", e)
