# External Link

## CONTENTS OF THIS FILE

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * A note about the CSS
 * Using custom icons
 * Maintainers

## INTRODUCTION

The External Links module is a very simple approach to adding icons to links
to external websites or email addresses. It is a JavaScript-based
implementation, so the icons are only shown to users that have JavaScript
enabled.

External Links was written by Nathan Haug.
Built by Robots: [Lullabot](http://www.lullabot.com)

## REQUIREMENTS

This module requires no modules outside of Drupal core.

## INSTALLATION

Install the External Links as you would normally install a contributed
Drupal module. Visit
[Installing Drupal Modules](https://www.drupal.org/node/1897420) for further
information.

1. Copy the extlink folder in the modules folder in your Drupal directory.
2. Enable the module using Manage -> Extend (/admin/modules).

## CONFIGURATION

No additional configuration is necessary though you may fine-tune settings at
Manage -> Configuration -> External Links
(/admin/config/user-interface/extlink).

## A NOTE ABOUT THE CSS

This module adds a CSS file that is only a few lines in length. You may choose
to move this CSS to your theme to prevent the file from needing to be loaded
separately. To do this:

1. Open the .info.yml file for your theme and add those lines of code to
   prevent the extlink.css file from loading:
    ```
        stylesheets-remove:
          - extlink.css
    ```
2. Open the extlink.css file within the extlink directory and copy all the code
   from the file into your theme's style.css file.
3. Copy the extlink.png and mailto.png files to your theme's directory.

Note that you DO NOT need to make an extlink.css file. Specifying the file
in the .info.yml file is enough to tell Drupal not to load the original file.

## USING CUSTOM ICONS

1. Create a custom JS file in your theme or module, e.g. js/extlink-overrides.js.
2. In that file do something like:
   ```
     (function ($, Drupal) {
       Drupal.theme.extlink_fa_mailto = function (options) {
       // Your custom markup instead of the default
       return '<span class="fa fa-envelope custom-mailto"></span>';
       };
     })(jQuery, Drupal);
   ```
Available functions to override

* Drupal.theme.extlink_fa_mailto
* Drupal.theme.extlink_fa_extlink
* Drupal.theme.extlink_fa_tel
* Drupal.theme.extlink_mailto
* Drupal.theme.extlink_extlink
* Drupal.theme.extlink_tel

## MAINTAINERS

* Lachlan Ennis - [elachlan](https://www.drupal.org/u/elachlan)
* Nate Haug - [quicksketch](https://www.drupal.org/u/quicksketch)
* Neslee Canil Pinto - [Neslee Canil Pinto](https://www.drupal.org/u/neslee-canil-pinto)
* Julian Pustkuchen - [Anybody](https://www.drupal.org/u/anybody)
* Stephen Mustgrave - [smustgrave](https://www.drupal.org/u/smustgrave)

