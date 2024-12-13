# Implementation details for devs
There are a few key parts to this custom code that make it work:
- form stuff
- theme stuff
- js/css stuff

This implementation of content warnings touches two directories: modules/mukurtu_content_warnings and themes/mukurtu_v4.

1. The settings form (src/Form/MukurtuContentWarningsSettingsForm.php)
2. themes/mukurtu_v4/css/content-warnings.css
3. themes/mukurtu_v4/js/content-warnings.js
4. themes/mukurtu_v4/templates/field/field--node--field-media-assets.html.twig
5. themes/mukurtu_v4/mukurtu_v4.theme (see `mukurtu_v4_preprocess_field()`)

## Notes
There are some other files in this module's directory that aren't in use (as of 09-04-24):
- mukurtu_content_warnings.module
- mukurtu_content_warnings.links.task.yml
- src/Plugin/Field/ContentWarningsField.php
- src/Controller/MukurtuManageContentWarningsController.php
- config/install/field.storage.node.field_content_warning_triggers.yml
- config/install/field.storage.paragraph.field_content_warning_terms.yml
- config/install/field.storage.paragraph.field_content_warning_text.yml
- and the config/install directory itself

These unused files are the previous tech lead's initial stub-out of content warnings. His solution was different from v3's in that it allowed content warnings to be configured at the community level. This is certainly an improvement since at the moment, content warnings are all set at the sitewide level.

We are keeping these files because there could be a feature request in the future to improve media content warnings by providing community-level customization.
