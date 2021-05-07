# Description

The Mukurtu Notifications module provides integration with the 'message' module stack and configures the flag module to allow users to follow and be notified of events.

## Following Events
By default, these are the types of content that can be followed, and the events that trigger messages:

* Single Nodes (e.g., a digital heritage item)
  * Update
  * Deletion
  * Comment added
* Collections
  * Addition of items to the collection
  * Removal of items from the collection
  * Comment added to an item in the collection
* Protocols
  * Items added to the protocol. This could be newly created items or existing items that had their protocol field changed.
* Communities
  * Items added to the communtiy. This is equivalent to following all available protocols in the community.
