# Docker examples

Three ready-to-run Docker Compose examples for the Mukurtu CMS image. Each folder is
self-contained: open it, follow its README, run `docker compose up -d`.

| Example | Use it to | Passwords | Needs |
| --- | --- | --- | --- |
| [`minimal/`](minimal) | Try Mukurtu in a couple of minutes. | Throwaway, written in the file. | Nothing. |
| [`single-site/`](single-site) | Run one site the way you would in production. | Random, in secret files. | `./generate-secrets.sh` |
| [`multi-tenant/`](multi-tenant) | Host several independent sites on one machine, each at its own domain. | Random, in `.env`. | `./generate-secrets.sh` |

All three use the published image `ghcr.io/<owner>/mukurtu`; edit the image line (or
`MUKURTU_IMAGE` in `.env`) to pin the version you want. How the image is built is in
[docs/docker-build.md](../../docs/docker-build.md); every setting it accepts is in
[docs/docker.md](../../docs/docker.md).

The generated `.env` files and `secrets/` folders hold real passwords. They are listed in
each example's `.gitignore` and must never be committed.
