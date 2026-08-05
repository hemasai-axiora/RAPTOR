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

# Login as EMP001
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
opener.open(req)

test_routes = [
    'followups/index',
    'followups/my_leads',
    'leads/index',
    'leads/show/1',
    'customers/index',
    'customers/show/1',
    'communications/index',
    'meetings/index',
    'attendance/index',
    'dashboard/index',
    'users/profile'
]

for route in test_routes:
    try:
        r_sub = opener.open(f"{BASE_URL}?route={route}")
        body = r_sub.read().decode('utf-8', errors='ignore')
        print(f"Route [{route}]: HTTP {r_sub.status} | Length: {len(body)}")
        title = re.search(r'<title>(.*?)</title>', body, re.DOTALL)
        if title:
            print(f"   Title: {title.group(1).strip()}")
    except urllib.error.HTTPError as e:
        err_body = e.read().decode('utf-8', errors='ignore')
        print(f"Route [{route}]: HTTP {e.code} | Length: {len(err_body)}")
        if len(err_body) > 0:
            print("   Body:", err_body[:500])
