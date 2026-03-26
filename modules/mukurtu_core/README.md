# Description

This module provides a mix of functionality and configuration that is important for base functionality in Mukurtu CMS.

## Dashboard
The Mukurtu Dashboard is now provided by the [Dashboards contrib module](https://www.drupal.org/project/dashboards). Currently there is only one dashboard (machine name: `mukurtu_dashboard`), which can be accessed at `/dashboard/mukurtu_dashboard` or at `/{langcode}/dashboard/mukurtu_dashboard` for translated sites.

## All Related Content Field
This module provides a computed field `field_all_related_content`, which is an entity reference field controlled by `AllRelatedContentItemList`. This field has options that can be set in `MukurtuSettingsForm`. When set to `computed`, the `RelatedContentComputationEvent` event is dispatched during field computation allowing subscribers to modify the conditions in which items should be considered "related". For example, if you want all items that share the same category or keyword to be related. When the field is set to `localonly`, this field exactly mirrors the non-computed `field_related_content` field.

## Representative Media Field
This module provides a computed field `field_representative_media` controlled by `RepresentativeMediaItemList`. The idea was to create field that would be attached to all content types to provide a protocol aware media field that would abstract away some of the logic behind trying to find an accessible thumbnail/teaser image for a given item for a given user. The current implementation is half baked. It should be fully fleshed out or removed.

## Citation Field
This module provides a computed field `field_citation` which is a text field controlled by `CitationItemList`. The `MukurtuSettingsForm` provides template configuration (with token replacement) per node bundle.

## External Embed Media Source
This module provides a media source for external embed codes, `ExternalEmbed`.
