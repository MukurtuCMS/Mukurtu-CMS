# Description
This module provides CSV export functionality for Mukurtu CMS.

## Permissions to Export
To use the export functionality in Mukurtu CMS, users need a site role that grants them the `access mukurtu export` permission. Beyond that, users must have view access to individual items to export them.

## Marking Items for Export
There is a very simple interface, `MukurtuExporterSourceInterface` that defines a single method `getEntities` that returns the Entity IDs to be exported. There is an implementation of this, `FlaggedExporterSource`, that returns entities that have been flagged, via the `flag` modules, with one of our export related flags (e.g., `export_content`, `export_media`). The idea being a user uses some combination of views + VBO and browse pages to add items to their export "shopping cart". This `FlaggedExporterSource` is the export source being used in the exporter forms.

## Export Settings
There is a custom configuration entity type, CsvExporter (`csv_exporter`), that encapsulates the settings for CSV export. These entities can be configured to be "Site wide" (`site_wide`), in which case they will show up as named preset options to all users with permission to export content. Otherwise they are owned by the creating user and will only be available to them.

## Exporting
There is a custom plugin annotation `MukurtuExporter` and a specific implementation of that plugin, `CSV`. This is where all the batch operations are performed, as well as the `export` method which handles the export of a single entity. That export method dispatches a `EntityFieldExportEvent` event for each field being exported. There is an event subscriber `CsvEntityFieldExportEventSubscriber` that handles that event and provides the default handling for all Mukurtu CMS field types.
