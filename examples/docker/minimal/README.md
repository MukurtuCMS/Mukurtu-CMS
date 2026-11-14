# Try Mukurtu

```
docker compose up -d
```

Wait about 5 minutes, then open http://localhost:8080 (`localhost`, not `127.0.0.1`) and sign in
as `admin` / `Mukurtu-Test-1`. The first start installs a whole site, so be patient; follow it
with `docker compose logs -f mukurtu`.

`docker compose stop` stops it, `docker compose down -v` deletes everything. The passwords are
throwaway and email is not set up, so this is only for trying it: for a real site use
[`../single-site`](../single-site).
