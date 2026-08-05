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

login_url = "https://98.94.227.211/public/index.php?route=auth/login"
resp = opener.open(login_url)
html = resp.read().decode('utf-8')

m = re.search(r'name="csrf_token" value="([^"]+)"', html)
token = m.group(1) if m else ""

data = urllib.parse.urlencode({
    "email": "admin@raptor.local",
    "password": "Raptor@12345",
    "csrf_token": token
}).encode('utf-8')

req = urllib.request.Request(login_url, data=data)
resp_login = opener.open(req)
print("Admin Login Status:", resp_login.status, "URL:", resp_login.url)

routes = [
    "followups/index",
    "leads/index",
    "customers/index",
    "communications/index",
    "meetings/index"
]

for route in routes:
    u = f"https://98.94.227.211/public/index.php?route={route}"
    try:
        r = opener.open(u)
        body = r.read().decode('utf-8', errors='ignore')
        print(f"--- SUCCESS: {route} (HTTP {r.status}) ---")
        print("Body snippet:", body[:200].strip())
    except urllib.error.HTTPError as e:
        err_body = e.read().decode('utf-8', errors='ignore')
        print(f"--- FAILED: {route} (HTTP {e.code}) ---")
        print("Error snippet:", err_body[:300].strip())
    print("-" * 50)
