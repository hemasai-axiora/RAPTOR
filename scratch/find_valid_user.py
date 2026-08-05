import urllib.request
import urllib.parse
import http.cookiejar
import ssl
import re

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

BASE_URL = "https://ags.raptor.unaux.com/public/index.php"

identifiers = [
    'axiora.operations@gmail.com',
    'admin@raptor.com',
    'admin@example.com',
    'employee@raptor.com',
    'EMP001',
    'EMP002',
    'EMP003',
    'EMP004',
    'EMP005',
    'admin',
    'axiora'
]

passwords = [
    'Axiorags@2026',
    'Password123!',
    'Raptor@12345',
    'Admin@12345',
    'admin123'
]

for ident in identifiers:
    for pwd in passwords:
        cj = http.cookiejar.CookieJar()
        opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj), urllib.request.HTTPSHandler(context=ctx))
        opener.addheaders = [('User-Agent', 'Mozilla/5.0')]
        
        try:
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
            r_login = opener.open(req)
            res_body = r_login.read().decode('utf-8', errors='ignore')
            
            err_matches = re.findall(r'<div[^>]*class=["\'][^"\']*invalid-feedback[^"\']*["\'][^>]*>(.*?)</div>', res_body)
            if not any(err_matches):
                print(f"🎉 VALID LOGIN FOUND! Identifier: {ident} | Password: {pwd}")
                # Print redirect URL or session cookies
                for c in cj:
                    print(f"   Cookie: {c.name}={c.value}")
                
                # Check followups route
                r_f = opener.open(f"{BASE_URL}?route=followups/index")
                f_body = r_f.read().decode('utf-8', errors='ignore')
                print("   Followups route status:", r_f.status, "Length:", len(f_body))
                if 'login' in f_body.lower() and 'password' in f_body.lower():
                    print("   -> Redirected to login")
                elif 'Fatal' in f_body or 'Exception' in f_body or 'Error' in f_body:
                    print("   -> CRASHED WITH ERROR:")
                    print(f_body[:1000])
                else:
                    print("   -> SUCCESS! Followups page loaded fine.")
                break
        except Exception as e:
            pass
