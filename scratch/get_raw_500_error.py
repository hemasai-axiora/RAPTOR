import urllib.request
import urllib.parse
import http.cookiejar
import ssl

EC2 = "https://ags.raptor.unaux.com"
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(
    urllib.request.HTTPCookieProcessor(cj),
    urllib.request.HTTPSHandler(context=ctx)
)

print("=== Fetching Raw 500 Error Details from EC2 ===\n")

# GET login page
req = urllib.request.Request(f"{EC2}/public/index.php?route=auth/login", headers={"User-Agent": "Mozilla/5.0"})
with opener.open(req) as resp:
    html = resp.read().decode('utf-8', errors='ignore')

import re
m = re.search(r'name=["\']csrf_token["\'][^>]*value=["\']([^"\']+)["\']', html)
csrf = m.group(1) if m else ""

# Log in as EMP001
data = urllib.parse.urlencode({
    "email": "EMP001",
    "password": "Password123!",
    "csrf_token": csrf
}).encode('utf-8')

preq = urllib.request.Request(
    f"{EC2}/public/index.php?route=auth/login",
    data=data,
    headers={"User-Agent": "Mozilla/5.0", "Content-Type": "application/x-www-form-urlencoded"}
)
opener.open(preq)

# Fetch Followups 500 Error Body
murl = f"{EC2}/public/index.php?route=followups/index"
mreq = urllib.request.Request(murl, headers={"User-Agent": "Mozilla/5.0"})
try:
    with opener.open(mreq) as mresp:
        print("Success?", mresp.status)
except urllib.error.HTTPError as e:
    err_body = e.read().decode('utf-8', errors='ignore')
    print("=== HTTP 500 RAW ERROR BODY FOR followups/index ===")
    print(err_body[:3000])
