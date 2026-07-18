[![Mukurtu CI Tests](https://github.com/MukurtuCMS/Mukurtu-CMS/actions/workflows/build-and-test.yml/badge.svg)](https://github.com/MukurtuCMS/Mukurtu-CMS/actions/workflows/build-and-test.yml)

<img alt="Mukurtu Logo" src="https://mukurtu.org/wp-content/uploads/2017/02/cropped-Mukurtu-dc8633.png" height="75px">

# Mukurtu CMS
To learn more about Mukurtu CMS and the larger Mukurtu community, visit [mukurtu.org](https://mukurtu.org/).

**Note: This version of Mukurtu CMS is currently under active development and is subject to daily change. Only use for testing and feedback purposes.**

## Requirements

* The necessary database server, web server, and PHP installed that meet [modern Drupal requirements](https://www.drupal.org/docs/system-requirements)
  * Currently only PHP 8.3 is supported. Support for 8.4 will be added later.
  * Currently MariaDB or MySQL is supported. PostGRES is not.
  * the Mukurtu Team does our internal work with nginx. Apache SHOULD work fine, but we have not tested it extensively.
* [Composer](https://getcomposer.org/)
* To generate PDF thumbnails, [poppler-utils](https://pypi.org/project/poppler-utils/) must be installed on the server.
* To generate thumbnails for uploaded video files, [FFmpeg](https://ffmpeg.org/) must be installed on the server.
* For local development, we encourage using [Docker](https://ddev.readthedocs.io/en/stable/users/install/docker-installation/) and [DDEV](https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/) (which includes composer)

## Install Mukurtu with DDEV

Using DDEV is the easiest way to get up and running with Mukurtu locally.

* Download and install [DDEV](https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/)
* Make a new folder in which to work and initialize a DDEV project inside it. Run the following commands to download and install Mukurtu.
```
mkdir mukurtu
cd mukurtu
ddev config --project-type=drupal --docroot=web
# Optional but recommended: install pdftotext inside the DDEV container:
echo "RUN sudo apt -qq update; sudo apt install poppler-utils -y;" > .ddev/web-build/Dockerfile.pdftotext
ddev start
ddev composer create-project mukurtu/mukurtu-template:dev-main
ddev drush si --site-name=Mukurtu --account-name=admin --account-pass=admin
ddev launch
```
* If planning to develop on the Mukurtu CMS installation profile, follow the [additional installation steps to connect a Git checkout to the new project](https://github.com/MukurtuCMS/Mukurtu-CMS/wiki).

## Installing Mukurtu CMS with Composer

If installing directly on a web host that has a command line interface, you can install Mukurtu via composer.

* First, [install composer](https://getcomposer.org/download/). If you do not have it already, it can be downloaded into a directory with the following:
```
wget https://raw.githubusercontent.com/composer/getcomposer.org/main/web/installer -O - -q | php -- --quiet
# Ideally, move composer into an executable path such as /usr/local/bin/composer.
# But for use only within the current directory, just rename it.
mv composer.phar composer
```
* Install Mukurtu through composer with the following commands:
```
mkdir mukurtu
cd mukurtu
composer create-project mukurtu/mukurtu-template:dev-main .
```
* Set your web server to serve the "web" folder (e.g. `mukurtu4/web`)
* Install Drupal as normal by opening the site in your web browser, the Mukurtu profile distribution will automatically be used.

## Post-installation Steps

### Set up private files

Access control in Mukurtu depends on the Drupal private file system. You must configure the `file_private_path` setting in settings.php.

* Create a folder outside the `web` directory, such as `private_files`.
* Open `web/sites/default/settings.php` and modify the `$settings['file_private_path']` line, such as the following:
```php
// Specify a private files path.
$settings['file_private_path'] = '../../private_files';
```
* Clear your site cache by visiting `admin/config/development/performance` within your Mukurtu site and clicking "Clear all caches".
* Confirm the private files directory is found by visiting `admin/config/media/file-system` within your Mukurtu site.

### Install pdftotext

The ability to parse PDFs is dependent on the `pdftotext` command line tool. This can be installed in ddev with:
```bash
echo "RUN sudo apt -qq update; sudo apt install poppler-utils -y;" > .ddev/web-build/Dockerfile.pdftotext
ddev restart
```

Or, if hosting your own server with:
```bash
sudo apt install poppler-utils
```

### Set up cookie and consent management (Klaro)

Mukurtu ships the [Klaro](https://www.drupal.org/project/klaro) module (enabled by default) for GDPR/cookie consent management, configurable at `admin/config/user-interface/klaro` (also linked from the dashboard as "Cookie & Consent Settings"). It ships with all of its pre-built services (Google Analytics, YouTube, Google Maps, etc.) disabled, so it has no effect until a site enables what it actually uses.

If you configure a Google Tag Manager container via the existing Google Tag module (`admin/config/services/google-tag/containers`), also enable Klaro's `gtm_consent_mode`, `ga_consent_mode`, and/or `google_ads_consent_mode` services (Klaro admin > Manage > Services) so those tags respect visitor consent via Google Consent Mode v2. Klaro also ships a `google_consent_mode` recipe that does this in one step: `ddev drush recipe web/modules/contrib/klaro/recipes/google_consent_mode`.

### Updates

To update your local DDEV environment to a newer version of main, run `ddev composer upgrade`. Note that there may be data changes, so use at your own risk.

## Contributing
Mukurtu CMS v4 is under active development. Code contribution and feedback is welcome, and can be submitted in [our issues](https://github.com/MukurtuCMS/Mukurtu-CMS/issues) or you can contact us at [support@mukurtu.org](mailto:support@mukurtu.org).
