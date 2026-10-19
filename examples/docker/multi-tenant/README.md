# Several sites on one host (multi-tenant)

Two independent Mukurtu sites behind a Caddy proxy that routes each domain to its site and
handles HTTPS. Each site is its own container with its own database and files. This is not
Drupal's "multisite" feature.

```
./generate-secrets.sh        # creates .env with random passwords
docker compose up -d         # the sites install on first start: a few minutes
```

To try it on your machine, open https://a.localhost:8443 and https://b.localhost:8443
(accept the certificate warning) and sign in as `admin` with the passwords
`generate-secrets.sh` printed. For real domains, edit `.env`: set `HTTP_PORT=80`,
`HTTPS_PORT=443`, the two `SITE_*_HOST` domains, and `ACME_EMAIL`.

The header of `docker-compose.yml` explains how to add a site. Every setting is documented
in [docs/docker.md](../../../docs/docker.md) ("Hosting many sites").

`docker compose down -v` deletes everything.
