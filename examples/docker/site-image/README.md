# Site image

Your own image, built on the official Mukurtu image, with contributed modules that need
Composer libraries. Modules that don't need them, and your own modules and themes, are
simpler in the `custom` folders of the other examples.

1. List the modules in `composer-packages.txt`, one per line, such as `drupal/address`.
2. Build on the exact Mukurtu version you run:

   ```
   docker build --build-arg MUKURTU_IMAGE=ghcr.io/mukurtucms/mukurtu:4.0.8-r1 -t my-mukurtu:4.0.8-r1 .
   ```

3. Set `MUKURTU_IMAGE=my-mukurtu:4.0.8-r1` in your Compose example's `.env`, and run
   `docker compose up -d`.

Rebuild the image for every Mukurtu update. Details, including how updates work, are in
"Adding modules and themes" in [docs/docker.md](../../../docs/docker.md#adding-modules-and-themes).
