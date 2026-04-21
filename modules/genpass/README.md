# Generate Password

Great utility module which makes the password field optional (or hidden)
on the add new user page (admin & registration). If the password field is
not set during registration, the system will generate a strong password.
You can optionally display this password at the time it is created.

This module is useful to achieve compliance with PCI DSS requirement 8.2.6.

For a full description of the module visit
[project page](https://www.drupal.org/project/genpass).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/genpass).


## Requirements

This module requires no modules outside of Drupal core.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

- Install Generate Password module as normally.
- After install, goto `Admin > Configuration > People > Account settings`
- Configure required settings under "Generate Password"
