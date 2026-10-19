# Single site (production-shaped)

One Mukurtu site and its MariaDB database, with the passwords in secret files, a
read-only container, and memory limits.

```
./generate-secrets.sh        # creates .env and secrets/ (random passwords)
docker compose up -d         # first start installs Mukurtu: a few minutes
```

Then open http://localhost:8080 (use `localhost`, not `127.0.0.1`) and sign in as
`admin` with the password `generate-secrets.sh` printed (it is in
`secrets/admin_password.txt`).

Edit `.env` first if you want a different image version, host name, or admin email.
For a real site, put a reverse proxy that provides HTTPS in front of port 8080 and set
`MUKURTU_HOST` to the site's domain. Every setting is documented in
[docs/docker.md](../../../docs/docker.md).

`docker compose down -v` deletes the site, its database and its files.
