import binascii
from Crypto.Cipher import AES

a = binascii.unhexlify("f655ba9d09a112d4968c63579db590b4") # key/iv?
b = binascii.unhexlify("98344c2eee86c3994890592585b49f80")
c = binascii.unhexlify("f2e4b059332eb7200303aecce5d43a10")

# slowAES mode 2 = AES-128 CBC mode
try:
    cipher = AES.new(a, AES.MODE_CBC, b)
    decrypted = cipher.decrypt(c)
    print("Decrypted hex:", decrypted.hex())
except Exception as e:
    print("Crypto Error:", e)
