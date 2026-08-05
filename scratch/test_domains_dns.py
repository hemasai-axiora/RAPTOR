import urllib.request
import ssl
import socket

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

domains = [
    "http://raptor.unaux.com",
    "http://ags.raptor.unaux.com",
    "https://ags.raptor.unaux.com",
    "http://unaux.com"
]

print("=== Scanning Domain IPs and Headers ===")
for d in domains:
    try:
        host = d.split("//")[1].split("/")[0]
        ip = socket.gethostbyname(host)
        print(f"\nDomain: {d} -> IP: {ip}")
        req = urllib.request.Request(d, headers={'User-Agent': 'Mozilla/5.0'})
        r = urllib.request.urlopen(req, context=ctx, timeout=5)
        print(f"  HTTP {r.status} | Server Header: {r.headers.get('Server')}")
    except Exception as e:
        print(f"  Error on {d}: {e}")
