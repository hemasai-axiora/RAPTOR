import socket
import urllib.request
import ssl

EC2_IP = "98.94.227.211"
EC2_HOST = "ags.raptor.unaux.com"

print(f"=== Checking EC2 Server: {EC2_HOST} ({EC2_IP}) ===\n")

# Check ports
ports = [80, 443, 22, 3306, 8080]
for port in ports:
    s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    s.settimeout(3)
    result = s.connect_ex((EC2_IP, port))
    s.close()
    status = "OPEN" if result == 0 else "CLOSED"
    print(f"Port {port}: {status}")

print("\n--- HTTP/HTTPS Responses ---")

# Try HTTP
for url in [
    f"http://{EC2_IP}/public/index.php",
    f"http://{EC2_IP}/",
    f"https://{EC2_HOST}/public/index.php",
    f"http://{EC2_HOST}/public/index.php",
]:
    try:
        ctx = ssl.create_default_context()
        ctx.check_hostname = False
        ctx.verify_mode = ssl.CERT_NONE
        req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0", "Host": EC2_HOST})
        with urllib.request.urlopen(req, timeout=5, context=ctx) as resp:
            body = resp.read(500).decode('utf-8', errors='ignore')
            print(f"[OK] {url} -> HTTP {resp.status} ({len(body)}b)")
            if 'raptor' in body.lower() or 'login' in body.lower():
                print(f"     Contains: RAPTOR app content!")
    except urllib.error.HTTPError as e:
        print(f"[HTTP {e.code}] {url}")
    except Exception as e:
        print(f"[ERR] {url}: {type(e).__name__}: {str(e)[:80]}")
