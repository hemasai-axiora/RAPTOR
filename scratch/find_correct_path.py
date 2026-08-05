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

# Get session first
main_url = f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}&home=%2Fhtdocs"
r = opener.open(main_url)
html = r.read().decode('utf-8', errors='ignore')
print(f"Session: HTTP {r.status}")

# Try to find the correct path that the server maps to /htdocs/public
# Look for .htaccess which might redirect to a subdirectory

# First let's check what paths the file manager shows in its structure
# Look for the path display in HTML
path_displays = re.findall(r'(?:home|path|currentpath)[=:"\s]+(/[^"\'<>\s]+)', html, re.IGNORECASE)
print("Path displays:", path_displays[:10])

# Check breadcrumbs
breadcrumbs = re.findall(r'htdocs[^"\'<>]*', html)
print("Breadcrumbs:", breadcrumbs[:10])

# Now let's find the real document root by checking .htaccess
print("\n=== Checking .htaccess in htdocs ===")
post = urllib.parse.urlencode({'action': 'edit', 'path': '/htdocs/.htaccess'}).encode()
req = urllib.request.Request(f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}", data=post,
    headers={'Content-Type': 'application/x-www-form-urlencoded'})
r = opener.open(req)
resp = r.read().decode('utf-8', errors='ignore')
print(f"htdocs/.htaccess: {resp[:500]}")

# Check public/.htaccess 
print("\n=== Checking public/.htaccess ===")
post = urllib.parse.urlencode({'action': 'edit', 'path': '/htdocs/public/.htaccess'}).encode()
req = urllib.request.Request(f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}", data=post,
    headers={'Content-Type': 'application/x-www-form-urlencoded'})
r = opener.open(req)
resp = r.read().decode('utf-8', errors='ignore')
print(f"public/.htaccess: {resp[:500]}")

# Try reading index.php from various paths
print("\n=== Finding the real public/index.php ===")
paths_to_check = [
    '/htdocs/public/index.php',
    '/htdocs/index.php', 
    '/htdocs/RAPTOR-main/public/index.php',
    '/www/htdocs/public/index.php',
    '/var/www/html/public/index.php',
]
for path in paths_to_check:
    post = urllib.parse.urlencode({'action': 'edit', 'path': path}).encode()
    req = urllib.request.Request(f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}", data=post,
        headers={'Content-Type': 'application/x-www-form-urlencoded'})
    r = opener.open(req)
    resp = r.read().decode('utf-8', errors='ignore')
    if '"success":true' in resp or '<?php' in resp or 'content' in resp.lower():
        print(f"SUCCESS: {path}")
        print(f"Content: {resp[:500]}")
        break
    else:
        print(f"FAILED: {path} -> {resp[:80]}")

# Also try GET with path in URL
print("\n=== Trying download endpoint ===")
for path in ['/htdocs/public/index.php', '/htdocs/index.php']:
    url = f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}&action=download&path={urllib.parse.quote(path)}"
    try:
        r = opener.open(url)
        resp = r.read().decode('utf-8', errors='ignore')
        print(f"Download {path}: HTTP {r.status}, {resp[:200]}")
        if '<?php' in resp:
            print("FOUND PHP content!")
            break
    except Exception as e:
        print(f"Download {path}: {e}")

# Try the save action with a simpler test file
print("\n=== Test: save a simple file ===")
for target in ['/htdocs/public/test123.php', '/htdocs/test123.php']:
    post = urllib.parse.urlencode({
        'action': 'save',
        'path': target,
        'content': '<?php echo "TEST123 at: " . __FILE__; ?>'
    }).encode()
    req = urllib.request.Request(f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}", data=post,
        headers={'Content-Type': 'application/x-www-form-urlencoded'})
    r = opener.open(req)
    resp = r.read().decode('utf-8', errors='ignore')
    print(f"Save to {target}: {resp[:100]}")
    if '"success":true' in resp:
        print(f"SUCCESS! File saved to {target}")
        # Now check both possible URLs
        for url in [
            'https://ags.raptor.unaux.com/test123.php',
            'https://ags.raptor.unaux.com/public/test123.php',
        ]:
            try:
                r2 = opener.open(url)
                body = r2.read().decode('utf-8', errors='ignore')
                if 'TEST123' in body:
                    print(f"✓ ACCESSIBLE AT: {url}")
                    print(f"  Content: {body[:200]}")
                elif 'Transform Business Data' in body:
                    print(f"✗ Landing page at: {url}")
                else:
                    print(f"? Unknown at {url}: {body[:100]}")
            except Exception as e:
                print(f"Error at {url}: {e}")
