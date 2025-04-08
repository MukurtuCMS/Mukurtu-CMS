# Description
This module provides the community and cultural protocol entities, which are the basis for grouping and access control in Mukurtu.

> Warning: You must use Drupal's private file system for protocols to function as intended. The public file system allows the web server to serve files directly without verifying access.

# Communities & Cultural Protocols
Communities and Cultural Protocols in Mukurtu CMS are both a custom entity (See `./src/Entity/Community.php` and `./src/Entity/Protocol.php`) and Organic Groups groups.

Permissions for both are standard OG permissions and are created in `MukurtuProtocolOgEventSubscriber`.

## Communities
For the purposes of development, Communities primarily exist to:

* Provide a target & user pool for Cultural Protocols
* Provide the permissions for users to create and manage Cultural Protocols
* Provide affiliation information for content, based on what Cultural Protocols/Communities are in use on that content.

## Cultural Protocols
Cultural Protocols, often shortened to just "Protocols", are the main access control system in Mukurtu CMS. They have a significant field `field_access_mode` which can be either `open` or `strict`. When set to `open`, all site users including unauthenticated users, are considered "members" of that protocol. When set to `strict`, users need to have an OG group role assigned for that protocol entity to be considered a member.

> Warning: Having this finer level of granularity of access is not something some areas of Drupal and/or Contrib modules expect. If you are not careful, you will encounter the [Inaccessible Reference Problem](https://github.com/WSU-CDSC/mukurtu-cms/discussions/35) and expose private information.

So called "Protocol Aware" entities are those that have a `CulturalProtocolItem` field (ideally via `CulturalProtocolControlledTrait`). The `cultural_protocol` field has two significant subfields:

* The `protocols` subfield holds a list of protocol entity IDs
* The `sharing_setting` subfield has value `any` or `all`. Essentially this is the logical conjunction for the above protocols subfield (any is OR, all is AND)

Access is essentially determined by checking if, given the sharing setting, a user has membership to the required cultural protocols.

This module also implements a general, protocol aware bundle class called `MukurtuNode`, that provides the protocol field. This will be the default bundle class for any node bundle that does not have a bundle class specified.

### Access Specifics
For nodes, media, and comments there are custom access handlers in place:

|Entity Type ID|Handler|
|-|-|
|node|`MukurtuProtocolNodeAccessControlHandler`|
|media|`MukurtuProtocolMediaAccessControlHandler`|
|comment|`MukurtuCommentAccessControlHandler`|

For queries (views, entityQuery) there are protocol specific grant handlers and `hook_query_TAG_alter` implementations.

There is also integration with the default `content_moderation` module, making those permissions available at the community/protocol level.

# Route Permission: `_mukurtu_permission`
This module provides a custom route requirement handler for Mukurtu specific permissions called `_mukurtu_permission` that is very similar to the standard Drupal `_permission` route requirement and uses the same conjunctions (',' for AND, '+' for OR).

Unlike `_permission`, `_mukurtu_permission` will check the user's community and protocol permissions. A site permission can be specified by prefixing the permission with `site:`. Community permissions can be prefixed with `community:`, cultural protocol permissions with `protocol:`, or both will be checked if the prefix is omitted. In the case of no prefix, having *either* the community or protocol permission is sufficient (both will be *checked* but only one is *required*).
