import ftplib
import io

FTP_HOST = "ftpupload.net"
FTP_USER = "ezyro_42571719"
FTP_PASS = "b441442"

print("Connecting to FTP...")
ftp = ftplib.FTP(FTP_HOST, timeout=15)
ftp.login(FTP_USER, FTP_PASS)
print("FTP Login SUCCESS!")
ftp.cwd("/htdocs")

# Download and read rtest.php and test123.php
for f in ['rtest.php', 'test123.php']:
    try:
        buf = io.BytesIO()
        ftp.retrbinary(f"RETR {f}", buf.write)
        print(f"\n=== {f} ===")
        print(buf.getvalue().decode('utf-8', errors='ignore'))
    except Exception as e:
        print(f"[Error] {f}: {e}")

# Also check app/config folder
try:
    ftp.cwd("/htdocs/app/config")
    print("\n=== /htdocs/app/config contents ===")
    ftp.dir()
    # Try to download config.php itself from live server
    buf = io.BytesIO()
    ftp.retrbinary("RETR config.php", buf.write)
    content = buf.getvalue().decode('utf-8', errors='ignore')
    print("\n=== config.php on server ===")
    print(content[:2000])
except Exception as e:
    print(f"[Error] config folder: {e}")

ftp.quit()
