import urllib.request
import urllib.parse
import http.cookiejar
import ssl
import re

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

BASE_URL = "https://ags.raptor.unaux.com/public/index.php"

cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj), urllib.request.HTTPSHandler(context=ctx))
opener.addheaders = [('User-Agent', 'Mozilla/5.0')]

# Step 1: Login as admin@raptor.local
r = opener.open(f"{BASE_URL}?route=auth/login")
login_html = r.read().decode('utf-8', errors='ignore')
csrf_match = re.search(r'name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']', login_html)
csrf_token = csrf_match.group(1) if csrf_match else ''

login_data = urllib.parse.urlencode({
    'email': 'admin@raptor.local',
    'password': 'Password123!',
    'csrf_token': csrf_token
}).encode('utf-8')

req = urllib.request.Request(f"{BASE_URL}?route=auth/login", data=login_data)
r_login = opener.open(req)
print("[OK] Admin Login Status:", r_login.status)

# Step 2: Test all 5 modules as Admin
modules = [
    'followups/index',
    'leads/index',
    'customers/index',
    'communications/index',
    'meetings/index',
    'dashboard/index'
]

for m in modules:
    try:
        r_mod = opener.open(f"{BASE_URL}?route={m}")
        body = r_mod.read().decode('utf-8', errors='ignore')
        title = re.search(r'<title>(.*?)</title>', body, re.DOTALL)
        t_str = title.group(1).strip() if title else 'No title'
        print(f"[OK] Route [{m}]: HTTP {r_mod.status} | Len: {len(body)} | Title: {t_str}")
    except urllib.error.HTTPError as e:
        body = e.read().decode('utf-8', errors='ignore')
        print(f"[HTTP {e.code}] Route [{m}] | Len: {len(body)}")
