# Description

The Mukurtu Collections module provides a "collection" content type. A collection contains a sequence of other content in the "Items in Collection" field as well as other metadata fields that represent the collection as a whole. The module provides several specific modes of operation for collections, controlled by the "Collection Type" field.


# Collection Type Modes

## Multipage Documents

Multipage documents are collections that are used to bundle content together in a way that makes them appear as a singular, connected piece of content.

When content is added to the multipage collection:
- The Sequence Collection (field_sequence_collection) field is set to target the collection.
- The content's 'full' view mode will be replaced by the 'multipage_full' view mode. The entire collection will be shown.
- The Sequence Collection field is managed by the module, you should not attempt to change it via the content edit form or programmatically.
- The collection's Items in Collection field can be altered in the edit form or programmatically.

### Multipage View Modes

|View Mode|Machine Name|Description|
|---|---|---|
|Multipage Navigation|multipage_navigation|Controls the display for the overall multipage navigation (e.g., a carousel). In general this  mode should not be altered unless you are very familar with the inner workings of the collections module. This mode should be implemented for collections only.
|Multipage Navigation Teaser|multipage_navigation_teaser|This mode controls the display of individual item teasers in the navigation control (e.g., a single page in the carousel). **This mode must be implemented for each content type that is available to be added to a collection.**
|Multipage Item|multipage_item|When viewing a multipage item, this view mode controls the display of the currently selected page. **This mode must be implemented for each content type that is available to be added to a collection.**
|Multipage Full|multipage_full|This view mode is for nodes that are in a multipage item collection. It will display the entire multipage document instead of only the single item page. **This mode must be implemented for each content type that is available to be added to a collection. All content types should have the exact same implementation of this mode.**

## Computed Fields

### Node - Collections (field_in_collection)
This computed field contains the collections the content is a member of.
