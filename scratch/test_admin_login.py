import urllib.request
import urllib.parse
import http.cookiejar
import ssl
import re

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

BASE_URL = "https://ags.raptor.unaux.com/public/index.php"

admin_users = [
    ('admin@raptor.local', 'Password123!'),
    ('admin@raptor.local', 'Raptor@12345'),
    ('ceo@raptor.local', 'Password123!'),
    ('ceo@raptor.local', 'Raptor@12345'),
    ('employee@raptor.local', 'Password123!'),
    ('employee@raptor.local', 'Raptor@12345'),
]

for ident, pwd in admin_users:
    cj = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj), urllib.request.HTTPSHandler(context=ctx))
    opener.addheaders = [('User-Agent', 'Mozilla/5.0')]
    
    r = opener.open(f"{BASE_URL}?route=auth/login")
    login_html = r.read().decode('utf-8', errors='ignore')
    csrf_match = re.search(r'name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']', login_html)
    csrf_token = csrf_match.group(1) if csrf_match else ''
    
    login_data = urllib.parse.urlencode({
        'email': ident,
        'password': pwd,
        'csrf_token': csrf_token
    }).encode('utf-8')

    req = urllib.request.Request(f"{BASE_URL}?route=auth/login", data=login_data)
    try:
        r_login = opener.open(req)
        res_body = r_login.read().decode('utf-8', errors='ignore')
        err_matches = re.findall(r'<div[^>]*class=["\'][^"\']*invalid-feedback[^"\']*["\'][^>]*>(.*?)</div>', res_body)
        if not any(err_matches):
            print(f"✔ SUCCESS ADMIN LOGIN! User: {ident} | Pass: {pwd}")
            print("   Logged in URL:", r_login.geturl())
            
            # Test followups route as Admin
            r_f = opener.open(f"{BASE_URL}?route=followups/index")
            f_body = r_f.read().decode('utf-8', errors='ignore')
            print(f"   Admin Followups status: HTTP {r_f.status} | Length: {len(f_body)}")
            if 'Follow-ups' in f_body or 'followup' in f_body.lower():
                print("   ✔ Followups loaded cleanly for Admin!")
            break
        else:
            print(f"Failed ({ident}:{pwd}) -> {err_matches}")
    except Exception as e:
        print(f"Error ({ident}:{pwd}) -> {e}")
