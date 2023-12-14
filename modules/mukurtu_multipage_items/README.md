# Description
This module provides the multipage item entity. A multipage item is a sequence of individual items (content nodes).

## Usage & Access
* Only nodes are supported as pages.
* Items may only be contained in a single multipage item.
* Community Records cannot be added as pages.
* By default, a multipage item's title will be the title of the first page.
* View access to the multipage item is controlled by the view access of the first page. Individual pages still maintain their protocol access, some pages may not be available to all users.
* Edit access (adding, removing, reordering pages) is controlled by the edit access of the first page.
* A user must have edit access to an item to add it as a page.
* Deleting the multipage item does not delete the individual pages.
* Route `mukurtu_multipage_items.settings` allows users with permission `administer multipage item` to select which node bundles are permitted to be used as pages.
