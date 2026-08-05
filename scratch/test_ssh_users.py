import subprocess

key_path = r"c:\Users\Axiora User-30\Desktop\RAPTOR-main\RAPTOR-main\temp_ssh\id_temp"
ip = "98.94.227.211"

usernames = [
    "ubuntu", "ec2-user", "debian", "centos", "admin", "root", "raptor", "unaux", "www-data"
]

print("=== Testing SSH Usernames ===")
for user in usernames:
    cmd = f'ssh -i "{key_path}" -o StrictHostKeyChecking=no -o ConnectTimeout=4 {user}@{ip} "echo SUCCESS_SSH_{user}"'
    res = subprocess.run(cmd, shell=True, capture_output=True, text=True)
    out = res.stdout + res.stderr
    if "SUCCESS_SSH" in out:
        print(f"🎉 SUCCESS SSH USER: {user}!")
        print("Output:", out)
        break
    else:
        print(f"Failed {user}: {out.strip()}")
