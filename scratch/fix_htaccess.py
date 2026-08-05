import urllib.request
import urllib.parse
import http.cookiejar
import ssl
import re
import json

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

# Read .htaccess in /htdocs/public
print("=== Reading /htdocs/public/.htaccess ===")
result = fm_action('edit', '/htdocs/public/.htaccess')
try:
    data = json.loads(result)
    print("Current .htaccess content:")
    print(data.get('content', 'EMPTY'))
except:
    print(result[:500])

# Read the current index.php or landing page
print("\n=== Looking for landing_php/index.html in public ===")
# The landing page must come from somewhere - check if it's an index.html
for path in [
    '/htdocs/public/index.html',
    '/htdocs/public/landing_php/index.html',
    '/htdocs/public/index2.html',
]:
    result = fm_action('edit', path)
    if '"success":true' in result:
        print(f"FOUND: {path}")
        try:
            data = json.loads(result)
            content = data.get('content', '')
            print(f"Content (first 200): {content[:200]}")
        except:
            print(result[:200])
    else:
        print(f"Not found: {path}")

# The key is: what does the .htaccess route to?
# If .htaccess redirects all requests to index.php which serves landing page...
# We need to update .htaccess to skip diag.php and patch.php
print("\n=== Updating .htaccess to allow direct PHP file access ===")
new_htaccess = """Options -Indexes
# Custom 404 Not Found Error Page
ErrorDocument 404 https://profreehost.com/404/index.php

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /public/

    # Allow direct access to PHP files (diag.php, patch.php, etc.)
    RewriteCond %{REQUEST_FILENAME} -f
    RewriteRule \.php$ - [L]

    # Allow direct access to existing static files
    RewriteCond %{REQUEST_FILENAME} -f
    RewriteRule ^ - [L]
    
    # Allow direct access to existing directories
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]

    # Route everything else to index.php
    RewriteRule ^ index.php [L]
</IfModule>"""

result = fm_action('save', '/htdocs/public/.htaccess', {'content': new_htaccess})
print(f"Save .htaccess: {result}")

# Test if diag.php is now accessible
print("\n=== Testing diag.php after .htaccess update ===")
try:
    req = urllib.request.Request("https://ags.raptor.unaux.com/public/rtest.php", 
                                  headers={'User-Agent': 'Mozilla/5.0'})
    r = opener.open(req)
    body = r.read().decode('utf-8', errors='ignore')
    if 'FOUND_AT' in body:
        print("SUCCESS! PHP files are now directly accessible!")
        print("Content:", body[:200])
    elif 'Transform Business Data' in body:
        print("Still showing landing page - .htaccess change didn't work")
        # Try to check what's in the .htaccess
        result2 = fm_action('edit', '/htdocs/public/.htaccess')
        data2 = json.loads(result2)
        print("Current .htaccess:", data2.get('content', '')[:300])
    else:
        print("Unknown:", body[:200])
except Exception as e:
    print(f"Error: {e}")
