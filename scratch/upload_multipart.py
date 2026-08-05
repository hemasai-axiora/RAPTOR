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

# Load main page to get token + understand structure
main_url = f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}&home=%2Fhtdocs%2Fpublic"
r = opener.open(main_url)
html = r.read().decode('utf-8', errors='ignore')
csrf_token = re.search(r'"token"\s*:\s*"([a-f0-9]+)"', html)
csrf_token = csrf_token.group(1) if csrf_token else ''
print(f"Token: {csrf_token[:20]}")

# Save the full HTML to examine
with open('scratch/fm_page.html', 'w', encoding='utf-8') as f:
    f.write(html)
print("Saved FM HTML to scratch/fm_page.html")

# Try to write diag.php via upload (multipart form)
print("\n=== Trying multipart upload ===")
import io

diag_code = b'''<?php
error_reporting(E_ALL); ini_set('display_errors','1');
$root=dirname(__DIR__);
echo "<pre>\nPHP: ".PHP_VERSION."\nDir: ".__DIR__."\nRoot: $root\n";
foreach(['app','app/core','app/config','app/controllers','app/views/followups'] as $d){
  echo "$d: ".(is_dir("$root/$d")?"OK":"MISSING")."\n";
}
foreach(['app/config/config.php','app/core/Model.php','app/core/PermissionService.php','app/controllers/FollowupsController.php','app/views/followups/index.php','app/views/layouts/main.php'] as $f){
  $p="$root/$f"; echo "$f: ".(file_exists($p)?"OK ".filesize($p)."b":"MISSING")."\n";
}
try{
  require_once "$root/app/config/config.php";
  $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS);
  echo "DB: OK\n";
  foreach(['follow_ups','leads','customers','meetings','communications','role_permissions'] as $t){
    try{$n=$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();echo "$t: $n rows\n";}
    catch(Exception $e){echo "$t: MISSING\n";}
  }
}catch(Exception $e){echo "DB Error: ".$e->getMessage()."\n";}
try{
  if(!defined('APPROOT'))define('APPROOT',"$root/app");
  if(!defined('URLROOT'))define('URLROOT','https://ags.raptor.unaux.com');
  if(!defined('APP_ENV'))define('APP_ENV','production');
  require_once APPROOT.'/core/Database.php';
  require_once APPROOT.'/core/Model.php';
  require_once APPROOT.'/core/PermissionService.php';
  require_once APPROOT.'/core/Controller.php';
  require_once APPROOT.'/models/FollowUp.php';
  $f=new FollowUp(); $results=$f->getFollowUps([],null);
  echo "FollowUp: OK ".count($results)." rows\n";
}catch(Throwable $e){
  echo "FollowUp Error: ".$e->getMessage()."\n";
  echo "At: ".$e->getFile().":".$e->getLine()."\n";
  echo $e->getTraceAsString()."\n";
}
echo "</pre>";
'''

boundary = b'----WebKitFormBoundary7MA4YWxkTrZu0gW'

def make_multipart(fields, files):
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

# Upload diag.php
upload_url = f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}"
fields = {'action': 'upload', 'path': '/htdocs/public', 'token': csrf_token}
files = {'file[]': ('diag.php', diag_code, 'application/octet-stream')}
body = make_multipart(fields, files)

req = urllib.request.Request(upload_url, data=body)
req.add_header('Content-Type', f'multipart/form-data; boundary={boundary.decode()}')
req.add_header('Content-Length', str(len(body)))
try:
    r = opener.open(req)
    resp = r.read().decode('utf-8', errors='ignore')
    print(f"Upload: HTTP {r.status}, Response: {resp[:300]}")
except Exception as e:
    print(f"Upload error: {e}")

# Check result
print("\n=== Checking diag.php ===")
try:
    r = opener.open("https://ags.raptor.unaux.com/public/diag.php")
    body = r.read().decode('utf-8', errors='ignore')
    if 'PHP Version' in body or 'PHP:' in body or '<pre>' in body:
        m = re.search(r'<pre>(.*?)</pre>', body, re.DOTALL)
        if m:
            print("=== DIAGNOSTIC OUTPUT ===")
            print(m.group(1)[:5000])
        else:
            print("PHP output found:", body[:2000])
    elif 'Transform Business Data' in body:
        print("Still showing landing page - upload may have failed")
        print("Checking if file exists via different URL...")
        r2 = opener.open("https://ags.raptor.unaux.com/public/patch.php")
        body2 = r2.read().decode('utf-8', errors='ignore')
        if 'Raptor CRM' in body2 and 'Patch' in body2:
            print("patch.php exists and works!")
        else:
            print("patch.php response:", body2[:300])
    else:
        print("Unknown:", body[:500])
except Exception as e:
    print(f"Error: {e}")
