## Run Service:
- Generate private key (used for decryption, store safely!):  
  `openssl genrsa -out private.pem 2048`
- Extract public key (used for encryption) and store it as `./keys/public.pem`  
  `openssl rsa -in private.pem -outform PEM -pubout -out keys/public.pem`
- Copy files onto a Webspace, or start locally using docker:  
  `docker run -it --rm --name PublicEncryptedStorage -p 80:80 -v $(pwd):/var/www/html php:8-apache`
- Also please setup transport encryption (SSL/TLS)!

## Store data:
Send JSON via HTML Form.

# View data:
Open http://localhost:80/list.html

## Debugging / Development notes:
- Store data: `curl -X POST --data-urlencode 'storeJson={"name":"Alice","role":"admin"}' http://localhost/api.php`
- List stored: `curl -X POST --data-urlencode 'listStored' http://localhost/api.php`
- Read one stored: `curl -X POST -F 'fetchStored=6945564cc45ee30c44a7f29098a1397e' -F 'privKey=@/home/me/Desktop/private.pem' http://localhost/api.php`
- Read all stored: `curl -X POST -F 'fetchStored=' -F 'privKey=@/home/me/Desktop/private.pem' http://localhost/api.php`

