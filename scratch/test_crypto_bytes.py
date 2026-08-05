import binascii
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
from cryptography.hazmat.backends import default_backend

a = binascii.unhexlify("f655ba9d09a112d4968c63579db590b4")
b = binascii.unhexlify("98344c2eee86c3994890592585b49f80")
c = binascii.unhexlify("f2e4b059332eb7200303aecce5d43a10")

try:
    cipher = Cipher(algorithms.AES(a), modes.CBC(b), backend=default_backend())
    decryptor = cipher.decryptor()
    decrypted = decryptor.update(c) + decryptor.finalize()
    cookie_val = decrypted.hex()
    print("Computed __test cookie:", cookie_val)
except Exception as e:
    print("Crypto Error:", e)
