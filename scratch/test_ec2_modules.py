import urllib.request
import ssl

EC2 = "https://ags.raptor.unaux.com"

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

modules = [
    ("/public/index.php?route=followups/index", "Follow-ups"),
    ("/public/index.php?route=leads/index", "Leads Manager"),
    ("/public/index.php?route=crm_leads/index", "CRM Leads"),
    ("/public/index.php?route=customers/index", "Customers"),
    ("/public/index.php?route=communications/index", "Communications"),
    ("/public/index.php?route=meetings/index", "Meetings"),
    ("/public/index.php?route=dashboard/index", "Dashboard"),
    ("/public/index.php?route=auth/login", "Login"),
]

print(f"=== Testing EC2: {EC2} ===\n")

for path, label in modules:
    url = EC2 + path
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
        with urllib.request.urlopen(req, timeout=10, context=ctx) as resp:
            body = resp.read(2000).decode('utf-8', errors='ignore')
            status = resp.status
            has_error = any(x in body for x in ['500','Fatal error','Undefined variable','Call to undefined','Database Connection Error','Access denied'])
            icon = "ERR" if has_error or status >= 500 else "OK " if status < 400 else "RDR"
            print(f"[{icon}] {label}: HTTP {status} ({len(body)}b)")
            if has_error:
                # Show first error line
                for line in body.split('\n'):
                    if any(x in line for x in ['error','Error','Fatal','undefined','Undefined']):
                        print(f"       Error: {line.strip()[:150]}")
                        break
    except urllib.error.HTTPError as e:
        body = e.read(1000).decode('utf-8', errors='ignore')
        print(f"[ERR] {label}: HTTP {e.code}")
        for line in body.split('\n'):
            if any(x in line for x in ['error','Error','Fatal','undefined']):
                print(f"       Error: {line.strip()[:150]}")
                break
    except Exception as e:
        print(f"[ERR] {label}: {e}")
