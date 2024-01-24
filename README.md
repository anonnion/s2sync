# s2sync
Bi-directional Server to server folder and database sync

## How to use?

### Prepare folder and database for server - server sync:
```bash
php backup.php --dbname=<dbname> --dbhost=<dbhost> --dbuser=<dbuser> --dbpass=<dbpass> --path=<path>
```
### Send the files over http, protected with a bearer token generated every call:
```bash
php remote.php --serve --path=<path>
```
### You will get an output like:
```txt
Server started on  http://x.x.x.x:8082
Use token: ff64792e331feb445793e3fcc4ba2f0e
```
### Download and unzip the file on the second server:
```bash
php remote.php --fetch --token=<token> --path=<path> --remote=<remote>
```
### You will see an output like:
```txt
string(30) "backup_2024_01_24_11_49_33.sql"
string(30) "backup_2024_01_24_11_50_54.zip"
Downloading <path>/backup_2024_01_24_11_50_54.zip...
Downloading <path>/backup_2024_01_24_11_49_33.sql...
Backup files downloaded and processed successfully.
```
Enjoy
