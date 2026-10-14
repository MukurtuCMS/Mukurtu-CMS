# Visitor geolocation

The `/visitors/*` analytics reports (Times, Locations, Software, Performance) work
out of the box. The **Locations** report's visitor map and its Region/City
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
  redistribution outright) to `private://mukurtu_core_geoip/dbip-city-lite.mmdb`
  when `private://` is configured, else `public://mukurtu_core_geoip/…` —
  `Drupal\mukurtu_core\Service\DbIpDatabaseLocator` resolves which. Deliberately
  not `visitors_geoip.settings:geoip_path` (where a manually-run MaxMind download
  lands): that is an admin-configured path outside the web root with no
  guarantee of being writable by whatever user runs PHP, and on at least one real
  hosting model — Tugboat's previews, where the codebase is root-owned from the
  build phase but `drush updb -y` and cron both run as `www-data` — it is not.
  If `private://` itself turns out unwritable (its own permissions are only
  admin-guaranteed, not Drupal-guaranteed the way `public://` is — again seen
  live on a Tugboat preview, where only the *update* phase, not the *build*
  phase, re-chowns it, so a stale ownership from an earlier build can persist),
  the download falls back to `public://` automatically, which a successful
  Drupal install always guarantees is writable.
- The download runs automatically: immediately when `visitors_geoip` is installed
  or re-installed (`hook_modules_installed()`); via an update hook
  (`mukurtu_core_update_40203`) for a site that already has `visitors_geoip`
  installed and is updating to a mukurtu_core version carrying this feature — the
  case `hook_modules_installed()` cannot reach, since the module is not being
  freshly installed; and as a monthly-ish safety net on cron (`hook_cron()`).
  None of the three ever fails an install, an update run, or a cron run over a
  network hiccup or a filesystem permission problem — failures are logged, not
  thrown.
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

## Past visits

Visits saved before a site had a geolocation database have only a country guessed
from the browser language, and no region, city or coordinates. So do the visits
that were saved while DB-IP's missing region codes were making every geolocated
visit fail (see Limitations). `Drupal\mukurtu_core\Service\VisitorsLocationBackfill`
re-resolves them from their stored IP addresses automatically, through
visitors_geoip's own `RebuildLocationService`:

- It starts once, on the first cron run that finds a geolocation database
  installed, and covers every visit up to the highest `visitors_id` at that
  moment.
- Each cron run does about 20 seconds of work. Progress is kept in the
  `mukurtu_core.visitors_location_backfill` state key, and the status report shows
  how many addresses are left.
- It walks the table by id rather than repeatedly asking for "rows still missing
  coordinates", so addresses no database can place (private ranges, for example)
  are looked at once and do not stall it.

To run it again (after adding a MaxMind key, say), first clear the coordinates of
the rows to re-resolve, then delete the state key; the next cron run starts over:

```
drush state:delete mukurtu_core.visitors_location_backfill
```

## The visitor map

The Locations report shows a map below its Continent and Country tables, and its
country and region drill-downs show one above their table
(`Drupal\mukurtu_core\Service\VisitorsCountryMap`). It has
one circle per city, sized by unique visitors, at the average position of that
city's visits, for the report's selected dates.

A city with fewer unique visitors than the threshold (default 3) is not plotted
on its own: it is combined with the rest of its region, and a region still under
the threshold with the rest of its country. Anything still under it at country
level is left off the map (the tables still count it). This keeps a city with one
or two visitors from being read off the map. Admins set the threshold on the
GeoIP settings form (`/admin/config/system/visitors/geoip`); it is stored in
`mukurtu_core.visitors_map:city_threshold`.

When the DB-IP database is installed, the map shows the "IP Geolocation by DB-IP"
credit its CC BY 4.0 licence requires.

## Configuring MaxMind for better precision (optional)

1. Register for a free MaxMind account and generate a license key:
   <https://www.maxmind.com/en/geolite2/signup>
2. On the site, go to **Visitors → GeoIP settings**
   (`/admin/config/system/visitors/geoip`) and enter:
   - **MaxMind License Key** — the key from step 1.
   - **GeoIP Database path** — a directory, relative to the Drupal root, that the
     web server can write to. MaxMind's database file lives here (DB-IP's lives
     elsewhere — see above — since it is downloaded automatically rather than by
     an admin).
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
- DB-IP City Lite records have region names but no region ISO codes, so the
  Region report lists DB-IP-resolved visits by name ("California") and
  MaxMind-resolved ones by code ("CA"). `DbIpFallbackGeoIpService::normalize()`
  fills in the name, and empty strings for any other missing field, because
  visitors_geoip writes these values to NOT NULL columns. Without it, every
  visit DB-IP could place failed to save with "Column 'location_region' cannot
  be null".
- Both MaxMind's free tier and DB-IP's Lite tier have accuracy gaps for mobile
  carriers, VPNs, and CGNAT'd IP ranges — expect some visits to still resolve to
  only a country or region, or not at all, even with one of them configured.
