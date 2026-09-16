# Visitor geolocation

The `/visitors/*` analytics reports (Times, Locations, Software, Performance) work
out of the box. The **Locations** report's country map and its Region/City
breakdowns now also work out of the box: mukurtu_core automatically downloads and
uses a free, redistributable geolocation database (DB-IP's "City Lite"), so no
account, license key, or setup step is required. A site that wants better
precision can still configure a MaxMind key — see below — which takes priority
automatically wherever it resolves an address.

## Why this needed fixing

`visitors_geoip` (the contrib module `/visitors/*` is built on) only ever looked
up a visit's coordinates (`location_latitude`/`location_longitude`), region and
city from a local MaxMind GeoLite2-City (or paid GeoIP2-City) `.mmdb` database
file, which needs a free but separately registered MaxMind account and license
key. Without one, every visit fell back to guessing a country from the visitor's
browser `Accept-Language` header alone (e.g. a browser set to "English (United
States)" was recorded as `US` regardless of where the visitor actually was) — no
coordinates, no region, no city, and a country that was not even real
geolocation. Since almost no site ever registered that key, the map and Region/City
tables were effectively always empty in practice.

## How the DB-IP fallback works

- `Drupal\mukurtu_core\Service\DbIpFallbackGeoIpService` decorates
  `visitors_geoip.lookup`. It tries the real (MaxMind-backed) service first; only
  when that has nothing does it fall back to a bundled DB-IP reader.
- `Drupal\mukurtu_core\Service\DbIpDownloadService` downloads DB-IP's monthly
  "City Lite" database (<https://db-ip.com>, licensed CC BY 4.0 — genuinely
  redistributable, unlike MaxMind's GeoLite2, whose EULA prohibits third-party
  redistribution outright) to the same directory MaxMind's own database would use
  (`visitors_geoip.settings:geoip_path`), under a different filename
  (`dbip-city-lite.mmdb`) so the two never collide.
- The download runs automatically: immediately when `visitors_geoip` is installed
  or re-installed (`hook_modules_installed()`), and as a monthly-ish safety net on
  cron (`hook_cron()`) that also catches a site that enabled `visitors_geoip`
  before this feature existed. Neither ever fails an install or a cron run over a
  network hiccup — failures are logged, not thrown.
- To force it immediately (a fresh DDEV site, or right after deploying this
  feature to an existing site) rather than waiting for the next cron run:
  ```
  drush mukurtu:geoip:download-dbip
  ```

DB-IP's City Lite data is coarser than MaxMind's own City database — solid at
country/region level, weaker on exact city/lat-long precision, particularly for
mobile carriers, VPNs, and CGNAT'd ranges. It is what makes the map and
Region/City tables show real (if approximate) data instead of nothing, for every
Mukurtu site, with no setup step.

## Configuring MaxMind for better precision (optional)

1. Register for a free MaxMind account and generate a license key:
   <https://www.maxmind.com/en/geolite2/signup>
2. On the site, go to **Visitors → GeoIP settings**
   (`/admin/config/system/visitors/geoip`) and enter:
   - **MaxMind License Key** — the key from step 1.
   - **GeoIP Database path** — a directory, relative to the Drupal root, that the
     web server can write to. Both MaxMind's and DB-IP's database files live here.
3. Download the database:
   ```
   drush visitors:download:city
   ```
   This only needs to be re-run when MaxMind ships a new GeoLite2 release (monthly);
   it is not a one-time setup step to forget about.
4. New visits prefer MaxMind's result automatically from this point on; DB-IP is
   only ever consulted when MaxMind's lookup has nothing (an IP outside its
   coverage, or the key not yet configured). To backfill visits already recorded
   before the key was configured, either run:
   ```
   drush visitors:rebuild:location
   ```
   or use the equivalent form at `/admin/config/system/visitors/rebuild-location`.
   This only re-resolves through whichever service is currently active (MaxMind if
   configured, DB-IP otherwise) — it does not distinguish which source a
   already-filled-in row came from.

## Limitations

- `Visitors::doLocation()` only overwrites a visit's fields when a geoip lookup
  (MaxMind's, or now DB-IP's) succeeds for that visit's IP; a failed lookup
  silently leaves that visit's earlier locale-guessed country in place rather than
  clearing it.
- Both MaxMind's free tier and DB-IP's Lite tier have accuracy gaps for mobile
  carriers, VPNs, and CGNAT'd IP ranges — expect some visits to still resolve to
  only a country or region, or not at all, even with one of them configured.
