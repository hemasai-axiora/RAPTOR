import urllib.request

url = "http://raptor.unaux.com/public/index.php"

req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
try:
    r = urllib.request.urlopen(req)
    html = r.read().decode('utf-8')
    print("=== AES JS HTML ===")
    print(html)
except Exception as e:
    print("Error:", e)
