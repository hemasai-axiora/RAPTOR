import ftplib

hosts = ['ftpupload.net', 'ftp.unaux.com', 'ags.raptor.unaux.com', 'raptor.unaux.com', '98.94.227.211']
user = 'ezyro_42571719'
pas = 'J2ZyVl5bRQ'

for h in hosts:
    print(f"Testing FTP host: {h}...")
    try:
        f = ftplib.FTP(h, timeout=5)
        f.login(user, pas)
        print(f"  ✔ SUCCESS ON {h}!")
        print("  Current dir:", f.pwd())
        f.quit()
        break
    except Exception as e:
        print(f"  Failed on {h}: {e}")
