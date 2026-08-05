import urllib.request
import ssl

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

url = "https://ags.raptor.unaux.com/migrations/0041_fix_employee_crm_permissions.php"
print(f"Requesting {url}...")

req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
try:
    r = urllib.request.urlopen(req, context=ctx, timeout=10)
    body = r.read().decode('utf-8', errors='ignore')
    print(f"Response HTTP {r.status} (Length: {len(body)})")
    print("Body snippet:")
    print(body[:2000])
except urllib.error.HTTPError as e:
    body = e.read().decode('utf-8', errors='ignore')
    print(f"HTTP Error {e.code}:")
    print(body[:1000])
except Exception as e:
    print(f"Error: {e}")
