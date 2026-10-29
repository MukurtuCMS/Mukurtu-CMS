# Single site

One Mukurtu site and its MariaDB database, with the passwords in secret files, a read-only
container and memory limits.

```
./generate-secrets.sh     # creates .env and secrets/ (random passwords); prints the admin password
docker compose up -d      # the first start installs Mukurtu: a few minutes
```

Open http://localhost:8080 (`localhost`, not `127.0.0.1`) and sign in as `admin`. Edit `.env`
first for a different image version, host name or admin email. For a real site, put a reverse
proxy that provides HTTPS in front of port 8080 and set `MUKURTU_HOST` to the domain.

`docker compose down -v` deletes the site, its database and its files. Settings are in
[docs/docker.md](../../../docs/docker.md).
