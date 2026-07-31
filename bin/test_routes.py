import urllib.request
import urllib.parse
import http.cookiejar
import re

cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

# 1. Get Login Page & CSRF Token
resp = opener.open('http://localhost:80/public/index.php?route=auth/login')
html = resp.read().decode('utf-8')
match = re.search(r'name="csrf_token"\s+value="([^"]+)"', html)
csrf_token = match.group(1) if match else ''

print(f"1. CSRF Token: {csrf_token}")

# 2. Login
post_data = urllib.parse.urlencode({
    'email': 'admin@raptor.local',
    'password': 'Raptor@12345',
    'csrf_token': csrf_token
}).encode('utf-8')

req = urllib.request.Request('http://localhost:80/public/index.php?route=auth/login', data=post_data, headers={
    'User-Agent': 'Mozilla/5.0',
    'Content-Type': 'application/x-www-form-urlencoded'
})

login_resp = opener.open(req)
login_html = login_resp.read().decode('utf-8')
title_match = re.search(r'<title>(.*?)</title>', login_html, re.IGNORECASE)
print(f"2. Post-Login URL: {login_resp.geturl()} | Title: {title_match.group(1) if title_match else 'None'}")

# 3. Test account_sales/index
req1 = opener.open('http://localhost:80/public/index.php?route=account_sales/index')
html1 = req1.read().decode('utf-8')
title1 = re.search(r'<title>(.*?)</title>', html1, re.IGNORECASE)
print(f"3. account_sales/index Status: {req1.getcode()} | Title: {title1.group(1) if title1 else 'None'}")

# 4. Test website_analytics/index
req2 = opener.open('http://localhost:80/public/index.php?route=website_analytics/index')
html2 = req2.read().decode('utf-8')
title2 = re.search(r'<title>(.*?)</title>', html2, re.IGNORECASE)
print(f"4. website_analytics/index Status: {req2.getcode()} | Title: {title2.group(1) if title2 else 'None'}")
