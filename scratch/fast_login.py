import urllib.request
import urllib.parse
import http.cookiejar
import ssl
import re
from concurrent.futures import ThreadPoolExecutor

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

BASE_URL = "https://ags.raptor.unaux.com/public/index.php"

credentials = [
    ('axiora.operations@gmail.com', 'Axiorags@2026'),
    ('axiora.operations@gmail.com', 'Password123!'),
    ('axiora.operations@gmail.com', 'Raptor@12345'),
    ('axiora.operations@gmail.com', 'axiorags@2026'),
    ('admin@raptor.com', 'Password123!'),
    ('admin@raptor.com', 'Raptor@12345'),
    ('admin@raptor.com', 'Admin@12345'),
    ('employee@raptor.com', 'Password123!'),
    ('employee@raptor.com', 'Raptor@12345'),
    ('EMP001', 'Password123!'),
    ('EMP001', 'Raptor@12345'),
    ('EMP002', 'Password123!'),
    ('EMP002', 'Raptor@12345'),
    ('EMP003', 'Password123!'),
    ('EMP004', 'Password123!'),
    ('admin', 'Password123!'),
    ('admin', 'Raptor@12345'),
]

def try_login(pair):
    ident, pwd = pair
    cj = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj), urllib.request.HTTPSHandler(context=ctx))
    opener.addheaders = [('User-Agent', 'Mozilla/5.0')]
    try:
        r = opener.open(f"{BASE_URL}?route=auth/login", timeout=10)
        login_html = r.read().decode('utf-8', errors='ignore')
        csrf_match = re.search(r'name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']', login_html)
        csrf_token = csrf_match.group(1) if csrf_match else ''
        
        login_data = urllib.parse.urlencode({
            'email': ident,
            'password': pwd,
            'csrf_token': csrf_token
        }).encode('utf-8')

        req = urllib.request.Request(f"{BASE_URL}?route=auth/login", data=login_data)
        r_login = opener.open(req, timeout=10)
        res_body = r_login.read().decode('utf-8', errors='ignore')
        
        err_matches = re.findall(r'<div[^>]*class=["\'][^"\']*invalid-feedback[^"\']*["\'][^>]*>(.*?)</div>', res_body)
        if not any(err_matches):
            print(f"\nSUCCESS LOGIN! User: {ident} | Pass: {pwd}")
            # Try followups
            r_f = opener.open(f"{BASE_URL}?route=followups/index", timeout=10)
            f_body = r_f.read().decode('utf-8', errors='ignore')
            print(f"Followups Route for {ident}: HTTP {r_f.status}, Size {len(f_body)}")
            if 'Login' in f_body and 'password' in f_body.lower():
                print(" -> Redirected to Login")
            elif 'Fatal' in f_body or 'Exception' in f_body or 'Error' in f_body:
                print(" -> CRASHED WITH ERROR:")
                print(f_body[:1000])
            else:
                print(" -> SUCCESS LOADED!")
            return (ident, pwd, True)
        else:
            print(f"Failed ({ident}:{pwd}) -> {err_matches}")
    except Exception as e:
        print(f"Exception ({ident}:{pwd}) -> {e}")
    return (ident, pwd, False)

print("=== Starting Fast Parallel Login Check ===")
with ThreadPoolExecutor(max_workers=5) as executor:
    results = list(executor.map(try_login, credentials))

print("=== Done ===")
