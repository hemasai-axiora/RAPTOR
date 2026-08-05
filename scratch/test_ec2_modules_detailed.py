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

print("=== EC2 Module Access Detailed Diagnostic for EMP001 ===\n")

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

# Test each module independently
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
    try:
        with opener.open(mreq) as mresp:
            mbody = mresp.read(5000).decode('utf-8', errors='ignore')
            mclean = re.sub(r'<[^>]+>', ' ', mbody)
            mclean = re.sub(r'\s+', ' ', mclean).strip()
            print(f"  [OK 200] {name:20s}: HTTP {mresp.status} - Title: {mclean[:80]}")
    except urllib.error.HTTPError as e:
        err_body = e.read(5000).decode('utf-8', errors='ignore')
        err_clean = re.sub(r'<[^>]+>', ' ', err_body)
        err_clean = re.sub(r'\s+', ' ', err_clean).strip()
        print(f"  [ERR {e.code}] {name:20s}: {err_clean[:200]}")
    except Exception as e:
        print(f"  [EXC] {name:20s}: {e}")
