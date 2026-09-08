# Description

This module provides a mix of functionality and configuration that is important for base functionality in Mukurtu CMS.

## Dashboard
The Mukurtu Dashboard is now provided by the [Dashboards contrib module](https://www.drupal.org/project/dashboards). Currently there is only one dashboard (machine name: `mukurtu_dashboard`), which can be accessed at `/dashboard/mukurtu_dashboard` or at `/{langcode}/dashboard/mukurtu_dashboard` for translated sites.

## All Related Content Field
This module provides a computed field `field_all_related_content`, which is an entity reference field controlled by `AllRelatedContentItemList`. This field has options that can be set in `MukurtuSettingsForm`. When set to `computed`, the `RelatedContentComputationEvent` event is dispatched during field computation allowing subscribers to modify the conditions in which items should be considered "related". For example, if you want all items that share the same category or keyword to be related. When the field is set to `localonly`, this field exactly mirrors the non-computed `field_related_content` field.

## Representative Media Field
This module provides a computed field `field_representative_media` controlled by `RepresentativeMediaItemList`. The idea was to create a field that would be attached to all content types to provide a protocol-aware media field that would abstract away some of the logic behind trying to find an accessible thumbnail/teaser image for a given item for a given user. Since this was originally written, the field has been substantially built out (Community Records source-of-truth support, restricted/no-media placeholders, per-content-type source fields including dictionary and collection media), and is now used across most content types' displays. It has not yet been refactored to use an event, the way the sibling `field_all_related_content` field does with `RelatedContentComputationEvent` (see above); its list of source fields is instead hardcoded and duplicated between `RepresentativeMediaItemList` and `mukurtu_media.module`. See [issue #187](https://github.com/MukurtuCMS/Mukurtu-CMS/issues/187).

## Citation Field
This module provides a computed field `field_citation` which is a text field controlled by `CitationItemList`. The `MukurtuSettingsForm` provides template configuration (with token replacement) per node bundle.

## External Embed Media Source
This module provides a media source for external embed codes, `ExternalEmbed`.
