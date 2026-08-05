import requests
import re
import urllib3
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

s = requests.Session()
s.verify = False

url_login = "https://98.94.227.211/public/index.php?route=auth/login"
r = s.get(url_login)
csrf_match = re.search(r'name="csrf_token" value="([^"]+)"', r.text)
csrf = csrf_match.group(1) if csrf_match else ""

payload = {
    "email": "employee@raptor.local",
    "password": "Raptor@12345",
    "csrf_token": csrf
}
r_post = s.post(url_login, data=payload)
print(f"Login status: {r_post.status_code}")

routes = [
    "followups/index",
    "leads/index",
    "customers/index",
    "communications/index",
    "meetings/index"
]

for route in routes:
    u = f"https://98.94.227.211/public/index.php?route={route}"
    r_target = s.get(u)
    print(f"--- ROUTE: {route} -> Status: {r_target.status_code} ---")
    print(r_target.text[:500])
    print("\n")
