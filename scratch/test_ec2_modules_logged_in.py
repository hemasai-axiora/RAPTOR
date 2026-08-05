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

print("=== EC2 Module Access Test for Employee EMP001 ===\n")

# GET login page
req = urllib.request.Request(f"{EC2}/public/index.php?route=auth/login", headers={"User-Agent": "Mozilla/5.0"})
with opener.open(req) as resp:
    html = resp.read().decode('utf-8', errors='ignore')

m = re.search(r'name=["\']csrf_token["\'][^>]*value=["\']([^"\']+)["\']', html)
csrf = m.group(1) if m else ""

# Log in as EMP001 / Password123!
data = urllib.parse.urlencode({
    "email": "EMP001",
    "password": "Password123!",
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
    print(f"Login result: HTTP {presp.status} -> Redirected to: {purl}\n")

# Now test all 5 employee modules
modules = [
    ("followups/index", "Follow-ups"),
    ("leads/index", "Leads Manager"),
    ("customers/index", "Customer Directory"),
    ("communications/index", "Communications"),
    ("meetings/index", "Meetings"),
    ("attendance/index", "Attendance Gateway"),
    ("dashboard/index", "Dashboard"),
]

print("=== Accessing Employee Modules on EC2 ===")
for r, name in modules:
    murl = f"{EC2}/public/index.php?route={r}"
    mreq = urllib.request.Request(murl, headers={"User-Agent": "Mozilla/5.0"})
    with opener.open(mreq) as mresp:
        mbody = mresp.read(5000).decode('utf-8', errors='ignore')
        mclean = re.sub(r'<[^>]+>', ' ', mbody)
        mclean = re.sub(r'\s+', ' ', mclean).strip()
        
        has_500 = "500 Internal Server Error" in mbody or "Fatal error" in mbody
        has_denied = "Access Denied" in mbody or "403" in mbody
        
        if has_500:
            status_str = "[500 ERROR]"
        elif has_denied:
            status_str = "[DENIED]"
        else:
            status_str = "[OK 200]"
            
        print(f"  {status_str} {name:20s}: HTTP {mresp.status} - Title/Content: {mclean[:100]}")
