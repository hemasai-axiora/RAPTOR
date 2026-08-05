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

FM_BASE = "https://filemanager.ai"
FM_USER = "ezyro_42571719"
FM_PASS = "J2ZyVl5bRQ"

# Key insight: uploads only allowed to htdocs folders (not subdirectories)
# So file must be uploaded to /htdocs directly - but that's not where PHP runs from
# The web root is /htdocs/public - we need to check what paths are allowed

# Load FM page to get token
main_url = f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}&home=%2Fhtdocs"
r = opener.open(main_url)
html = r.read().decode('utf-8', errors='ignore')
csrf_token = re.search(r'"token"\s*:\s*"([a-f0-9]+)"', html)
csrf_token = csrf_token.group(1) if csrf_token else ''

# Read the FM page HTML for clues
print("Scanning FM page for upload paths...")
# Look for 'htdocs' mentions in HTML
htdocs_refs = re.findall(r'/htdocs[^\s"\'<>]*', html)
print("htdocs paths mentioned:", list(set(htdocs_refs))[:20])

# Upload to /htdocs root (allowed path)
print("\n=== Uploading diag.php to /htdocs ===")
boundary = b'----WebKitFormBoundary7MA4YWxkTrZu0gW'

diag_code = b'''<?php
error_reporting(E_ALL); ini_set('display_errors','1');
$root=__DIR__;
echo "<pre>\nPHP: ".PHP_VERSION."\nDir: ".__DIR__."\n";
foreach(['public','app','RAPTOR-main','RAPTOR-main/public','RAPTOR-main/app'] as $d){
  echo "$d: ".(is_dir("$root/$d")?"EXISTS":"MISSING")."\n";
}
echo "</pre>";
'''

def make_multipart(fields, files, boundary):
    body = b''
    for name, value in fields.items():
        body += b'--' + boundary + b'\r\n'
        body += f'Content-Disposition: form-data; name="{name}"\r\n\r\n'.encode()
        body += value.encode() + b'\r\n'
    for name, (filename, content, ctype) in files.items():
        body += b'--' + boundary + b'\r\n'
        body += f'Content-Disposition: form-data; name="{name}"; filename="{filename}"\r\n'.encode()
        body += f'Content-Type: {ctype}\r\n\r\n'.encode()
        body += content + b'\r\n'
    body += b'--' + boundary + b'--\r\n'
    return body

# Try uploading to /htdocs directly
for target_path in ['/htdocs', '/htdocs/', 'htdocs']:
    fields = {'action': 'upload', 'path': target_path, 'token': csrf_token}
    files = {'file[]': ('diag.php', diag_code, 'application/octet-stream')}
    body = make_multipart(fields, files, boundary)
    
    upload_url = f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}"
    req = urllib.request.Request(upload_url, data=body)
    req.add_header('Content-Type', f'multipart/form-data; boundary={boundary.decode()}')
    try:
        r = opener.open(req)
        resp = r.read().decode('utf-8', errors='ignore')
        print(f"Upload to '{target_path}': HTTP {r.status}, Response: {resp[:200]}")
        if '"success":true' in resp:
            print("SUCCESS!")
            break
    except Exception as e:
        print(f"Error: {e}")

# Check /htdocs/diag.php
print("\n=== Checking /htdocs/diag.php ===")
# If files are at /htdocs/ then this URL would work
for test_url in [
    "https://ags.raptor.unaux.com/diag.php",
    "https://ags.raptor.unaux.com/public/diag.php",
]:
    try:
        r = opener.open(test_url)
        body = r.read().decode('utf-8', errors='ignore')
        if 'PHP Version' in body or 'PHP:' in body or 'Dir:' in body:
            m = re.search(r'<pre>(.*?)</pre>', body, re.DOTALL)
            print(f"\n=== {test_url} DIAGNOSTIC OUTPUT ===")
            print(m.group(1)[:3000] if m else body[:2000])
        elif 'Transform Business Data' in body:
            print(f"{test_url}: Still landing page")
        else:
            print(f"{test_url}: HTTP {r.status}, content: {body[:100]}")
    except Exception as e:
        print(f"{test_url}: Error - {e}")

# Also try to read the index.php using download action
print("\n=== Downloading existing index.php to understand structure ===")
download_data = urllib.parse.urlencode({
    'action': 'download',
    'path': '/htdocs/public/index.php',
    'token': csrf_token
}).encode('utf-8')
download_url = f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}"
req = urllib.request.Request(download_url, data=download_data)
try:
    r = opener.open(req)
    resp = r.read().decode('utf-8', errors='ignore')
    print(f"Download index.php: HTTP {r.status}")
    if '<?php' in resp or 'require' in resp:
        print("Content:", resp[:1000])
    else:
        print("Response:", resp[:200])
except Exception as e:
    print(f"Download error: {e}")
