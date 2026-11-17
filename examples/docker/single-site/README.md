# Single site

One Mukurtu site and its MariaDB database, with the passwords in secret files, a read-only
container, memory limits and rotated logs.

```
./generate-secrets.sh     # creates .env and secrets/ (random passwords); prints the admin password
docker compose up -d      # the first start installs Mukurtu: a few minutes
```

Open http://localhost:8080 (`localhost`, not `127.0.0.1`) and sign in as `admin`. Edit `.env`
first for a different image version, host name or admin email. For a real site, put a reverse
proxy that provides HTTPS in front of port 8080 and set `MUKURTU_HOST` to the domain.

## Backups

`backup.sh` backs up the running site: the database, the uploaded files, and this folder's
settings and passwords. Each run writes a folder in `backups/` named after the date and
time, and deletes backups older than 14 days (`KEEP_DAYS=30 ./backup.sh` keeps 30).

```
./backup.sh
```

**Every night.** Run `crontab -e`, and add this line with the full path of this folder:

```
30 3 * * * /root/mukurtu-docker/examples/docker/single-site/backup.sh >>/var/log/mukurtu-backup.log 2>&1
```

It runs at 3:30 every morning, server time. Check `/var/log/mukurtu-backup.log` now and
then: each run ends with `backup written to` or `backup failed`.

**Copy the backups off the server.** If the server or the hosting account is lost, the
backups on it are lost too. For example, run this on your own computer, every week or after
big changes; it copies only the new backups:

```
rsync -a root@<server>:/root/mukurtu-docker/examples/docker/single-site/backups/ mukurtu-backups/
```

The backups hold the site's passwords, so keep the copies private.

**Restore** into a new, empty site. `restore.sh` refuses to overwrite a site.

1. Put the example in a new folder (see Quick start in docs/docker.md), and copy the
   backup folder into it.
2. Unpack the backup's settings: `tar xzf <backup>/site.tgz`. On the server the backup came
   from, then change `name:` and the port in `docker-compose.yml`, or the new site uses the
   old site's data (see "Moving a site to another host" in docs/docker.md).
3. Run `./restore.sh <backup>`.
4. Check `docker compose logs -f mukurtu`: it says `existing installation found`.

Test a restore once, before you need it.

## Deleting the site

`docker compose down -v` deletes the site, its database and its files. Settings are in
[docs/docker.md](../../../docs/docker.md).
