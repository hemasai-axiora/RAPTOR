import ftplib
import io

FTP_HOST = "ftpupload.net"
FTP_USER = "ezyro_42571719"
FTP_PASS = "b441442"

print("Connecting to FTP...")
ftp = ftplib.FTP(FTP_HOST, timeout=15)
ftp.login(FTP_USER, FTP_PASS)
print("[OK] Connected!")

# Read root .htaccess (may contain SetEnv DB credentials)
ftp.cwd("/")
for filename in ['.htaccess', '.override', '.env', 'wp-config.php']:
    try:
        buf = io.BytesIO()
        ftp.retrbinary(f"RETR {filename}", buf.write)
        content = buf.getvalue().decode('utf-8', errors='ignore')
        print(f"\n=== {filename} ===")
        print(content)
    except Exception as e:
        print(f"[Skip] {filename}: {e}")

# Check htdocs root
ftp.cwd("/htdocs")
for filename in ['.env', '.htaccess', 'wp-config.php', '.env.local']:
    try:
        buf = io.BytesIO()
        ftp.retrbinary(f"RETR {filename}", buf.write)
        content = buf.getvalue().decode('utf-8', errors='ignore')
        print(f"\n=== htdocs/{filename} ===")
        print(content)
    except Exception as e:
        print(f"[Skip] htdocs/{filename}: {e}")

ftp.quit()
