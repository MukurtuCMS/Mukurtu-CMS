# Flat taxonomy

This is a very basic module which provide a new option in vocabulary
creation/edition form to enforce it to be flat. As a developer, I faced some
situation where the vocabulary was supposed to be flat and development have been
done based on that assumption. Later, a contributor creates a new term using the
parent field (hierarchical vocabulary). It causes issue on the front-end or at
least it is confusing for the contributor. This module should avoid the
situation by removing the possibility to create nested terms.

Alter the term creation/edition for to be sure it is impossible to select a
parent.

Alter the term overview page to be sure it is impossible to create a nested
vocabulary.

The Drupal version of the module has some additional features:
- Drush integration to flatten a vocabulary.
- Confirmation step on vocabulary edition form if the form is flagged to be
  flatten but contains nested terms.

Any new feature to be added to the module is welcomed ;-)

For a full description of the module, visit the
[project page](https://www.drupal.org/project/flat_taxonomy).

To submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/flat_taxonomy).


## Table of contents

- Requirements
- Installation
- Configuration
- Maintainers


## Requirements

This module requires no modules outside of Drupal core.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

1. Enable the module at Administration > Extend.
2. When creating new texonomy a new checkbox to be appearing to the bottom side.
3. Checking up the checkbox should avoid the situation by removing the
   possibility to create nested terms.


## Maintainers

- Vincent Bouchet - [vbouchet](https://www.drupal.org/u/vbouchet)
