# Geocoder 4.x

This is a complete rewrite of the Geocoder module, based on the
[Geocoder PHP library](http://geocoder-php.org).

This branch is a parallel copy of the Geocoder 3.x branch requiring
php-http/guzzle7-adapter compatibility.

### **Note:** How to seamlessly upgrade to the latest Geocoder 4.x.
At the moment this branch still has incompatibilities with packages locked to
php-http/guzzle6-adapter dependency (see [issue comment #3283651-50](https://www.drupal.org/project/geocoder/issues/3283651#comment-15050664)).

When preparing for an upgrade we recommend that you widen your composer version
constraints to allow either 3.x or 4.x.
Edit composer.json the following way:

```
"require": {
...
"drupal/geocoder": "^3.20 || ^4.0",
...
}
```

# Features

* Solid API based on the [Geocoder PHP library](http://geocoder-php.org);
* Geocode and Reverse Geocode using one or multiple Geocoder providers
  (ArcGISOnline, BingMaps, File, GoogleMaps, MapQuest, Nominatim,
  OpenStreetMap, etc.);
* Results can be dumped into multiple formats such as WKT, GeoJson, KML,
  GPX, WKB, and AddressText;
* The Geocoder Provider and Dumper plugins are extendable through a custom
  module;
* Submodule Geocoder Field provides Drupal field widgets and formatters, with
  even more options;
* [Geofield](https://www.drupal.org/project/geofield) and
  [Address](https://www.drupal.org/project/address) field integration;
* Caching results capabilities, enabled by default;
* Rate limiting per provider via a LeakyBucket algorithm;

# Architecture

## Plugin System

Three plugin types drive the module:

* **Providers** (`src/Plugin/Geocoder/Provider/`): 35+ geocoding services
  (GoogleMaps, Nominatim, BingMaps, etc.). Base class hierarchy:
  `ProviderBase` → `ProviderUsingHandlerBase` →
  `ProviderUsingHandlerWithAdapterBase` →
  `ConfigurableProviderUsingHandlerWithAdapterBase`.
  Use the deepest base that fits the provider's needs.

* **Dumpers** (`src/Plugin/Geocoder/Dumper/`): Convert `Location` objects to
  output formats (GeoJson, Wkt, Kml, Gpx, Wkb, AddressText). Managed by
  `DumperPluginManager`.

* **Formatters** (`src/Plugin/Geocoder/Formatter/`): Address string formatters.
  Managed by `FormatterPluginManager`.

## Config Entity

`GeocoderProvider` (`src/Entity/GeocoderProvider.php`) stores a provider plugin
ID and its configuration (API keys, etc.) as a Drupal config entity. Providers
must be created in the UI at `/admin/config/system/geocoder/geocoder-provider`
before they can be used programmatically.

## Main Service

`Geocoder` (`src/Geocoder.php`) — injected as `geocoder`. Two primary methods:

```php
geocode(string $address, array $providers, ?string $dumper = NULL): ?AddressCollection
reverse(string $latitude, string $longitude, array $providers, ?string $dumper = NULL): ?AddressCollection
```

Pass loaded `GeocoderProvider` entities as `$providers`. Caching and throttling
are handled internally.

## Rate Limiting

`GeocoderThrottle` (`src/GeocoderThrottle.php`) uses a LeakyBucket algorithm
(`davedevelopment/stiphle`) to throttle requests per provider.

# Requirements

* [Composer](https://getcomposer.org/), to add the module to your codebase
  (refer to [Using Composer to manage Drupal site dependencies](https://www.drupal.org/node/2718229));
* [Drush](http://drush.org), to enable the module (and its dependencies) from
  the shell;
* One or more [Geocoder Provider packages](https://packagist.org/providers/geocoder-php/provider-implementation)
  installed via Composer. The dependent
  [willdurand/geocoder](https://packagist.org/packages/willdurand/geocoder)
  and any provider-specific libraries are downloaded automatically;
* The embedded **Geocoder Geofield** submodule requires the
  [Geofield module](https://www.drupal.org/project/geofield);
* The embedded **Geocoder Address** submodule requires the
  [Address module](https://www.drupal.org/project/address);

# Installation and Setup

* Download the module running the following shell command from your project root
  (at the composer.json file level):

  ```bash
  composer require drupal/geocoder:^4.0
  ```

* Choose the [Geocoder Provider](https://github.com/geocoder-php/Geocoder#address)
  you want to use and also add it as a required dependency to your project. For
  example if you want to use Nominatim (used by OpenStreetMap) as your provider:

  ```bash
  composer require geocoder-php/nominatim-provider
  ```

* Enable the module via [Drush](http://drush.org):

  ```bash
  drush en geocoder
  ```

  or the website back-end/administration interface.

* Optionally enable submodules: `geocoder_field` and
  `geocoder_geofield` / `geocoder_address`.

* Create and configure one or more providers at Configuration > System >
  Geocoder > Providers:
  `admin/config/system/geocoder/geocoder-provider`.

* Configure caching options at Configuration > System > Geocoder:
  `admin/config/system/geocoder`.

### Support for [COI (Config Override Inspector) module](https://www.drupal.org/project/coi)

It is hard to confirm that configuration overrides are being applied correctly
in production. Also, API keys are visible when they are being overridden in the
production environment. The Geocoder module supports the COI module to more
easily see what has been overridden and to hide overridden API keys.

# Hooks (Extension Points)

Documented in `geocoder.api.php`.

# Submodules

The Geocoder submodules are needed to set up and implement Geocode and Reverse
Geocode functionalities on entity fields from the Drupal backend.

## geocoder_field

Adds the ability to set up Geocode operations on entity insert and edit
operations among specific field types, as well as field Geo formatters, using
all available Geocoder Provider Plugins and output Geo formats (via Dumpers).
It also enables the File provider/formatter functionalities for geocoding valid
Exif Geo data present in JPG images. Uses a `QueueWorker` for async batch
processing.

Check the `geocoder_presave_disabled` global setting and per-field widget
configuration to control when geocoding runs on save.

## geocoder_geofield

Provides integration with [Geofield](https://www.drupal.org/project/geofield)
(module/field type) and the ability to both use it as a target of Geocode or
source of Reverse Geocode with other fields. It also enables provider/formatter
functionalities for geocoding valid GPX, KML, and GeoJSON file contents.

## geocoder_address

Provides integration with [Address](https://www.drupal.org/project/address)
(module/field type) and the ability to both use it as a target of Reverse
Geocode from a Geofield or as a source of Geocode with other fields.

---

Throughout Geocoder submodules **the following field types are supported**:

###### For Geocode operations:

* `text`, `text_long`, `text_with_summary`
* `string`, `string_long`
* `file` (with `geocoder_field` module enabled)
* `image` (with `geocoder_field` module enabled)
* `computed_string`, `computed_string_long` (with `computed_field` module enabled)
* `address` (with `address` module and `geocoder_address` sub-module enabled)
* `address_country` (with `address` module and `geocoder_address` sub-module enabled)

###### For Reverse Geocode operations:

* `geofield` (with `geofield` module and `geocoder_geofield` sub-module enabled)

**Note:** The Geocoder Field sub-module provides hooks to alter (change and
extend) the list of Geocoding and Reverse Geocoding field types
(see `geocoder_field.api.php`).

## Using Geocoder Behind a Proxy

`geocoder.http_adapter` service respects
`$settings['http_client_config']['proxy']` as defined in `default.settings.php`.

# Adding a New Provider Plugin

1. Add the provider package:
   ```bash
   composer require geocoder-php/<name>-provider
   ```
2. Create `src/Plugin/Geocoder/Provider/MyProvider.php` extending the
   appropriate base class.
3. Use the `#[GeocoderProvider]` attribute (or `@GeocoderProvider` annotation)
   for plugin discovery.
4. If the provider needs configuration (API key, locale, etc.) implement
   `buildConfigurationForm()` and `defaultConfiguration()`.

# API

## Get a List of Available Provider Plugins

This is the list of plugins that have been installed using Composer and are
available to configure in the UI.

```php
\Drupal::service('plugin.manager.geocoder.provider')->getDefinitions();
```

## Get a List of Available Dumper Plugins

```php
\Drupal::service('plugin.manager.geocoder.dumper')->getDefinitions();
```

## Get a List of Providers Created in the UI

```php
\Drupal::entityTypeManager()->getStorage('geocoder_provider')->loadMultiple();
```

## Geocode a String

```php
// A list of machine names of providers that are created in the UI.
$provider_ids = ['geonames', 'googlemaps', 'bingmaps'];
$address = '1600 Amphitheatre Parkway Mountain View, CA 94043';

$providers = \Drupal::entityTypeManager()
  ->getStorage('geocoder_provider')
  ->loadMultiple($provider_ids);

$addressCollection = \Drupal::service('geocoder')
  ->geocode($address, $providers);
```

## Reverse Geocode Coordinates

```php
$provider_ids = ['freegeoip', 'geonames', 'googlemaps', 'bingmaps'];
$lat = '37.422782';
$lon = '-122.085099';

$providers = \Drupal::entityTypeManager()
  ->getStorage('geocoder_provider')
  ->loadMultiple($provider_ids);

$addressCollection = \Drupal::service('geocoder')
  ->reverse($lat, $lon, $providers);
```

## Return Format

Both `Geocoder::geocode()` and `Geocoder::reverse()` return the same object:
`Geocoder\Model\AddressCollection`, which is itself composed of
`Geocoder\Model\Address`.

You can transform those objects into arrays:

```php
$provider_ids = ['geonames', 'googlemaps', 'bingmaps'];
$address = '1600 Amphitheatre Parkway Mountain View, CA 94043';

$providers = \Drupal::entityTypeManager()
  ->getStorage('geocoder_provider')
  ->loadMultiple($provider_ids);

$addressCollection = \Drupal::service('geocoder')
  ->geocode($address, $providers);
$address_array = $addressCollection->first()->toArray();

// You can also get individual coordinate values:
$latitude  = $addressCollection->first()->getCoordinates()->getLatitude();
$longitude = $addressCollection->first()->getCoordinates()->getLongitude();
```

You can also convert results to different formats using the Dumper plugins.
Get the list of available Dumpers:

```php
\Drupal::service('plugin.manager.geocoder.dumper')->getDefinitions();
```

Here's an example of how to use a Dumper:

```php
$addressCollection = \Drupal::service('geocoder')
  ->geocode($address, $providers);
$geojson = \Drupal::service('plugin.manager.geocoder.dumper')
  ->createInstance('geojson')
  ->dump($addressCollection->first());
```

There's also a dumper for GeoPHP:

```php
$addressCollection = \Drupal::service('geocoder')
  ->geocode($address, $providers);
$geometry = \Drupal::service('plugin.manager.geocoder.dumper')
  ->createInstance('geometry')
  ->dump($addressCollection->first());
```

# Geocoder API URL Endpoints

The Geocoder module provides the following API URL endpoints (with JSON output)
for performing Geocode and Reverse Geocode operations.

## Geocode

This endpoint allows processing a Geocode operation (get Geo Coordinates from
Addresses) on the basis of an input Address, the operational Geocoders, and an
optional output Format (Dumper).

Path: **`/geocoder/api/geocode`**
Method: **GET**
Access Permission: **`access geocoder api endpoints`**
Successful Response Body Format: **json**

##### Query Parameters:

* **address** (required): The address string to geocode (the more detailed and
  extended, the better the possible results).

* **geocoder** (required): The Geocoder ID, or a list of Geocoder IDs separated
  by a comma (,) that should process the request (in order of priority). At
  least one must be provided. Each ID must correspond to a valid
  `GeocoderProvider` config entity.

  Note: unless differently specified in `options`, the Geocoder configurations
  at `/admin/config/system/geocoder` will be used for each Geocoder.

* **format** (optional): The geocoding output format ID for each result. Must be
  a single value corresponding to one of the Dumper (`@GeocoderDumper`) plugin
  IDs defined in the Geocoder module. Default (or fallback): the native output
  format of the specific `@GeocoderProvider` processing the operation.

* **address_format** (optional): The specific Geocoder address formatter plugin
  (`@GeocoderFormatter`) used to output the `formatted_address` property (present
  when no specific output format/Dumper is requested). Falls back to the bundled
  `default_formatted_address` formatter.

* **options** (optional): Override plugin options written as multi-dimensional
  array query strings (e.g. `a[b][c]=d`). For instance, to override the Google
  Maps locale to Italian: `options[googlemaps][locale]=it`

## Reverse Geocode

This endpoint allows processing a Reverse Geocode operation (get an Address from
Geo Coordinates) on the basis of input Latitude/Longitude coordinates, the
operational Geocoder Providers, and an optional output Format (Dumper).

Path: **`/geocoder/api/reverse_geocode`**
Method: **GET**
Access Permission: **`access geocoder api endpoints`**
Successful Response Body Format: **json**

##### Query Parameters:

* **latlon** (required): The latitude and longitude values in decimal degrees,
  as a comma-separated string (e.g. `45.4654,9.1859`) specifying the location
  for which you wish to obtain the closest human-readable address.

* **geocoder** (required): See the Geocode endpoint parameters.

* **format** (optional): See the Geocode endpoint parameters.

* **options** (optional): See the Geocode endpoint parameters.

## Successful and Unsuccessful Responses

If the Geocode or Reverse Geocode operation is successful, each response result
is a JSON format output (array list of JSON objects) with a 200 ("OK") response
status code. Each result format complies with the chosen output format (Dumper).
Retrieve the PHP results array with:

```php
$response_array = JSON::decode($this->response->getContent());
$first_result = $response_array[0];
```

If something goes wrong (no Geocoder provided, bad Geocoder configuration, etc.)
the response body is empty with a 204 ("No content") status code. See the Drupal
logs for information about possible causes.

# Persistent Cache for Geocoded Points

Ref: Geocoder issue [#2994249](https://www.drupal.org/project/geocoder/issues/2994249)

It is possible to persist the geocode cache when Drupal caches are cleared:

* Install the [Permanent Cache Bin module](https://www.drupal.org/project/pcb)
* In your `settings.php` add:
  ```php
  $settings['cache']['bins']['geocoder'] = 'cache.backend.permanent_database';
  ```

# Upgrading from Geocoder 2.x to 3.x (and above)

## Site Builders

1. When upgrading to the Geocoder 8.x-3.x branch, remove the Geocoder 8.x-2.x
   branch first (`composer remove drupal/geocoder`), and make sure its
   dependency `willdurand/geocoder` is also removed
   (run also: `composer remove willdurand/geocoder`).

2. Require the new Geocoder 4.x version:
   `composer require 'drupal/geocoder:^4.0'`
   (this will also install the `willdurand/geocoder` dependency).

3. Choose the [Geocoder Provider](https://packagist.org/providers/geocoder-php/provider-implementation)
   you want to use and add it as a required dependency to your project.
   For example, to use Google Maps as your provider:
   `composer require geocoder-php/google-maps-provider`

   It will be added as a Geocoder provider option in the "add provider" selector
   at `/admin/config/system/geocoder/geocoder-provider`.

4. Run the database updates, either by visiting `update.php` or running:
   `drush updb`

5. Check the existing Geocoder provider settings or add new ones at
   `/admin/config/system/geocoder/geocoder-provider`.

6. Re-apply the Geocoding and Reverse Geocoding settings for each field you
   previously configured, as they will have been lost during the upgrade.

## Developers

Since Geocoder 3.x, Geocoder providers are config entities, whereas in earlier
versions the provider settings were stored in simple configuration. An upgrade
path is provided, but any code relying on the old simple config must be updated
to use the `GeocoderProvider` config entity. See `src/Entity/GeocoderProvider.php`.

### Removed Methods

#### `GeocodeFormatterBase::getEnabledProviderPlugins()`

The method
`\Drupal\geocoder_field\Plugin\Field\GeocodeFormatterBase::getEnabledProviderPlugins()`
returned an array of provider configuration as flat properties. It has been
replaced by
`\Drupal\geocoder_field\Plugin\Field\GeocodeFormatterBase::getEnabledGeocoderProviders()`
which returns an array of `GeocoderProvider` entities.

### Signature Changes

#### `Geocoder::geocode()`

The method `\Drupal\geocoder\Geocoder::geocode()` used to take a string of data
to geocode, a list of provider plugins as an array, and an optional array of
configuration overrides.

Old signature:
```php
public function geocode($data, array $plugins, array $options = []);
```

Since configuration is now stored in config entities, this method takes an array
of `GeocoderProvider` entities. The optional overrides array has been dropped.

New signature:
```php
public function geocode(string $data, array $providers): ?AddressCollection;
```

#### `Geocoder::reverse()`

Old signature:
```php
public function reverse($latitude, $longitude, array $plugins, array $options = []);
```

New signature:
```php
public function reverse(string $latitude, string $longitude, array $providers): ?AddressCollection;
```

### Functional Changes

#### `ProviderPluginManager::getPlugins()`

In Geocoder 2.x, `\Drupal\geocoder\ProviderPluginManager::getPlugins()` was the
main way of retrieving provider plugins, returning plugin definitions mixed with
provider configuration.

Since Geocoder 3.x this has been replaced by the `GeocoderProvider` config
entity. The method now returns only plugin definitions, equivalent to calling
`ProviderPluginManager::getDefinitions()`. Use one of these alternatives instead:

To get all available plugin definitions:
```php
$definitions = \Drupal\geocoder\ProviderPluginManager::getDefinitions();
```

To get all geocoding providers configured by the site builder:
```php
$providers = \Drupal\geocoder\Entity\GeocoderProvider::loadMultiple();
```

# Authors / Maintainers

From Drupal 8 to today:
- **Italo Mairo** — [itamair](https://www.drupal.org/u/itamair) — main maintainer
- **Claudiu Cristea** — [claudiu.cristea](hhttps://www.drupal.org/u/claudiucristea)
- **Pol Dellaiera** — [pol](https://www.drupal.org/u/pol)

Drupal 7:
- **Michael Favia** - [michaelfavia](https://www.drupal.org/u/michaelfavia) - original creator
- **Patrick Hayes** - [phayes](https://www.drupal.org/u/phayes)
- **Juraj Nemec** - [poker10](https://www.drupal.org/u/poker10)
- **Brandon Morrison** - [brandonian](https://www.drupal.org/u/brandonian)
- **Simon Georges** - [simon georges](https://www.drupal.org/u/simon-georges)

And credits to the wider Drupal community

