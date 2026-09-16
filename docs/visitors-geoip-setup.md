# Setting up real visitor geolocation

The `/visitors/*` analytics reports (Times, Locations, Software, Performance) work
out of the box, but the **Locations** report's country map and its Region/City
breakdowns stay empty — "No location data for the selected dates" — until a MaxMind
license key is configured. This is expected, not a bug: without that key, precise
per-visit coordinates are never collected at all.

## Why the map is empty by default

`visitors_geoip` only looks up a visit's coordinates
(`location_latitude`/`location_longitude`), region and city from a local MaxMind
GeoLite2-City (or paid GeoIP2-City) `.mmdb` database file. Without that file, every
visit falls back to guessing a country from the visitor's browser `Accept-Language`
header alone (e.g. a browser set to "English (United States)" is recorded as
`US` regardless of where the visitor actually is) — no coordinates, no region, no
city. The Country table stays populated (from that same locale guess), but it is
**not real geolocation**, and the map and Region/City tables, which need actual
coordinates, have nothing to plot.

## Setting up MaxMind (free)

1. Register for a free MaxMind account and generate a license key:
   <https://www.maxmind.com/en/geolite2/signup>
2. On the site, go to **Visitors → GeoIP settings**
   (`/admin/config/system/visitors/geoip`) and enter:
   - **MaxMind License Key** — the key from step 1.
   - **GeoIP Database path** — a directory, relative to the Drupal root, that the
     web server can write to. The database file downloads here.
3. Download the database:
   ```
   drush visitors:download:city
   ```
   This only needs to be re-run when MaxMind ships a new GeoLite2 release (monthly);
   it is not a one-time setup step to forget about.
4. New visits are geolocated automatically from this point on. To backfill visits
   already recorded before the key was configured, either run:
   ```
   drush visitors:rebuild:location
   ```
   or use the equivalent form at `/admin/config/system/visitors/rebuild-location`.

## Limitations even with MaxMind configured

- GeoLite2's free tier has known accuracy gaps for mobile carriers, VPNs, and
  CGNAT'd IP ranges — expect some visits to still resolve to only a country or
  region, or not at all.
- `Visitors::doLocation()` only fills in geolocated fields when the MaxMind lookup
  succeeds for that visit's IP; a failed lookup silently leaves that visit's
  earlier locale-guessed country in place rather than clearing it.
