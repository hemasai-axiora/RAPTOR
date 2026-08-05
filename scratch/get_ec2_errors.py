import urllib.request
import ssl
import re

EC2 = "https://ags.raptor.unaux.com"
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

def check(path, label):
    url = EC2 + path
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
        with urllib.request.urlopen(req, timeout=10, context=ctx) as resp:
            body = resp.read(10000).decode('utf-8', errors='ignore')
            status = resp.status
            # Strip HTML tags for readable output
            clean = re.sub(r'<[^>]+>', '', body)
            clean = re.sub(r'\s+', ' ', clean).strip()
            print(f"\n=== {label} HTTP {status} ===")
            print(clean[:1500])
    except urllib.error.HTTPError as e:
        body = e.read(5000).decode('utf-8', errors='ignore')
        clean = re.sub(r'<[^>]+>', '', body)
        clean = re.sub(r'\s+', ' ', clean).strip()
        print(f"\n=== {label} HTTP {e.code} ===")
        print(clean[:1500])
    except Exception as e:
        print(f"\n=== {label} ERROR: {e} ===")

# Check exact errors
check("/public/index.php?route=followups/index", "Follow-ups")
check("/public/index.php?route=leads/index", "Leads")
check("/public/index.php?route=meetings/index", "Meetings")
