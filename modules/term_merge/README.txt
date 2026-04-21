CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Recommended Modules
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

The Term merge module allows users to merge taxonomy terms in the same
vocabulary together. This module allows the user to merge multiple terms into
one, while updating all fields referring to those terms to refer to the
replacement term instead.


 * For a full description of the module visit:
   https://www.drupal.org/project/term_merge

 * To submit bug reports and feature suggestions, or to track changes visit:
   https://www.drupal.org/project/issues/term_merge


REQUIREMENTS
------------

This module requires the following outside of Drupal core.

 * Term reference change - https://www.drupal.org/project/term_reference_change


INSTALLATION
------------

Install the Term merge module as you would normally install a contributed
Drupal module. Visit https://www.drupal.org/node/1897420 for further
information.


CONFIGURATION
-------------

    1. Navigate to Administration > Extend and enable the module and its
       dependencies.
    2. Navigate to Administration > Structure > Taxonomy > [Vocabulary to edit]
       > List terms > Merge and select the terms to merge from the dropdown.
       Merge.
    3. Enter the new term name or select from an existing term. Submit.
    4. Confirm merge.


MAINTAINERS
-----------

 * Chris Jansen (legolasbo) - https://www.drupal.org/u/legolasbo
 * Daniel Johnson (daniel_j) - https://www.drupal.org/u/daniel_j
