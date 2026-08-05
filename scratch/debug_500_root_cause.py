import urllib.request
import urllib.parse
import http.cookiejar
import ssl
import re

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

BASE_URL = "https://ags.raptor.unaux.com/public/index.php"

# Test logins with EMP001 and any other users
test_users = [
    ('EMP001', 'Password123!'),
    ('EMP002', 'Password123!'),
]

for user_id, pwd in test_users:
    cj = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj), urllib.request.HTTPSHandler(context=ctx))
    opener.addheaders = [('User-Agent', 'Mozilla/5.0')]
    
    print(f"\n==========================================")
    print(f"Logging in as {user_id}...")
    print(f"==========================================")
    
    r = opener.open(f"{BASE_URL}?route=auth/login")
    login_html = r.read().decode('utf-8', errors='ignore')
    csrf_match = re.search(r'name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']', login_html)
    csrf_token = csrf_match.group(1) if csrf_match else ''
    
    login_data = urllib.parse.urlencode({
        'email': user_id,
        'password': pwd,
        'csrf_token': csrf_token
    }).encode('utf-8')

    req = urllib.request.Request(f"{BASE_URL}?route=auth/login", data=login_data)
    r_login = opener.open(req)
    print("Login status:", r_login.status)
    
    modules = ['followups/index', 'leads/index', 'customers/index', 'communications/index', 'meetings/index']
    for m in modules:
        try:
            r_mod = opener.open(f"{BASE_URL}?route={m}")
            body = r_mod.read().decode('utf-8', errors='ignore')
            print(f"Route [{m}]: HTTP {r_mod.status} | Body Len: {len(body)}")
        except urllib.error.HTTPError as e:
            err_body = e.read().decode('utf-8', errors='ignore')
            print(f"Route [{m}]: HTTP {e.code} | Error Body Len: {len(err_body)}")
