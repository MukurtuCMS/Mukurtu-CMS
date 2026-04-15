[![Mukurtu CI Tests](https://github.com/MukurtuCMS/Mukurtu-CMS/actions/workflows/build-and-test.yml/badge.svg)](https://github.com/MukurtuCMS/Mukurtu-CMS/actions/workflows/build-and-test.yml)

<img alt="Mukurtu Logo" src="https://mukurtu.org/wp-content/uploads/2017/02/cropped-Mukurtu-dc8633.png" height="75px">

# Mukurtu CMS
To learn more about Mukurtu CMS and the larger Mukurtu community, visit [mukurtu.org](https://mukurtu.org/).

**Note: This version of Mukurtu CMS is currently under active development and is subject to daily change. Only use for testing and feedback purposes.**

## Requirements

* The necessary database server, web server, and PHP installed that meet [modern Drupal requirements](https://www.drupal.org/docs/system-requirements)
* [Composer](https://getcomposer.org/)
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

### Updates

To update your local DDEV environment to a newer version of main, run `ddev composer upgrade`. Note that there may be data changes, so use at your own risk.

## Contributing
Mukurtu CMS v4 is under active development. Code contribution and feedback is welcome, and can be submitted in [our issues](https://github.com/MukurtuCMS/Mukurtu-CMS/issues) or you can contact us at [support@mukurtu.org](mailto:support@mukurtu.org).
