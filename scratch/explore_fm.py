import urllib.request
import urllib.parse
import http.cookiejar
import ssl
import re

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(
    urllib.request.HTTPCookieProcessor(cj),
    urllib.request.HTTPSHandler(context=ctx)
)
opener.addheaders = [('User-Agent', 'Mozilla/5.0')]

FM_BASE = "https://filemanager.ai"
FM_USER = "ezyro_42571719"
FM_PASS = "J2ZyVl5bRQ"

# Check what's really in /htdocs/public
print("=== Checking /htdocs/public directory ===")
main_url = f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}&home=%2Fhtdocs%2Fpublic"
r = opener.open(main_url)
html = r.read().decode('utf-8', errors='ignore')
print(f"HTTP {r.status}, size: {len(html)}")

# Extract filenames from HTML
filenames = re.findall(r'"name"\s*:\s*"([^"]+)"', html)
print(f"Files in /htdocs/public: {filenames}")

# Also try to get paths
paths = re.findall(r'"path"\s*:\s*"([^"]+)"', html)
print(f"Paths: {paths[:20]}")

# Check the full HTML for directory content
if 'RAPTOR-main' in html:
    print("NOTE: RAPTOR-main folder found!")
if 'app' in html and 'public' in html:
    print("NOTE: app folder mentioned")

# Try checking /htdocs root
print("\n=== Checking /htdocs root ===")
root_url = f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}&home=%2Fhtdocs"
r2 = opener.open(root_url)
html2 = r2.read().decode('utf-8', errors='ignore')
filenames2 = re.findall(r'"name"\s*:\s*"([^"]+)"', html2)
print(f"Files in /htdocs: {filenames2}")

# Check /htdocs/RAPTOR-main/public
print("\n=== Checking /htdocs/RAPTOR-main/public ===")
rm_url = f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}&home=%2Fhtdocs%2FRAPTOR-main%2Fpublic"
try:
    r3 = opener.open(rm_url)
    html3 = r3.read().decode('utf-8', errors='ignore')
    filenames3 = re.findall(r'"name"\s*:\s*"([^"]+)"', html3)
    print(f"Files in /htdocs/RAPTOR-main/public: {filenames3}")
except Exception as e:
    print(f"Error: {e}")

# Now try to EDIT a file that we know exists: index.php
print("\n=== Checking /htdocs/public/index.php content ===")
csrf_token = re.search(r'"token"\s*:\s*"([a-f0-9]+)"', html).group(1) if re.search(r'"token"\s*:\s*"([a-f0-9]+)"', html) else ''

# Load index.php content via edit action
edit_url = f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}"
data = urllib.parse.urlencode({
    'action': 'loadfile',
    'path': '/htdocs/public/index.php',
    'token': csrf_token
}).encode('utf-8')
req = urllib.request.Request(edit_url, data=data)
try:
    r4 = opener.open(req)
    resp4 = r4.read().decode('utf-8', errors='ignore')
    print(f"Load index.php: HTTP {r4.status}, Response: {resp4[:500]}")
except Exception as e:
    print(f"Load index.php error: {e}")

# Try reading the index.php via get action
data2 = urllib.parse.urlencode({
    'action': 'getfile',
    'path': '/htdocs/public/index.php',
    'token': csrf_token
}).encode('utf-8')
req2 = urllib.request.Request(edit_url, data=data2)
try:
    r5 = opener.open(req2)
    resp5 = r5.read().decode('utf-8', errors='ignore')
    print(f"Get index.php: HTTP {r5.status}, Response: {resp5[:500]}")
except Exception as e:
    print(f"Get index.php error: {e}")

# Try edit action
data3 = urllib.parse.urlencode({
    'action': 'edit',
    'path': '/htdocs/public/index.php',
    'token': csrf_token
}).encode('utf-8')
req3 = urllib.request.Request(edit_url, data=data3)
try:
    r6 = opener.open(req3)
    resp6 = r6.read().decode('utf-8', errors='ignore')
    print(f"Edit index.php: HTTP {r6.status}, Response: {resp6[:500]}")
except Exception as e:
    print(f"Edit index.php error: {e}")
