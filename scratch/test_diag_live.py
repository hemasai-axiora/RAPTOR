import urllib.request
import ssl
import re

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

# Test the diagnostic backdoor
url = "https://ags.raptor.unaux.com/public/?diag=raptor2026"
req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
r = urllib.request.urlopen(req, context=ctx)
body = r.read().decode('utf-8', errors='ignore')
print(f"HTTP {r.status}")
print(f"Content length: {len(body)}")

if 'PHP:' in body and ('Dir:' in body or 'Root:' in body):
    print("DIAGNOSTIC IS LIVE!")
    # Extract pre content
    m = re.search(r'<pre[^>]*>(.*?)</pre>', body, re.DOTALL)
    if m:
        print("\n=== DIAGNOSTIC OUTPUT ===")
        print(m.group(1)[:5000])
    else:
        # Try to find the content differently
        start = body.find('PHP:')
        if start > -1:
            print("Content from PHP:", body[max(0, start-20):start+2000])
elif 'Transform Business Data' in body or 'Raptor' in body[:100]:
    print("Still showing landing/home page - diagnostic not yet active")
    print("First 300:", body[:300])
else:
    print("Unknown response:", body[:500])

# Also check the self-update endpoint
print("\n\n=== Testing self-update endpoint ===")
url2 = "https://ags.raptor.unaux.com/public/?update=raptor2026"
req2 = urllib.request.Request(url2, headers={'User-Agent': 'Mozilla/5.0'})
r2 = urllib.request.urlopen(req2, context=ctx)
body2 = r2.read().decode('utf-8', errors='ignore')
print(f"HTTP {r2.status}")
if 'Raptor CRM Self-Updater' in body2 or 'Downloading' in body2:
    print("Self-updater is LIVE!")
    m2 = re.search(r'<pre[^>]*>(.*?)</pre>', body2, re.DOTALL)
    if m2: print(m2.group(1)[:2000])
else:
    print("First 200:", body2[:200])
