import urllib.request
import urllib.parse
import http.cookiejar
import re

cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

resp = opener.open('http://localhost:80/public/index.php?route=auth/login')
html = resp.read().decode('utf-8')
match = re.search(r'name="csrf_token"\s+value="([^"]+)"', html)
csrf_token = match.group(1) if match else ''

post_data = urllib.parse.urlencode({
    'email': 'admin@raptor.local',
    'password': 'Raptor@12345',
    'csrf_token': csrf_token
}).encode('utf-8')

req = urllib.request.Request('http://localhost:80/public/index.php?route=auth/login', data=post_data, headers={
    'User-Agent': 'Mozilla/5.0'
})

login_resp = opener.open(req)
body = login_resp.read().decode('utf-8')
print("=== LOGIN RESPONSE BODY ===")
for line in body.splitlines():
    if "invalid" in line.lower() or "error" in line.lower() or "alert" in line.lower() or "feedback" in line.lower():
        print(line.strip())
