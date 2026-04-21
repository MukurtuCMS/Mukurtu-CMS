# Color Field

This module provides an intuitive way to specify and display HTML color values.

For a full description of the module, visit the
[color field](https://www.drupal.org/project/color_field) project page.

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/color_field).


## Table of contents

- Requirements
- About & Features
- Road Map
- Installation
- Configuration
- More info
- Maintainers


## Requirements

This module requires no modules outside of Drupal core.


## About & Features

### Formatters

- *Plain text*: Renders a simple HEX Code (#fffff) or an RGB decimal - with
or without opacity.
- *CSS Declaration*: Provides simple options to automatically build a CSS
declaration affecting color or background-color. New in 2.4, also "advanced
mode" which lets you build your own css declaration - for example to target
multiple aspects (such as border color and background color) of elements or
multiple elements.
- *Swatches*: Provides simple swatch of color in various shapes.

### Widgets

- *Color Boxes*: Provides a row of colored boxes to select from a configured
list of colors. If enabled, opacity is a number field.
- *Color Default*: Textbox to put in a hex value. If enabled, opacity is a
number field.
- *Color Grid*: Uses
[jQuery Simple Color](https://github.com/recurser/jquery-simple-color)
to provide a pop up grid of color options. If enabled, opacity is a number
field.
- *Color HTML5*: Uses the color HTML5 input type to render in a browser/system
native manner. If enabled, opacity is a number field.
- *Color Spectrum*: Uses [Spectrum](https://github.com/bgrins/spectrum) to
provide a user friendly color palette to choose the correct color. This has an
integrated slider for opacity (if opacity is enabled).

## Road Map

1) Make this module a base that could be used by any color picker.
2) include http://www.eyecon.ro/colorpicker/
3) include http://www.dematte.at/colorPicker/
4) include http://acko.net/blog/farbtastic-jquery-color-picker-plug-in/


## Installation

Install as you would normally install a contributed Drupal module. For further information, see [Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

Additionally depending on which widget(s) you are using you will need the relevant JavaScript Libraries.
There are multiple ways to install them.

1. (**Recommended**) Us the [Composer Merge Plugin](https://github.com/wikimedia/composer-merge-plugin).
   Require the composer merge plug and add the following to the extra section of your composer.json
   (adjusting the path as necessary to match your system).
   ```
   "merge-plugin": {
     "include": [
       "web/modules/contrib/color_field/composer.libraries.json"
       ]
   },
   ```
   Then run `composer update drupal/color_field` and libraries should be installed.


2. Alternatively you can use Asset Packagist, see [Asset Packagist](https://asset-packagist.org) for more information.
   Add Asset packagist to your `composer.json` and make a couple other changes to your. 
   Specifically, in the `extra` key add/adjust current values:
   ```json
   "installer-types": [
     "npm-asset",
     "bower-asset"
   ],
   "installer-paths": {
     "web/libraries/{$name}": [
       "type:bower-asset",
       "type:npm-asset",
       "type:drupal-library"
     ]
   }
   ```

   then run
   ```bash
   composer require oomphinc/composer-installers-extender
   composer require bower-asset/jquery-simple-color bower-asset/spectrum
   ```

3. You can also manually install the libraries from github but that is not recommended.

## Configuration

1. Enable the module at Administration > Extend.
1. Add a field of the "Color" type to any content type, vocabulary, or other
   bundle that could leverage the functionality of this module.
1. Configure the form display and display options that best suit your needs.
1. Profit!


## More Information

- http://www.w3.org/TR/css3-color/#color
- https://github.com/mikeemoo/ColorJizz-PHP
- http://www.colorhexa.com/ff0000
- https://github.com/PrimalPHP/Color/blob/master/lib/Primal/Color/Parser.php
- https://github.com/matthewbaggett/php-color/blob/master/Color.php


## Maintainers
[//]: # cSpell:disable
[//]: # Do not add maintainers to cspell-project-words file

- david calcus - [targoo](https://www.drupal.org/u/targoo)
- Nick Dickinson-Wilde - [NickDickinsonWilde](https://www.drupal.org/u/NickDickinsonWilde)
- Martin Anderson-Clutz - [mandclu](https://www.drupal.org/u/mandclu)
