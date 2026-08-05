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
opener.addheaders = [('User-Agent', 'Mozilla/5.0')]

BASE_URL = "https://ags.raptor.unaux.com/public/index.php"

# Step 1: GET login page to get CSRF token if any
print("=== Step 1: Fetching login page ===")
r = opener.open(f"{BASE_URL}?route=auth/login")
login_html = r.read().decode('utf-8', errors='ignore')
print("Login page status:", r.status)

csrf_match = re.search(r'name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']', login_html)
csrf_token = csrf_match.group(1) if csrf_match else ''
print("CSRF Token:", csrf_token)

# Try multiple passwords
passwords = ['Axiorags@2026', 'Password123!', 'Raptor@12345', 'Admin@12345', 'axiorags@2026']

for pwd in passwords:
    print(f"\n--- Testing Password: {pwd} ---")
    cj = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj), urllib.request.HTTPSHandler(context=ctx))
    opener.addheaders = [('User-Agent', 'Mozilla/5.0')]
    
    r = opener.open(f"{BASE_URL}?route=auth/login")
    login_html = r.read().decode('utf-8', errors='ignore')
    csrf_match = re.search(r'name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']', login_html)
    csrf_token = csrf_match.group(1) if csrf_match else ''
    
    login_data = urllib.parse.urlencode({
        'email': 'axiora.operations@gmail.com',
        'password': pwd,
        'csrf_token': csrf_token
    }).encode('utf-8')

    req = urllib.request.Request(f"{BASE_URL}?route=auth/login", data=login_data)
    try:
        r_login = opener.open(req)
        res_body = r_login.read().decode('utf-8', errors='ignore')
        err_matches = re.findall(r'<div[^>]*class=["\'][^"\']*invalid-feedback[^"\']*["\'][^>]*>(.*?)</div>', res_body)
        if not any(err_matches):
            print(f"SUCCESS with password: {pwd}!")
            print("Response URL / Title:", r_login.geturl())
            
            # Try followups route with this session
            r_f = opener.open(f"{BASE_URL}?route=followups/index")
            f_body = r_f.read().decode('utf-8', errors='ignore')
            print("Followups route status:", r_f.status, "Length:", len(f_body))
            if 'Login' in f_body and 'password' in f_body.lower():
                print("Still redirected to login!")
            else:
                print("Followups body preview:", f_body[:400].replace('\n', ' '))
            break
        else:
            print(f"Failed with password {pwd}: {err_matches}")
    except Exception as e:
        print(f"Error testing {pwd}: {e}")



# Step 3: Access followups module
print("\n=== Step 3: Accessing followups/index ===")
routes = [
    'followups/index',
    'leads/index',
    'customers/index',
    'communications/index',
    'meetings/index',
    'attendance/index'
]

for route in routes:
    url = f"{BASE_URL}?route={route}"
    try:
        req_route = urllib.request.Request(url)
        r_route = opener.open(req_route)
        body = r_route.read().decode('utf-8', errors='ignore')
        print(f"\nRoute [{route}]: HTTP {r_route.status} (Length: {len(body)})")
        if 'Fatal' in body or 'Exception' in body or 'Error' in body:
            print("Error snippet:", body[:1000])
        elif 'login' in body.lower() and 'password' in body.lower():
            print("Redirected to Login page!")
        else:
            print("Success preview:", body[:300].replace('\n', ' '))
    except urllib.error.HTTPError as e:
        err_body = e.read().decode('utf-8', errors='ignore')
        print(f"\nRoute [{route}]: HTTP ERROR {e.code}")
        print("Error Body:", err_body[:2000])
