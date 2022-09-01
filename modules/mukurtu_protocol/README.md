# Description
This module provides the community and cultural protocol entities, which are the basis for grouping and access control in Mukurtu.

# Route Permission: _mukurtu_permission
This module provides a custom route requirement handler for Mukurtu specific permissions called '_mukurtu_permission' that is very similar to the standard Drupal '_permission' route requirement and uses the same conjunctions (',' for AND, '+' for OR).

Unlike '_permission', '_mukurtu_permission' will check the user's community and protocol permissions. A site permission can be specified by prefixing the permission with 'site:'. Community permissions can be prefixed with 'community:', cultural protocol permissions with 'protocol:', or both will be checked if the prefix is omitted. In the case of no prefix, having *either* the community or protocol permission is sufficient (both will be *checked* but only one is *required*).
