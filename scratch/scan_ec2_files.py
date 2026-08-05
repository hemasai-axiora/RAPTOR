import urllib.request
import ssl

EC2 = "https://ags.raptor.unaux.com"
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

paths = [
    "/bin/migrate.php",
    "/bin/alter_employees.php",
    "/deploy/deploy.sh",
    "/deploy/health-check.sh",
    "/public/index.php",
    "/public/api/session-status.php",
    "/public/patch.php",
    "/public/update.php",
    "/public/deploy.php",
    "/public/git.php",
    "/public/pull.php",
    "/git-pull.php",
    "/pull.php",
    "/update.php"
]

print("=== Scanning EC2 Files ===")
for p in paths:
    url = EC2 + p
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
        with urllib.request.urlopen(req, timeout=5, context=ctx) as resp:
            body = resp.read(200).decode('utf-8', errors='ignore')
            print(f"[HTTP {resp.status}] {p} ({len(body)}b) -> {body[:100].strip()}")
    except urllib.error.HTTPError as e:
        print(f"[HTTP {e.code}] {p}")
    except Exception as e:
        print(f"[ERR {p}]: {e}")
