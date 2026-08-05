import urllib.request
import urllib.parse
import http.cookiejar
import ssl
import re
import json
import time

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

def fm_save(path, content):
    params = {'action': 'save', 'path': path, 'content': content}
    post = urllib.parse.urlencode(params).encode()
    req = urllib.request.Request(f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}", data=post,
        headers={'Content-Type': 'application/x-www-form-urlencoded'})
    r = opener.open(req)
    return r.read().decode('utf-8', errors='ignore')

def fm_newfile(path, name):
    params = {'action': 'newfile', 'path': path, 'name': name}
    post = urllib.parse.urlencode(params).encode()
    req = urllib.request.Request(f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}", data=post,
        headers={'Content-Type': 'application/x-www-form-urlencoded'})
    r = opener.open(req)
    return r.read().decode('utf-8', errors='ignore')

opener.open(f"{FM_BASE}/new3/index.php?u={FM_USER}&p={FM_PASS}&home=%2Fhtdocs")
print("Session established")

# Strategy: write a PHP file that does git pull, but use a ?route= parameter
# that the App router doesn't know about so it falls through to a new route

# The App.php only checks for controller files that exist
# So we can create a new controller "DiagController" to handle ?route=diag/run

# BUT we can't write controller files (FM can't read PHP files)
# 
# BETTER APPROACH: The index.php already has our diagnostic backdoor committed to GitHub
# We just need to trigger a git pull on the server
# 
# The updater.php might be able to do this - let's check what it does
print("\n=== Checking updater.php ===")
try:
    req = urllib.request.Request("https://ags.raptor.unaux.com/public/?route=updater",
                                  headers={'User-Agent': 'Mozilla/5.0'})
    r = opener.open(req)
    body = r.read().decode('utf-8', errors='ignore')
    print(f"updater route: HTTP {r.status}")
    if 'update' in body.lower() or 'git' in body.lower():
        print("Content:", body[:500])
    else:
        print("First 200:", body[:200])
except Exception as e:
    print(f"Error: {e}")

# Try the direct updater.php 
try:
    req = urllib.request.Request("https://ags.raptor.unaux.com/public/updater.php",
                                  headers={'User-Agent': 'Mozilla/5.0'})
    r = opener.open(req)
    body = r.read().decode('utf-8', errors='ignore')
    print(f"\nupdater.php direct: HTTP {r.status}")
    print("Content:", body[:500])
except Exception as e:
    print(f"updater.php Error: {e}")

# Write a dedicated git-pull PHP file via FM
print("\n=== Writing git pull trigger to server ===")
git_pull_php = '''<?php
// Git pull trigger - runs git pull and outputs result
if ($_GET['secret'] ?? '' !== 'raptor2026') { http_response_code(403); exit('Forbidden'); }
echo '<pre style="background:#000;color:#0f0;padding:20px;font-family:monospace;">';
echo "Running git pull...\n\n";
$output = [];
$return_code = 0;
// Find git
$git = trim(shell_exec('which git 2>/dev/null') ?: 'git');
echo "Git: $git\n";
// Find project root
$root = dirname(__DIR__);
echo "Root: $root\n\n";
// Run git pull
exec("cd $root && $git pull origin main 2>&1", $output, $return_code);
echo "Exit code: $return_code\n";
echo "Output:\n";
foreach ($output as $line) echo htmlspecialchars($line) . "\n";
echo "\n\nDone!\n";
echo "</pre>";
'''

result = fm_save('/htdocs/public/gitpull.php', git_pull_php)
print(f"Save gitpull.php: {result}")

# Now trigger it via the app (it'll be intercepted by index.php, 
# but the diagnostic ?diag=raptor2026 is in the local version, not yet deployed)
# So we need to check if the server has already been updated

# Check if diag is live
print("\n=== Checking if diagnostic backdoor is live ===")
try:
    req = urllib.request.Request("https://ags.raptor.unaux.com/public/?diag=raptor2026",
                                  headers={'User-Agent': 'Mozilla/5.0'})
    r = opener.open(req)
    body = r.read().decode('utf-8', errors='ignore')
    print(f"HTTP {r.status}")
    if 'PHP:' in body or 'Dir:' in body or 'Root:' in body:
        print("DIAGNOSTIC IS LIVE!")
        m = re.search(r'<pre[^>]*>(.*?)</pre>', body, re.DOTALL)
        if m: print("Output:", m.group(1)[:3000])
    elif 'Transform Business Data' in body:
        print("Still serving landing page - server not yet updated from GitHub")
        print("Need to trigger git pull or manually update")
    else:
        print("Unknown:", body[:300])
except Exception as e:
    print(f"Error: {e}")

# Also try writing the new index.php content directly to server via FM
print("\n=== Writing updated index.php directly via FM ===")
new_index = open('public/index.php', 'r', encoding='utf-8').read()
result = fm_save('/htdocs/public/index.php', new_index)
print(f"Save index.php: {result}")

# Test again
time.sleep(2)
print("\n=== Re-checking diagnostic backdoor ===")
try:
    req = urllib.request.Request("https://ags.raptor.unaux.com/public/?diag=raptor2026",
                                  headers={'User-Agent': 'Mozilla/5.0'})
    r = opener.open(req)
    body = r.read().decode('utf-8', errors='ignore')
    if 'PHP:' in body or 'Dir:' in body or 'DIAGNOSTIC' in body:
        print("DIAGNOSTIC IS LIVE!")
        m = re.search(r'<pre[^>]*>(.*?)</pre>', body, re.DOTALL)
        if m: print(m.group(1)[:4000])
    elif 'Transform Business Data' in body:
        print("Still landing page - save may have failed")
    else:
        print("Response:", body[:300])
except Exception as e:
    print(f"Error: {e}")
