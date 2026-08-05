import urllib.request
import ssl

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

url = "https://ags.raptor.unaux.com/bin/migrate.php"
print(f"Triggering Web Auto-Updater & Migration Runner at {url}...")

req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
try:
    r = urllib.request.urlopen(req, context=ctx, timeout=120)
    body = r.read().decode('utf-8', errors='ignore')
    print(f"\nResponse HTTP {r.status} (Length: {len(body)})")
    print("=== Server Output ===")
    print(body)
except urllib.error.HTTPError as e:
    body = e.read().decode('utf-8', errors='ignore')
    print(f"HTTP Error {e.code}:")
    print(body)
except Exception as e:
    print(f"Error: {e}")
