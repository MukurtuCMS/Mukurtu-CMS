# Description
This module provides CSV import functionality.

## Permissions
Users require a site role that grants them the `access mukurtu import` permission to use import. Beyond that, users will require whatever combination of memberships & permissions is needed to create/update entities of the imported type.

## Approach
The core approach of entity import is to dynamically craft Drupal Migrate API configuration, primarily field level process configuration, and let Migrate handle most of the heavy lifting. Migrate is part of Drupal core, is reasonable well tested, and has a large suite of existing process plugins. It is important to have a strong understanding of Migrate prior to making changes to this module, see their [Migrate API overview](https://www.drupal.org/docs/drupal-apis/migrate-api/migrate-api-overview).

We have extended many of Migrate's classes. Migrate has a few different assumptions than we have for import. For example, Migrate generally assumes that anybody who gets to the point of running a migration has permission to do the entire operation. This is not the case for import where we want to carefully validate access for every step. An example of this is our extension of Migrate's `EntityContentBase`, `ProtocolAwareEntityContent`. `EntityContentBase` does account switching to ensure the user can modify every item in the migration, while `ProtocolAwareEntityContent` specifically does not do that because we want the import to run as the current user, even if that causes a failure. Generally speaking, import should respect entity access in the same way as the rest of Drupal/Mukurtu CMS.

There is a Mukurtu CMS specific plugin annotation `MukurtuImportFieldProcess`. Plugins of this type are responsible for generating the field level migrate process plugin chain to handle fields of the type specified in the annotation. This module provides these plugins for all the field types used in Mukurtu CMS. New plugins can be added in this module or a separate module to add support for new field types or to override the existing handling (e.g., if a specific site wanted to change the default behavior for a field type).

The other key Mukurtu CMS specific element of import is the `mukurtu_import_strategy` configuration entity (`MukurtuImportStrategy`). This entity encapsulates all the import settings per migrate source (which is a CSV file, typically). Usage of this entity can be seen in `CustomStrategyFromFileForm`.
