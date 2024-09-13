# Description
This module provides media content warnings--custom code to enable click-through overlays on top of media tagged with sensitive taxonomy terms. Supported terms are terms in either the People and/or Media Tags taxonomy vocabularies.

Settings for media content warnings are found on the dashboard under Site Configuration (or at /admin/config/mukurtu/content-warnings).

## Requirements
### People Content warnings
1. Enable taxonomy records on the People vocabulary (at /admin/config/mukurtu/taxonomy/records)
2. Enable People warnings at /admin/config/mukurtu/content-warnings
3. When creating a media item that depicts or references a Person record, and that person is deceased, add that person's name to the Media Tags vocabulary.
4. Add the media item from the previous step to the media assets field of its corresponding person record.
5. On that person record, add the person's corresponding People term to the Representative Terms field on the Relations tab to see the content warning overlay.

### Taxonomy term warnings
1. Enable taxonomy records on the Media Tags vocabulary (at /admin/config/mukurtu/taxonomy/records)
2. Add terms to Media Tags field
3. Customize your content warnings at /admin/config/mukurtu/content-warnings. The media tags on your site are the terms you can choose from. You can customize the warning text for each term.
4. Attach this term to a media item's Media Tags field.
5. Add this media item to any content item's media assets field to see the content warning overlay.

---
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

---
# Notes
Q: Why do I have to use these specific taxonomy vocabularies to trigger content warnings? Why can't I customize that?
A: For now, we are implementing content warnings as they are in v3.

Q: There are some other files in this module's directory that aren't in use. What are they?
A: Oh yeah, there are some unused files in this directory (as of 09-04-24):
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
