[![Mukurtu CI Tests](https://github.com/MukurtuCMS/Mukurtu-CMS/actions/workflows/build-and-test.yml/badge.svg)](https://github.com/MukurtuCMS/Mukurtu-CMS/actions/workflows/build-and-test.yml)

<img alt="Mukurtu Logo" src="https://mukurtu.org/wp-content/uploads/2017/02/cropped-Mukurtu-dc8633.png" height="75px">

# Mukurtu CMS
To learn more about Mukurtu CMS and the larger Mukurtu community, visit [mukurtu.org](https://mukurtu.org/).

**Note: This version of Mukurtu CMS is currently under active development and is subject to daily change. Only use for testing and feedback purposes.**

## Requirements

* The necessary database server, web server, and PHP installed that meet [modern Drupal requirements](https://www.drupal.org/docs/system-requirements)
  * PHP 8.4 is supported.
  * Currently MariaDB or MySQL is supported. PostGRES is not.
  * The Mukurtu Team does our internal work with nginx. Apache SHOULD work fine, but we have not tested it extensively.
* [Composer](https://getcomposer.org/)
* To generate PDF thumbnails, [poppler-utils](https://pypi.org/project/poppler-utils/) must be installed on the server.
* To generate thumbnails for uploaded video files, [FFmpeg](https://ffmpeg.org/) must be installed on the server.
* For local development, we encourage using [Docker](https://ddev.readthedocs.io/en/stable/users/install/docker-installation/) and [DDEV](https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/) (which includes composer)
* If planning to develop on the Mukurtu CMS installation profile, follow the [additional installation steps to connect a Git checkout to the new project](https://github.com/MukurtuCMS/Mukurtu-CMS/wiki).

## Installing Mukurtu CMS with Composer

If installing directly on a web host that has a command line interface, you can install Mukurtu via composer.

**Database requirement:** Create your database using the `utf8mb4` character set and `utf8mb4_general_ci` collation. Using plain `utf8` can cause issues with content that includes characters outside the Basic Multilingual Plane (e.g. emoji). This follows [Drupal's recommendation](https://www.drupal.org/docs/getting-started/system-requirements/database-server-requirements) for MySQL/MariaDB:

```sql
CREATE DATABASE mukurtu CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

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

## Updating Mukurtu CMS

1. Go to https://github.com/MukurtuCMS/Mukurtu-CMS to see what is the latest release.
2. Compare that to the version your site is on.
    -  Go to the  "Mukurtu Dashboard" Link in your site. Look for the "Site information" block.
3. Note how many (if any) versions your site is behind.
 
### Updating if you are only one version behind

1. Backup your site and database.
2. From your Mukurtu 4 directory, run the update commands.
```
    # put the site into offline mode
    drush state:set system.maintenance_mode 1
    drush cr

    # Make sure everything looks correct
    composer update mukurtu/* -W --dry-run

    # If everything looks correct run it for real
    composer update mukurtu/* -W

    # update the database
    drush updb

    # take the site out of offline mode
    drush state:set system.maintenance_mode 0
    drush cr

```
### Updating if you are more than one version behind

We recommend iterating through each skipped version rather than jumping versions.

1. Backup your site and database.
2. From your Mukurtu 4 directory, put your site into offline mode.
    - `drush state:set system.maintenance_mode 0`
    - `drush cr`
2. Edit your composer.json file.
3. Look for this line: `"mukurtu/mukurtu": "*"`
4. Change the asterisk to the next mukurtu version.
    - For example if you are running 4.0.0-beta31 and the latest version is 4.0.0-beta35 change the asterisk to 4.0.0-beta32 (`"mukurtu/mukurtu": "4.0.0-beta32"`).
5. Then run the update commands.
    - Run a test update to make sure everything looks good.
        - `composer update mukurtu/* -W --dry-run`
    -  If everything looks correct run it for real.
        - `composer update mukurtu/* -W`
    - Update the database.
        - `drush updb`
6. Take the site out of offline mode.
    - `drush state:set system.maintenance_mode 0`
    - `drush cr`
7. Test your site. If everything looks good repeat steps 1-6, the only thing that will change is that will update the line in composer.json to reflect the next beta version
    - If there is an issue restore from the last backup.
8. When you have caught the site up to the latest release, change the version number in composer.json back to an asterisk `(*)`.

## Contributing
Mukurtu CMS v4 is under active development. Code contribution and feedback is welcome, and can be submitted in [our issues](https://github.com/MukurtuCMS/Mukurtu-CMS/issues) or you can contact us at [support@mukurtu.org](mailto:support@mukurtu.org).
