[![Mukurtu CI Tests](https://github.com/MukurtuCMS/Mukurtu-CMS/actions/workflows/build-and-test.yml/badge.svg)](https://github.com/MukurtuCMS/Mukurtu-CMS/actions/workflows/build-and-test.yml)

<img alt="Mukurtu Logo" src="https://mukurtu.org/wp-content/uploads/2017/02/cropped-Mukurtu-dc8633.png" height="75px">

# Mukurtu CMS
To learn more about Mukurtu CMS and the larger Mukurtu community, visit [mukurtu.org](https://mukurtu.org/).

**Note: This version of Mukurtu CMS is currently under active development and is subject to daily change. Only use for testing and feedback purposes.**


## Usage
Beginning with version 4, Mukurtu CMS has been implemented as a [Drupal](https://www.drupal.org/) installation profile. Drupal should be [installed](https://www.drupal.org/docs/installing-drupal) as normal, with the Mukurtu CMS installation profile added to your `composer.json` file.

There is an available [Mukurtu CMS project template](https://github.com/MukurtuCMS/Mukurtu-CMS-v4-Project-Template) with a `composer.json` preconfigured to download the Mukurtu CMS installation profile.

> :warning: Access control in Mukurtu depends on the Drupal private file system. You must configure the 'file_private_path' setting in settings.php.

## External Dependencies
* Requires `pdftotext` to be installed on the hosting system for PDF text extraction to function.

## Contributing
Mukurtu CMS v4 is extremely unstable and under active development. If you wish to contribute, please first discuss it with us by starting an issue or discussion on the [Mukurtu CMS GitHub page](https://github.com/MukurtuCMS/Mukurtu-CMS) or contact us via [mukurtu.org](https://mukurtu.org/). Unsolicited pull requests will likely not receive attention at this point in development.

## Quick start for Personal Testing & Evaluation
There are two easy methods to create a local installation of Mukurtu CMS:
### DDEV
Mukurtu CMS can be installed locally using [DDEV](https://ddev.com/).
* Download and install [DDEV](https://github.com/drud/ddev)
* Create and navigate to a new folder (e.g., 'mukurtu'):
```
mkdir mukurtu && cd mukurtu
```
* Download the composer.json file from our [Mukurtu CMS project template](https://github.com/MukurtuCMS/Mukurtu-CMS-v4-Project-Template):
```
wget https://raw.githubusercontent.com/MukurtuCMS/Mukurtu-CMS-v4-Project-Template/main/composer.json
```
* Initialize the ddev project for Drupal 9:
```
ddev config --project-type=drupal9 --docroot=web --create-docroot
```
* Configure Drupal's `file_private_path` setting by creating a folder (outside of `/web`) and editing `sites/default/settings.php` and setting it to the absolute path of your new private folder.
* Start ddev:
```
ddev start
```
* Run composer install:
```
ddev composer install
```
* You may be prompted to add your GitHub token. Follow the on-screen instructions for public repositories.
* Launch your new ddev project:
```
ddev launch
```
* You should now see the standard Drupal 9 installer, configured to use the Mukurtu installation profile
* Default admin credentials are admin/admin

### Gitpod
Mukurtu CMS is configured to work with [Gitpod](https://www.gitpod.io/), a cloud based development environment.

[![Open in Gitpod](https://gitpod.io/button/open-in-gitpod.svg)](https://gitpod.io/#https://github.com/MukurtuCMS/Mukurtu-CMS)
