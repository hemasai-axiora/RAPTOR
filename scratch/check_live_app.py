import urllib.request
import ssl
import re

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

# Download the HTML of landing page and look for version clues
url = "https://ags.raptor.unaux.com/public/"
req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
r = urllib.request.urlopen(req, context=ctx)
body = r.read().decode('utf-8', errors='ignore')

# Count lines 
lines = body.split('\n')
print(f"Response: HTTP {r.status}, {len(body)} bytes, {len(lines)} lines")

# Find version markers - the new landing page has specific JS content
if 'diag' in body:
    print("DIAGNOSTIC found in response!")
elif 'Transform Business Data' in body:
    print("Old landing page (HomeController) detected")
    
# Look at what HomeController generates
# The landing page title
title_m = re.search(r'<title>([^<]+)</title>', body)
if title_m:
    print(f"Title: {title_m.group(1)}")

# Check if PHP session is being set (means PHP is running)
print(f"PHP session in response: {'PHPSESSID' in r.getheader('set-cookie', '')}")

# Find the session cookie header
print(f"Headers: {dict(r.headers)}")

# Try to see if there's a route that shows PHP version or debug info
print("\n=== Checking existing routes ===")
routes_to_try = [
    "https://ags.raptor.unaux.com/public/?route=auth/login",
    "https://ags.raptor.unaux.com/public/?route=followups/index",
    "https://ags.raptor.unaux.com/public/?route=nonexistent/route",
]
for url in routes_to_try:
    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
    try:
        r = urllib.request.urlopen(req, context=ctx)
        body2 = r.read().decode('utf-8', errors='ignore')
        print(f"\n{url}: HTTP {r.status}, {len(body2)} bytes")
        if 'Application Exception' in body2 or 'message' in body2.lower():
            print("Exception content:", body2[:500])
        elif 'Login' in body2 or 'login' in body2:
            print("Shows login page")
        elif 'Follow' in body2:
            print("Shows followups page!")
        else:
            print("First 100:", body2[:100])
    except urllib.error.HTTPError as e:
        body3 = e.read().decode('utf-8', errors='ignore')
        print(f"\n{url}: HTTP {e.code}")
        if 'Application Exception' in body3 or '<pre>' in body3:
            print("Error body:", body3[:500])
        else:
            print("Error body:", body3[:100])
