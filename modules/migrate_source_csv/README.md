Migrate Source CSV is the contrib functionality for migrating CSV files.

Example
=======
The migrate_source_csv_test module in the tests/modules folder provides a
fully functional and runnable example migration scenario demonstrating the
basic concepts and most common techniques for CSV-based migrations.

To enable test modules, add $settings['extension_discovery_scan_tests'] = TRUE;
to your settings.php, or enable the default local.settings.php file that comes
with Drupal 8.

See https://cgit.drupalcode.org/drupal/tree/sites/example.settings.local.php

Installing the demo/test module
-------------------------------
Enable the module, check status and run a migration:
drush en migrate_source_csv_test -y
drush migrate-status
drush migrate-import migrate_csv_test
