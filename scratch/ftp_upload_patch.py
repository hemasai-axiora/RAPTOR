import ftplib
import os

FTP_HOST = "ftpupload.net"
FTP_USER = "ezyro_42571719"
FTP_PASS = "J2ZyVl5bRQ"

zip_file_path = r"c:\Users\Axiora User-30\Desktop\update.zip"

print(f"Connecting to FTP server {FTP_HOST}...")
try:
    ftp = ftplib.FTP(FTP_HOST)
    ftp.login(FTP_USER, FTP_PASS)
    print("FTP Login Successful!")
    
    print("\nCurrent Directory:", ftp.pwd())
    ftp.cwd("/htdocs")
    print("Changed directory to /htdocs")
    
    print("\nListing /htdocs:")
    ftp.dir()
    
    # Upload update.zip
    print(f"\nUploading {zip_file_path} to /htdocs/update.zip...")
    with open(zip_file_path, "rb") as f:
        ftp.storbinary("STOR update.zip", f)
    print("✔ Uploaded update.zip successfully!")
    
    # Also check if public directory exists
    try:
        ftp.cwd("/htdocs/public")
        print("\nChanged directory to /htdocs/public")
        
        # Upload key patched files directly to public / app folders if needed
        files_to_upload = [
            (r"c:\Users\Axiora User-30\Desktop\RAPTOR-main\RAPTOR-main\public\index.php", "index.php"),
            (r"c:\Users\Axiora User-30\Desktop\RAPTOR-main\RAPTOR-main\public\patch.php", "patch.php"),
        ]
        for local, remote in files_to_upload:
            if os.path.exists(local):
                with open(local, "rb") as lf:
                    ftp.storbinary(f"STOR {remote}", lf)
                print(f"✔ Uploaded {remote} to /htdocs/public/{remote}")
    except Exception as e:
        print("Notice for /htdocs/public:", e)
        
    ftp.quit()
    print("\nFTP Process Complete!")
except Exception as e:
    print("FTP Error:", e)
