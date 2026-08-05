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

def fm_action(action, path, extra={}):
    params = {'action': action, 'path': path}
    params.update(extra)
    post = urllib.parse.urlencode(params).encode()
    req = urllib.request.Request(f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}", data=post,
        headers={'Content-Type': 'application/x-www-form-urlencoded'})
    r = opener.open(req)
    return r.read().decode('utf-8', errors='ignore')

opener.open(f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}&home=%2Fhtdocs")
print("Session established")

# The key insight: nginx sends ALL requests to index.php (no .htaccess)
# So diag.php doesn't run - index.php runs instead
# We need to modify index.php to output diagnostic info when ?diag=1 is passed

# Read the current index.php on the server
print("=== Reading current server index.php ===")
import json
result = fm_action('edit', '/htdocs/public/index.php')
try:
    data = json.loads(result)
    content = data.get('content', '')
    print(f"Success: {data.get('success')}")
    print(f"Content length: {len(content)}")
    print(f"First 500 chars: {content[:500]}")
except Exception as e:
    print(f"Error: {e}")
    print(result[:500])
