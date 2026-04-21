## Term Merge Manager

by:
 * Matthias Froschmeier <m.froschmeier@trust-design.net>

## Description

This module extends the Term Merge Module.

It saves all Term Merge Actions and automatically reapply them on new terms.

For example:  
You merge "foo" and "bar" into "foobar".  
The next time the term "foo" is tried to be created on the same vocabulary,   
it's automatically changed into "foobar".

## Requirements

The modules requires enabled the following modules:
 * Term Merge (https://www.drupal.org/project/term_merge)

## Installation

`composer require 'drupal/term_merge_manager:^2.0'`

https://www.drupal.org/docs/extending-drupal/installing-modules
