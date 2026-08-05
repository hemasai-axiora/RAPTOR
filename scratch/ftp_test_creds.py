import ftplib
import io

FTP_HOST = "ftpupload.net"
FTP_USER = "ezyro_42571719"
FTP_PASS = "b441442"

print(f"Connecting to FTP {FTP_HOST}...")
try:
    ftp = ftplib.FTP(FTP_HOST, timeout=15)
    ftp.login(FTP_USER, FTP_PASS)
    print("FTP Login SUCCESS!")
    print("Current dir:", ftp.pwd())
    
    # List directories
    print("\nDirectory listing:")
    ftp.dir()
    
    # Try to enter htdocs
    try:
        ftp.cwd("/htdocs")
        print("\n[OK] In /htdocs")
        ftp.dir()
    except Exception as e:
        print(f"[Note] /htdocs: {e}")
    
    ftp.quit()
    print("\nFTP test complete!")
except Exception as e:
    print(f"FTP Error: {e}")
