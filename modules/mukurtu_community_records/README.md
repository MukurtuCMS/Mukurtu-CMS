# Description

This module provides functionality related to Community Records in Mukurtu CMS. The basic concept of community records is that a community can create their own content that describes the same "thing" as a different, existing piece of content (the "Original Record"). The "thing" in this case is typically the "Media Assets" field of the content type. For example, "Community A" might create a public digital heritage item with some pictures of a location. Given sufficient permissions, "Community B" might create their own digital heritage item as a community record of that original item. That gives them the ability to add their own community specific context as well as selecting their own protocol settings, allowing them to control the level of access to their contributed information. This means what a user sees when viewing the original content will reflect their level of access/memberships. Users with access to both nodes will be presented with a single, unified record.

# Notes
* Community Records only work with protocol controlled nodes that have the `field_mukurtu_original_record` field. Each site can select which node bundles should be made available as use for community records using route `mukurtu_community_records.types_settings`.
* Community Records hold the reference to the original record. The original record has no field that directly references the community records. They are built by query.
* The display of Community Records is assisted by `CommunityRecordNodeViewBuilder` which extends `NodeViewBuilder`. It is responsible for passing the community records/original record to the `community_records` template.
* There is a form to adjust the community ordering of community records at route `mukurtu_community_records.order_settings`.
* Community records can contain their own media assets. When viewing a community record the expected behavior is to see both the original record's media assets as well as the community record's assets. This is controlled in `mukurtu_community_records_node_view`.
* A node can only be a community record for a single item. If node `B` is a community record for node `A`, it cannot also be a community record for node `C`.
* Similarly, an original record cannot also be a community record. Effectively we are creating shallow trees, with a single root, 0 to n leaf nodes (community records), and a depth of 1.


This module provides the `Administer Community Records` OG permission to protocols, see `MukurtuCommunityRecordEventSubscriber`.
