# Try Mukurtu

1. Open a terminal in this folder.
2. Run: `docker compose up -d`
3. Wait about 5 minutes, then open **http://localhost:8080**
   (type `localhost`, not `127.0.0.1`).
4. Sign in: `admin` / `Mukurtu-Test-1`

Stop: `docker compose stop`   Start again: `docker compose up -d`
Delete everything and start over: `docker compose down -v`

The passwords are throwaway and email is not set up, so this is only for trying it. For a
real site use [`../single-site`](../single-site). If something breaks, the output of
`docker compose logs mukurtu` shows what happened.
