# Agents.md

## Project Context
The Geocoder module wraps the [Geocoder PHP library](http://geocoder-php.org) to provide geocoding and reverse-geocoding services in Drupal. It exposes a `geocoder` service, a plugin system for providers and output formats, a config entity for provider management, and REST API endpoints. Three submodules handle field integration: `geocoder_field` (generic), `geocoder_geofield` (Geofield module), and `geocoder_address` (Address module).

## Key Architecture

### Plugin System (three plugin types)
- **Providers** (`src/Plugin/Geocoder/Provider/`): 35+ geocoding services (GoogleMaps, Nominatim, BingMaps, etc.). Base classes in `src/`: `ProviderBase` → `ProviderUsingHandlerBase` → `ProviderUsingHandlerWithAdapterBase` → `ConfigurableProviderUsingHandlerWithAdapterBase` (most providers use the deepest base that fits their needs).
- **Dumpers** (`src/Plugin/Geocoder/Dumper/`): Convert `Location` objects to output formats (GeoJson, Wkt, Kml, Gpx, Wkb, AddressText). Managed by `DumperPluginManager`.
- **Formatters** (`src/Plugin/Geocoder/Formatter/`): Address string formatters managed by `FormatterPluginManager`.

### Config Entity
`GeocoderProvider` (`src/Entity/GeocoderProvider.php`) stores a provider plugin ID and its configuration (API keys, etc.) as a Drupal config entity. Providers must be created in the UI at `/admin/config/system/geocoder/geocoder-provider` before they can be used programmatically.

### Main Service
`Geocoder` (`src/Geocoder.php`) — injected as `geocoder`. Two primary methods:
- `geocode(string $address, array $providers, ?string $dumper = NULL): ?AddressCollection`
- `reverse(string $latitude, string $longitude, array $providers, ?string $dumper = NULL): ?AddressCollection`

Pass loaded `GeocoderProvider` entities as `$providers`. Caching and throttling are handled internally.

### Rate Limiting
`GeocoderThrottle` (`src/GeocoderThrottle.php`) uses a LeakyBucket algorithm (`davedevelopment/stiphle`) to throttle requests per provider.

### REST API
`GeocoderApiEnpoints` controller handles:
- `GET /geocoder/api/geocode?address=...&geocoder=...&format=...`
- `GET /geocoder/api/reverse_geocode?latlon=...&geocoder=...`

Requires the `access geocoder api endpoints` permission.

## Drupal Services

```
geocoder                           # Main service (GeocoderInterface)
geocoder.http_adapter              # Guzzle7 HTTP client
plugin.manager.geocoder.provider   # Provider plugin manager
plugin.manager.geocoder.dumper     # Dumper plugin manager
plugin.manager.geocoder.formatter  # Formatter plugin manager
cache.geocoder                     # Dedicated cache bin
geocoder.throttle                  # Rate limiter
```

## Build / Lint / Test

Install provider packages as needed (no single provider is required):
```bash
composer require geocoder-php/nominatim-provider  # example
```

Run kernel tests (from Drupal root):
```bash
phpunit -c public_html/core/phpunit.xml.dist --filter geocoder tests/src/Kernel/
```

Tests use `geocoder_test_provider` (a mock in `tests/modules/`) and the built-in `Random` provider — no real API calls required.

Lint a file:
```bash
phpcs --standard=Drupal src/Geocoder.php
```

Static analysis:
```bash
phpstan analyse --configuration=phpstan.neon
```

## Hooks (extension points)

Defined in `geocoder.api.php`:
- `hook_geocode_address_string_alter(string &$address)` — alter address string before geocoding.
- `hook_geocode_address_geocode_query(GeocodeQuery &$query)` — alter the GeocodeQuery object.
- `hook_reverse_geocode_coordinates_alter(string &$latitude, string &$longitude)` — alter coordinates before reverse geocoding.
- `hook_geocode_country_code_alter(string &$country_code, Location $location)` — alter country code in results.

## Adding a New Provider Plugin

1. Add the provider package: `composer require geocoder-php/<name>-provider`
2. Create `src/Plugin/Geocoder/Provider/MyProvider.php` extending the appropriate base class.
3. Use the `#[GeocoderProvider]` attribute (or `@GeocoderProvider` annotation) for plugin discovery.
4. If the provider needs configuration (API key, locale, etc.) implement `buildConfigurationForm()` and `defaultConfiguration()`.

## Submodule Notes

- **geocoder_field**: Hooks into entity presave to auto-geocode field values. Check `geocoder_presave_disabled` global setting and per-field widget config. Uses a `QueueWorker` for async batch processing.
- **geocoder_geofield**: Adds Geofield as a reverse-geocoding source and file-based geocoding (GPX/KML/GeoJSON). Requires `geofield` module.
- **geocoder_address**: Maps `Address` field components to geocode queries and populates them from reverse geocoding results. Requires `address` module.

## Configuration

- Global settings: `geocoder.settings` (`config/install/geocoder.settings.yml`)
  - `geocoder_presave_disabled`: disable geocoding on entity save globally
  - `cache`: enable/disable result cache
  - `queue`: use async queue for geocoding
- Provider entities are exported to `config/geocoder/geocoder_provider.*.yml`.
- Schema defined in `config/schema/geocoder.schema.yml` — update when adding new provider configuration keys.
