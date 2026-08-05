import urllib.request
import urllib.parse
import http.cookiejar
import ssl
import re
import json

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(
    urllib.request.HTTPCookieProcessor(cj),
    urllib.request.HTTPSHandler(context=ctx)
)
opener.addheaders = [('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')]

FM_BASE = "https://filemanager.ai"
FM_USER = "ezyro_42571719"
FM_PASS = "J2ZyVl5bRQ"

diag_code = '''<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
$root=dirname(__DIR__);
echo "<pre>\\nPHP: ".PHP_VERSION."\\nDir: ".__DIR__."\\nRoot: $root\\n";
foreach(['app','app/core','app/config','app/controllers','app/views/followups'] as $d){
  echo "$d: ".(is_dir("$root/$d")?"OK":"MISSING")."\\n";
}
foreach(['app/config/config.php','app/core/Model.php','app/core/PermissionService.php','app/controllers/FollowupsController.php','app/views/followups/index.php','app/views/layouts/main.php'] as $f){
  $p="$root/$f"; echo "$f: ".(file_exists($p)?"OK ".filesize($p)."b":"MISSING")."\\n";
}
try{
  require_once "$root/app/config/config.php";
  $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS);
  echo "DB: OK\\n";
  foreach(['follow_ups','leads','customers','meetings','communications','role_permissions'] as $t){
    try{$n=$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();echo "$t: $n rows\\n";}
    catch(Exception $e){echo "$t: MISSING\\n";}
  }
}catch(Exception $e){echo "DB Error: ".$e->getMessage()."\\n";}
try{
  if(!defined('APPROOT'))define('APPROOT',"$root/app");
  if(!defined('URLROOT'))define('URLROOT','https://ags.raptor.unaux.com');
  if(!defined('APP_ENV'))define('APP_ENV','production');
  require_once APPROOT.'/core/Database.php';
  require_once APPROOT.'/core/Model.php';
  require_once APPROOT.'/core/PermissionService.php';
  require_once APPROOT.'/core/Controller.php';
  require_once APPROOT.'/models/FollowUp.php';
  $f=new FollowUp();
  $results=$f->getFollowUps([],null);
  echo "FollowUp: OK ".count($results)." rows\\n";
}catch(Throwable $e){
  echo "FollowUp Error: ".$e->getMessage()."\\n";
  echo "At: ".$e->getFile().":".$e->getLine()."\\n";
  echo $e->getTraceAsString()."\\n";
}
echo "</pre>";
'''

# Step 1: Load file manager to get CSRF token
print("=== Step 1: Loading File Manager ===")
main_url = f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}&home=%2Fhtdocs%2Fpublic"
r = opener.open(main_url)
html = r.read().decode('utf-8', errors='ignore')
print(f"HTTP {r.status}, size: {len(html)}")

# Get CSRF token
token_match = re.search(r'"token"\s*:\s*"([a-f0-9]+)"', html)
if not token_match:
    token_match = re.search(r'token["\s:=\']+([a-f0-9]{32,64})', html, re.IGNORECASE)
csrf_token = token_match.group(1) if token_match else ''
print(f"CSRF Token: {csrf_token[:20]}...")

# Find any form actions or JS fetch URLs
fetch_urls = re.findall(r'fetch\([\'"]([^\'"]+)[\'"]', html)
form_actions = re.findall(r'action=[\'"]([^\'"]+)[\'"]', html)
print("Fetch URLs:", fetch_urls[:5])
print("Form actions:", form_actions[:5])

# Step 2: Try to create a new file using discovered endpoints
print("\n=== Step 2: Creating diag.php ===")

# Try creating via newfile endpoint with token
payloads = [
    {"action": "newfile", "path": "/htdocs/public", "name": "diag.php", "token": csrf_token},
    {"action": "newfile", "file": "/htdocs/public/diag.php", "token": csrf_token},
]

for payload in payloads:
    url = f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}"
    data = urllib.parse.urlencode(payload).encode('utf-8')
    req = urllib.request.Request(url, data=data)
    try:
        r = opener.open(req)
        resp = r.read().decode('utf-8', errors='ignore')
        print(f"Create attempt: HTTP {r.status}, Response: {resp[:200]}")
    except Exception as e:
        print(f"Create attempt error: {e}")

# Step 3: Now save content to the file
print("\n=== Step 3: Saving diag.php content ===")
save_payloads = [
    {"action": "savefile", "path": "/htdocs/public/diag.php", "content": diag_code, "token": csrf_token},
    {"action": "save", "file": "/htdocs/public/diag.php", "content": diag_code, "token": csrf_token},
    {"action": "edit", "path": "/htdocs/public/diag.php", "content": diag_code, "token": csrf_token},
]

for payload in save_payloads:
    url = f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}"
    data = urllib.parse.urlencode(payload).encode('utf-8')
    req = urllib.request.Request(url, data=data)
    try:
        r = opener.open(req)
        resp = r.read().decode('utf-8', errors='ignore')
        print(f"Save attempt (action={payload['action']}): HTTP {r.status}, Response: {resp[:200]}")
        if '"success":true' in resp or 'success' in resp.lower():
            print("SUCCESS!")
            break
    except Exception as e:
        print(f"Save attempt error: {e}")

# Step 4: Check if diag.php now returns PHP output
print("\n=== Step 4: Checking diag.php output ===")
try:
    r = opener.open("https://ags.raptor.unaux.com/public/diag.php")
    body = r.read().decode('utf-8', errors='ignore')
    if 'PHP Version' in body or 'PHP:' in body:
        m = re.search(r'<pre>(.*?)</pre>', body, re.DOTALL)
        if m:
            print("=== DIAGNOSTIC OUTPUT ===")
            print(m.group(1)[:5000])
        else:
            print("PHP content found but no pre tag:", body[:1000])
    elif 'Transform Business Data' in body:
        print("Still shows landing page - file write failed or file is in wrong location")
        # Try to find out what's at /htdocs/public using the file manager
        check_url = f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}&home=%2Fhtdocs%2Fpublic"
        r2 = opener.open(check_url)
        html2 = r2.read().decode('utf-8', errors='ignore')
        # Extract file listing
        files_in_dir = re.findall(r'(?:diag|patch|index|logo|updater|\.htaccess)(?:\.php|\.html)?', html2)
        print("Files in /htdocs/public:", files_in_dir)
    else:
        print("Unknown response:", body[:500])
except Exception as e:
    print(f"Error: {e}")
