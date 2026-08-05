import urllib.request
import urllib.parse
import http.cookiejar
import ssl
import re

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj), urllib.request.HTTPSHandler(context=ctx))
opener.addheaders = [('User-Agent', 'Mozilla/5.0')]

BASE_URL = "https://ags.raptor.unaux.com/public/index.php"

# Step 1: Login
print("=== Step 1: Logging in as EMP001 ===")
r = opener.open(f"{BASE_URL}?route=auth/login")
login_html = r.read().decode('utf-8', errors='ignore')
csrf_match = re.search(r'name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']', login_html)
csrf_token = csrf_match.group(1) if csrf_match else ''

login_data = urllib.parse.urlencode({
    'email': 'EMP001',
    'password': 'Password123!',
    'csrf_token': csrf_token
}).encode('utf-8')

req = urllib.request.Request(f"{BASE_URL}?route=auth/login", data=login_data)
r_login = opener.open(req)
print("Login Status Code:", r_login.status)
print("Final URL after login:", r_login.geturl())
print("Cookies after login:")
for c in cj:
    print(f"  {c.name} = {c.value}")

# Step 2: Access each route
routes = [
    'attendance/index',
    'followups/index',
    'leads/index',
    'customers/index',
    'communications/index',
    'meetings/index',
    'dashboard/index'
]

for route in routes:
    print(f"\n--------------------------------------------------")
    print(f"Testing route: {route}")
    print(f"--------------------------------------------------")
    try:
        r_mod = opener.open(f"{BASE_URL}?route={route}")
        body = r_mod.read().decode('utf-8', errors='ignore')
        print("Status Code:", r_mod.status)
        print("Final URL:", r_mod.geturl())
        print("Body Length:", len(body))
        # Title check
        title = re.search(r'<title>(.*?)</title>', body, re.DOTALL)
        if title:
            print("Page Title:", title.group(1).strip())
        if 'Fatal' in body or 'Exception' in body or 'Error' in body:
            print("Error content snippet:", body[:1000])
        else:
            print("Preview snippet:", body[:300].replace('\n', ' '))
    except urllib.error.HTTPError as e:
        print(f"HTTP Error Code: {e.code}")
        err_body = e.read().decode('utf-8', errors='ignore')
        print(f"Error Body Length: {len(err_body)}")
        print("Error Body:", err_body[:1000])
