import urllib.request
import urllib.parse
import ssl
import http.cookiejar
import re

EC2 = "https://ags.raptor.unaux.com"
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

# Set up cookie jar to maintain session
cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(
    urllib.request.HTTPCookieProcessor(cj),
    urllib.request.HTTPSHandler(context=ctx)
)

print("=== Testing EC2 with Login Session ===\n")

# Step 1: GET login page to get any CSRF token
print("[1] GET login page...")
req = urllib.request.Request(f"{EC2}/public/index.php?route=auth/login", headers={"User-Agent": "Mozilla/5.0"})
with opener.open(req) as resp:
    login_body = resp.read().decode('utf-8', errors='ignore')
    print(f"    Login page: HTTP {resp.status} ({len(login_body)} bytes)")

# Extract CSRF token if present
csrf_match = re.search(r'name=["\']csrf_token["\'][^>]*value=["\']([^"\']+)["\']', login_body)
csrf_token = csrf_match.group(1) if csrf_match else None
print(f"    CSRF token: {csrf_token or 'none'}")

# Try employee credentials - try several combos
credentials_to_try = [
    ("EMP001", "Password123!"),
    ("EMP001", "Raptor@12345"),
    ("admin@raptor.local", "Password123!"),
    ("employee@raptor.local", "Password123!"),
    ("admin", "Password123!"),
    ("admin", "admin123"),
]

for emp_id, password in credentials_to_try:
    print(f"\n[2] Trying login: {emp_id} / {password}")
    
    post_data = {
        "employee_id": emp_id,
        "password": password,
    }
    if csrf_token:
        post_data["csrf_token"] = csrf_token
    
    data = urllib.parse.urlencode(post_data).encode('utf-8')
    req = urllib.request.Request(
        f"{EC2}/public/index.php?route=auth/login",
        data=data,
        headers={
            "User-Agent": "Mozilla/5.0",
            "Content-Type": "application/x-www-form-urlencoded",
            "Referer": f"{EC2}/public/index.php?route=auth/login",
        }
    )
    with opener.open(req) as resp:
        body = resp.read(3000).decode('utf-8', errors='ignore')
        final_url = resp.url
        print(f"    Response: HTTP {resp.status}, URL: {final_url}")
        
        if 'dashboard' in final_url.lower() or 'dashboard' in body.lower():
            print("    *** LOGIN SUCCEEDED - ON DASHBOARD ***")
            
            # Now test each module
            print("\n[3] Testing modules while logged in...")
            modules = [
                ("followups/index", "Follow-ups"),
                ("leads/index", "Leads"),
                ("customers/index", "Customers"),
                ("communications/index", "Communications"),
                ("meetings/index", "Meetings"),
            ]
            for route, label in modules:
                mreq = urllib.request.Request(
                    f"{EC2}/public/index.php?route={route}",
                    headers={"User-Agent": "Mozilla/5.0"}
                )
                with opener.open(mreq) as mresp:
                    mbody = mresp.read(5000).decode('utf-8', errors='ignore')
                    mclean = re.sub(r'<[^>]+>', ' ', mbody)
                    mclean = re.sub(r'\s+', ' ', mclean).strip()
                    
                    has_error = any(x in mbody for x in ['500', 'Fatal error', 'Database Connection Error', 'Undefined variable', 'Access Denied', 'permission'])
                    status_icon = "ERR" if has_error else "OK "
                    print(f"    [{status_icon}] {label}: HTTP {mresp.status} - {mclean[:200]}")
            break
        elif 'invalid' in body.lower() or 'incorrect' in body.lower() or 'wrong' in body.lower():
            print("    Invalid credentials")
        else:
            clean = re.sub(r'<[^>]+>', ' ', body)
            clean = re.sub(r'\s+', ' ', clean).strip()
            print(f"    Still on login page - body: {clean[:100]}")
