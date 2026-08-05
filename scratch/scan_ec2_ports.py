import socket

ip = "98.94.227.211"
ports = [22, 80, 443, 3306, 8000, 8080, 8081, 9000, 9090, 2222, 10000]

print(f"=== Port Scan on {ip} ===")
for p in ports:
    s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    s.settimeout(2)
    res = s.connect_ex((ip, p))
    if res == 0:
        print(f"[OPEN] Port {p}")
    else:
        print(f"[CLOSED] Port {p}")
    s.close()
