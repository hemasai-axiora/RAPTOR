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
opener.addheaders = [
    ('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'),
    ('Accept', 'application/json, text/javascript, */*; q=0.01'),
    ('X-Requested-With', 'XMLHttpRequest'),
    ('Referer', 'https://filemanager.ai/new3/index.php'),
]

FM_BASE = "https://filemanager.ai"
FM_USER = "ezyro_42571719"
FM_PASS = "J2ZyVl5bRQ"
TARGET_PATH = "/htdocs/public"

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
  $f=new FollowUp(); $results=$f->getFollowUps([],null);
  echo "FollowUp: OK ".count($results)." rows\\n";
}catch(Throwable $e){
  echo "FollowUp Error: ".$e->getMessage()."\\n";
  echo "At: ".$e->getFile().":".$e->getLine()."\\n";
  echo $e->getTraceAsString()."\\n";
}
echo "</pre>";
'''

# Step 1: GET the file manager page to get session + token
print("=== Step 1: Getting session ===")
main_url = f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}&home={urllib.parse.quote(TARGET_PATH)}"
r = opener.open(main_url)
html = r.read().decode('utf-8', errors='ignore')
print(f"HTTP {r.status}, size: {len(html)}")

csrf_token = re.search(r'"token"\s*:\s*"([a-f0-9]+)"', html)
csrf_token = csrf_token.group(1) if csrf_token else ''
print(f"Token: {csrf_token}")

# Check cookies
for cookie in cj:
    print(f"Cookie: {cookie.name}={cookie.value[:20] if cookie.value else ''}")

# Step 2: Create new file
print("\n=== Step 2: Creating diag.php ===")
for path_to_try in [TARGET_PATH, f"{TARGET_PATH}/", "/htdocs/public"]:
    post_data = urllib.parse.urlencode({
        'action': 'newfile',
        'path': path_to_try,
        'name': 'diag.php',
        'token': csrf_token
    }).encode('utf-8')
    
    req = urllib.request.Request(
        f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}",
        data=post_data,
        headers={'Content-Type': 'application/x-www-form-urlencoded'}
    )
    try:
        r = opener.open(req)
        resp = r.read().decode('utf-8', errors='ignore')
        print(f"Create (path={path_to_try}): {resp[:200]}")
        if '"success":true' in resp:
            print("File created successfully!")
            break
    except Exception as e:
        print(f"Create error: {e}")

# Step 3: Now save content to the created file
print("\n=== Step 3: Saving content to diag.php ===")
file_path = f"{TARGET_PATH}/diag.php"
for save_action in ['savefile', 'save', 'editfile']:
    post_data = urllib.parse.urlencode({
        'action': save_action,
        'path': file_path,
        'content': diag_code,
        'token': csrf_token
    }).encode('utf-8')
    
    req = urllib.request.Request(
        f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}",
        data=post_data,
        headers={'Content-Type': 'application/x-www-form-urlencoded'}
    )
    try:
        r = opener.open(req)
        resp = r.read().decode('utf-8', errors='ignore')
        print(f"Save (action={save_action}): {resp[:200]}")
        if '"success":true' in resp:
            print("Content saved successfully!")
            break
    except Exception as e:
        print(f"Save error: {e}")

# Step 4: Load the file to check it
print("\n=== Step 4: Loading diag.php to verify ===")
for load_action in ['loadfile', 'getfile', 'edit', 'read']:
    post_data = urllib.parse.urlencode({
        'action': load_action,
        'path': file_path,
        'token': csrf_token
    }).encode('utf-8')
    
    req = urllib.request.Request(
        f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}",
        data=post_data,
        headers={'Content-Type': 'application/x-www-form-urlencoded'}
    )
    try:
        r = opener.open(req)
        resp = r.read().decode('utf-8', errors='ignore')
        print(f"Load (action={load_action}): {resp[:300]}")
        if '"content"' in resp or '<?php' in resp:
            print("File content found!")
            break
    except Exception as e:
        print(f"Load error: {e}")

# Step 5: Test the URL
print("\n=== Step 5: Testing diag.php via HTTP ===")
try:
    r = opener.open("https://ags.raptor.unaux.com/public/diag.php")
    body = r.read().decode('utf-8', errors='ignore')
    if '<pre>' in body and ('PHP:' in body or 'PHP Version' in body):
        m = re.search(r'<pre>(.*?)</pre>', body, re.DOTALL)
        print("=== DIAGNOSTIC OUTPUT ===")
        print(m.group(1)[:5000] if m else body[:2000])
    elif 'Transform Business Data' in body:
        print("Still landing page - file write seems to have failed")
        print("Trying FTP approach...")
    else:
        print("Response:", body[:500])
except Exception as e:
    print(f"Error: {e}")
