import urllib.request
import urllib.parse
import http.cookiejar
import ssl
import re

EC2 = "https://ags.raptor.unaux.com"
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(
    urllib.request.HTTPCookieProcessor(cj),
    urllib.request.HTTPSHandler(context=ctx)
)

print("=== EC2 Authentication & Module Verification ===\n")

# GET login page
req = urllib.request.Request(f"{EC2}/public/index.php?route=auth/login", headers={"User-Agent": "Mozilla/5.0"})
with opener.open(req) as resp:
    html = resp.read().decode('utf-8', errors='ignore')

m = re.search(r'name=["\']csrf_token["\'][^>]*value=["\']([^"\']+)["\']', html)
csrf = m.group(1) if m else ""
print(f"Login page ready. CSRF: {csrf[:15]}...")

# Test logins
test_users = [
    ("EMP001", "Password123!"),
    ("EMP002", "Password123!"),
    ("EMP001", "Raptor@12345"),
    ("EMP002", "Raptor@12345"),
    ("admin@raptor.local", "Password123!"),
    ("employee@raptor.local", "Password123!"),
]

for identifier, passw in test_users:
    data = urllib.parse.urlencode({
        "email": identifier,
        "password": passw,
        "csrf_token": csrf
    }).encode('utf-8')
    
    preq = urllib.request.Request(
        f"{EC2}/public/index.php?route=auth/login",
        data=data,
        headers={"User-Agent": "Mozilla/5.0", "Content-Type": "application/x-www-form-urlencoded"}
    )
    
    with opener.open(preq) as presp:
        pbody = presp.read(4000).decode('utf-8', errors='ignore')
        purl = presp.url
        print(f"Login attempt '{identifier}': HTTP {presp.status} -> URL: {purl}")
        
        if "auth/login" not in purl:
            print(f"🎉 LOGIN SUCCESS for {identifier}! Redirected to: {purl}")
            
            # Test all 5 modules
            modules = [
                ("followups/index", "Follow-ups"),
                ("leads/index", "Leads Manager"),
                ("customers/index", "Customer Directory"),
                ("communications/index", "Communications"),
                ("meetings/index", "Meetings"),
            ]
            for r, name in modules:
                murl = f"{EC2}/public/index.php?route={r}"
                mreq = urllib.request.Request(murl, headers={"User-Agent": "Mozilla/5.0"})
                with opener.open(mreq) as mresp:
                    mbody = mresp.read(5000).decode('utf-8', errors='ignore')
                    mclean = re.sub(r'<[^>]+>', ' ', mbody)
                    mclean = re.sub(r'\s+', ' ', mclean).strip()
                    has_err = any(k in mbody for k in ['500', 'Fatal error', 'Database Connection Error', 'Undefined variable', 'Access Denied'])
                    status = "ERR" if has_err else "OK "
                    print(f"   [{status}] {name}: HTTP {mresp.status} - {mclean[:120]}")
            break
        else:
            # Check for error msg on page
            err_m = re.search(r'class=["\'][^"\']*alert-danger[^"\']*["\'][^>]*>(.*?)</div>', pbody, re.DOTALL)
            if err_m:
                print(f"   Notice: {re.sub(r'<[^>]+>', '', err_m.group(1)).strip()}")
