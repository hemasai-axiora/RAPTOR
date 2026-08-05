import shutil
import os

src = r"c:\Users\Axiora User-30\Desktop\RAPTOR-main\RAPTOR-main\update.zip"

destinations = [
    os.path.expanduser(r"~\Desktop\update.zip"),
    os.path.expanduser(r"~\Downloads\update.zip"),
    r"C:\Users\Axiora User-30\Downloads\update.zip",
    r"C:\Users\Axiora User-30\Desktop\update.zip",
    r"c:\Users\Axiora User-30\Desktop\RAPTOR-main\update.zip"
]

print("=== Copying update.zip to User Folders ===")
for d in destinations:
    try:
        folder = os.path.dirname(d)
        if os.path.exists(folder):
            shutil.copy(src, d)
            print(f"[OK] Copied ({os.path.getsize(d)} bytes) -> {d}")
        else:
            print(f"[MISSING FOLDER] {folder}")
    except Exception as e:
        print(f"[ERROR] {d}: {e}")
