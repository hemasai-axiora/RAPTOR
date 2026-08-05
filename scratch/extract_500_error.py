import urllib.request
import urllib.parse
import http.cookiejar
import ssl

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj), urllib.request.HTTPSHandler(context=ctx))
opener.addheaders = [('User-Agent', 'Mozilla/5.0')]

BASE_URL = "https://ags.raptor.unaux.com/public/index.php"

# 1. Login as EMP001
import re
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
print("Login Status:", r_login.status)

# 2. Check each module route
routes = [
    'followups/index',
    'leads/index',
    'customers/index',
    'communications/index',
    'meetings/index'
]

for route in routes:
    print(f"\n==========================================")
    print(f"Testing route: {route}")
    print(f"==========================================")
    try:
        r_mod = opener.open(f"{BASE_URL}?route={route}")
        body = r_mod.read().decode('utf-8', errors='ignore')
        print("HTTP Status:", r_mod.status)
        print("Response length:", len(body))
        print("Body snippet:")
        print(body[:2000])
    except urllib.error.HTTPError as e:
        print(f"HTTP Error Code: {e.code}")
        err_body = e.read().decode('utf-8', errors='ignore')
        print(f"Error Body length: {len(err_body)}")
        print("Error Body:")
        print(err_body)
