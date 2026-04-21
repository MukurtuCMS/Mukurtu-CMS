CONTRIBUTING
------------

You may setup your local environment with [DDEV]. This project leverages the
[DDEV Drupal Contrib] plugin.

1.  [Install DDEV] with a [Docker provider].
2.  Clone this project's repository from Drupal's GitLab.

        git clone git@git.drupal.org:project/views_bulk_operations.git
        cd views_bulk_operations

3.  Startup DDEV.

        ddev start

4.  Install composer dependencies.

        ddev poser

    Note: `ddev poser` is shorthand for `ddev composer` to add in Drupal core dependencies
    without needing to modify the root composer.json. Find out more in DDEV Drupal Contrib
    [commands].

5.  Install Drupal.

        ddev drush site:install

6.  Visit site in browser.

        ddev describe

    Or, login as user 1:

        ddev drush uli

7.  Push work to Merge Requests (MRs) opened via this project's [issue queue].


CHANGING DRUPAL CORE VERSION
----------------------------

DDEV Drupal Contrib installs a recent stable version of Drupal core via the `DRUPAL_CORE`
environment variable. Review .ddev/config.yaml to find the current default version.

Override the current default version of Drupal core by creating .ddev/config.local.yaml:

```yaml
web_environment:
    - DRUPAL_CORE=^10
```

UPDATING DEPENDENCIES
---------------------

This project depends on 3rd party PHP libraries. It also specifies suggested "dev dependencies"
for contribution on local development environments. Occasionally, DDEV and DDEV Drupal Contrib
must be updated as well.

1.  Create an issue, MR, and checkout the MR branch.
2.  Update DDEV and DDEV Drupal Contrib itself.

    Read https://ddev.readthedocs.io/en/stable/users/install/ddev-upgrade/

        ddev config --update
        ddev get ddev/ddev-drupal-contrib
        ddev restart
        ddev poser
        ddev symlink-project

3.  Review and update PHP dependencies defined in composer.json

        ddev composer outdated --direct

3.  Test clean install, commit, and push.


[DDEV]: https://www.ddev.com/
[DDEV Drupal Contrib]: https://github.com/ddev/ddev-drupal-contrib
[Install DDEV]: https://ddev.readthedocs.io/en/stable/
[Docker provider]: https://ddev.readthedocs.io/en/stable/users/install/docker-installation/
[issue queue]: https://www.drupal.org/project/issues/views_bulk_operations
[commands]: https://github.com/ddev/ddev-drupal-contrib#commands
