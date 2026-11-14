# Docker examples

Three ready-to-run Docker Compose examples for the Mukurtu CMS image, and a site image example. Each folder is
self-contained: open it, follow its README, run `docker compose up -d`.

| Example | Use it to | Passwords | Needs |
| --- | --- | --- | --- |
| [`minimal/`](minimal) | Try Mukurtu in a couple of minutes. | Throwaway, written in the file. | Nothing. |
| [`single-site/`](single-site) | Run one site the way you would in production. | Random, in secret files. | `./generate-secrets.sh` |
| [`multi-tenant/`](multi-tenant) | Host several independent sites on one machine, each at its own domain. | Random, in `.env`. | `./generate-secrets.sh` |
| [`site-image/`](site-image) | Add contributed modules that need Composer libraries, by building your own image. | Not applicable. | `docker build` |

Your own modules and themes go in each example's `custom` folder. See "Adding modules
and themes" in [docs/docker.md](../../docs/docker.md#adding-modules-and-themes).

All three use the published image `ghcr.io/mukurtucms/mukurtu`; edit the image line (or
`MUKURTU_IMAGE` in `.env`) to pin an exact version. How the image is built is in
[docs/docker-build.md](../../docs/docker-build.md); every setting it accepts is in
[docs/docker.md](../../docs/docker.md).

The generated `.env` files and `secrets/` folders hold real passwords. They are listed in
`.gitignore` here and must never be committed.
