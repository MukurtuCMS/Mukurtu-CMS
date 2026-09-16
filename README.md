[![Mukurtu CI Tests](https://github.com/MukurtuCMS/Mukurtu-CMS/actions/workflows/build-and-test.yml/badge.svg)](https://github.com/MukurtuCMS/Mukurtu-CMS/actions/workflows/build-and-test.yml)

<img alt="Mukurtu Logo" src="https://mukurtu.org/wp-content/uploads/2017/02/cropped-Mukurtu-dc8633.png" height="75px">

# Mukurtu CMS
To learn more about Mukurtu CMS and the larger Mukurtu community, visit [mukurtu.org](https://mukurtu.org/).

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
composer create-project mukurtu/mukurtu-template:^4.0 .
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

1. Check the [latest release](https://github.com/MukurtuCMS/Mukurtu-CMS/releases).
2. Compare it to the version your site is on. Follow the "Mukurtu Dashboard" link in your site and look for the "Site information" block.
3. Read the release notes for each version between yours and the latest. Most releases can be applied directly, but some must be passed through rather than skipped, and the release notes will say so.

### Updating

Back up your site and database first. Then, from your Mukurtu directory:

```
    # Put the site into maintenance mode.
    drush state:set system.maintenance_mode 1
    drush cr

    # Check what will change.
    composer update mukurtu/* -W --dry-run

    # If everything looks correct, run it for real.
    composer update mukurtu/* -W

    # Apply any database updates.
    drush updb

    # Bring the site back online.
    drush state:set system.maintenance_mode 0
    drush cr

```

Test the site before announcing it is back. If anything is wrong, restore your backup.

### Updating through a specific version

When the release notes say a version must be passed through, update to that version first rather than straight to the latest.

1. Back up your site and database.
2. Put the site into maintenance mode.
    - `drush state:set system.maintenance_mode 1`
    - `drush cr`
3. In your project's `composer.json`, pin the version you need to pass through.
    - Look for the line `"mukurtu/mukurtu": "^4.0"`.
    - Replace the constraint with that exact version, for example `"mukurtu/mukurtu": "4.0.0"`.
4. Run the update commands from the section above.
5. Test your site. If there is a problem, restore from your backup.
6. Repeat steps 3 to 5 for each version you need to pass through.
7. When your site has caught up, restore the constraint to `"mukurtu/mukurtu": "^4.0"` and update once more to reach the latest release.

### Troubleshooting: "patch file could not be downloaded" during an update

If `composer update mukurtu/* -W` fails with an error like:
```
The "web/profiles/mukurtu/patches/<some-patch>.patch" file could not be downloaded: Failed to open stream: No such file or directory
```
the release you're updating to added a new patch file that isn't present in your site's current (older) copy of the profile yet. Composer needs that file to patch an existing package before it finishes updating the Mukurtu profile itself, so it can't find it on disk.

To resolve it:
1. Note the patch filename from the error message.
2. Download that file from the `patches/` directory of the [release tag you're updating to](https://github.com/MukurtuCMS/Mukurtu-CMS/tags) on GitHub.
3. Save it into `web/profiles/mukurtu/patches/` in your site, keeping the same filename.
4. Re-run `composer install`. If a different patch file is reported missing, repeat steps 1-3 for it.

## Testing

Mukurtu CMS uses PHPUnit kernel tests and unit tests. See [`docs/testing/coverage.md`](docs/testing/coverage.md) for a full breakdown of test infrastructure and coverage by module. A plain-language summary of what each test verifies is available at [`docs/testing/coverage-plain-language.md`](docs/testing/coverage-plain-language.md).

## Contributing
Code contributions and feedback are welcome, and can be submitted in [our issues](https://github.com/MukurtuCMS/Mukurtu-CMS/issues) or you can contact us at [support@mukurtu.org](mailto:support@mukurtu.org).

## License

Mukurtu CMS is licensed under the [GNU General Public License v3 or later](LICENSE.txt).
