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

def fm_save(path, content):
    post = urllib.parse.urlencode({'action': 'save', 'path': path, 'content': content}).encode()
    req = urllib.request.Request(f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}", data=post,
        headers={'Content-Type': 'application/x-www-form-urlencoded'})
    r = opener.open(req)
    return r.read().decode('utf-8', errors='ignore')

def fm_edit(path):
    post = urllib.parse.urlencode({'action': 'edit', 'path': path}).encode()
    req = urllib.request.Request(f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}", data=post,
        headers={'Content-Type': 'application/x-www-form-urlencoded'})
    r = opener.open(req)
    return r.read().decode('utf-8', errors='ignore')

def check_url(url):
    try:
        req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
        r = opener.open(req)
        body = r.read().decode('utf-8', errors='ignore')
        return r.status, body
    except urllib.error.HTTPError as e:
        return e.code, ''
    except Exception as e:
        return 0, str(e)

# Step 1: Get session
opener.open(f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}&home=%2Fhtdocs")
print("Session established")

# Step 2: Save a test PHP file to multiple possible web roots
test_php = '<?php echo "FOUND_AT:" . __FILE__; ?>'

candidates = [
    '/htdocs/public/rtest.php',
    '/htdocs/rtest.php',
    '/htdocs/public/public/rtest.php',
    '/htdocs/html/rtest.php',
    '/htdocs/www/rtest.php',
]

print("=== Saving test files to all candidate paths ===")
for path in candidates:
    result = fm_save(path, test_php)
    print(f"Save {path}: {result[:80]}")

# Step 3: Check URLs to find which one is accessible
print("\n=== Testing URLs to find web root ===")
base_urls = [
    "https://ags.raptor.unaux.com/rtest.php",
    "https://ags.raptor.unaux.com/public/rtest.php",
    "https://ezyro42571719.unaux.com/rtest.php",
    "https://ezyro42571719.unaux.com/public/rtest.php",
    "https://ags.raptor.unaux.com/public/test123.php",
    "https://ags.raptor.unaux.com/test123.php",
]

for url in base_urls:
    code, body = check_url(url)
    if 'FOUND_AT' in body or 'TEST123' in body:
        print(f"SUCCESS: {url} (HTTP {code})")
        print(f"  Response: {body[:200]}")
    elif code == 200:
        print(f"HTTP 200 but not our content at {url}: {body[:100]}")
    else:
        print(f"HTTP {code}: {url}")

# Step 4: Let's look at the Unaux panel for domain/path mapping
# Try accessing index.php via the actual domain
print("\n=== Reading .htaccess files for path hints ===")
for htaccess_path in [
    '/htdocs/.htaccess',
    '/htdocs/public/.htaccess',
    '/htdocs/html/.htaccess',
]:
    result = fm_edit(htaccess_path)
    if '"success":true' in result:
        print(f"Found .htaccess at {htaccess_path}:")
        import json
        try:
            data = json.loads(result)
            print(f"  Content: {data.get('content', '')[:300]}")
        except:
            print(f"  Raw: {result[:300]}")
    else:
        print(f"No .htaccess at {htaccess_path}")

# Step 5: Check if there's a specific document root setting
print("\n=== Reading nginx or apache config if accessible ===")
for config_path in [
    '/etc/nginx/sites-enabled/default',
    '/etc/apache2/sites-enabled/000-default.conf',
    '/htdocs/.user.ini',
    '/htdocs/public/.user.ini',
]:
    result = fm_edit(config_path)
    if '"success":true' in result:
        print(f"Found {config_path}")
        try:
            import json
            data = json.loads(result)
            print(f"  Content: {data.get('content', '')[:500]}")
        except:
            print(f"  Raw: {result[:300]}")

print("\n=== Checking what's currently in htdocs via listing ===")
# Get directory listing by requesting the FM page for specific paths
for list_path in ['/htdocs', '/htdocs/public']:
    r = opener.open(f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}&home={urllib.parse.quote(list_path)}")
    html = r.read().decode('utf-8', errors='ignore')
    # Extract file data-items
    data_items = re.findall(r'data-name="([^"]+)"', html)
    data_paths = re.findall(r'data-path="([^"]+)"', html)
    print(f"\n{list_path} contents (data-name): {data_items[:20]}")
    print(f"{list_path} contents (data-path): {data_paths[:20]}")
    
    # Try to find JSON data
    json_data = re.findall(r'\{[^{}]*"name"[^{}]*\}', html)
    print(f"JSON items: {json_data[:5]}")
