# Several sites on one host

Two independent Mukurtu sites behind a Caddy proxy that routes each domain to its site and
handles HTTPS. Each site is its own container with its own database and files. This is not
Drupal's "multisite" feature.

```
./generate-secrets.sh     # creates .env with random passwords; prints each admin password
docker compose up -d      # the sites install on first start: a few minutes
```

Open https://a.localhost:8443 and https://b.localhost:8443 (accept the certificate warning) and
sign in as `admin`. For real domains edit `.env`: `HTTP_PORT=80`, `HTTPS_PORT=443`, the two
`SITE_*_HOST` domains and `ACME_EMAIL`. The header of `docker-compose.yml` says how to add a site.

`docker compose down -v` deletes everything. Settings and sizing are in
[docs/docker.md](../../../docs/docker.md) ("Hosting many sites").
