# Description

The Mukurtu Protocol module provides the "protocol" content type, which are groups used to control access to content in Mukurtu.

## Requirements
* You MUST use the Drupal private files/private URI scheme. Protocols can not and will not work with Drupal public file URIs.

## View Protocols
View protocols control who can view content. There are four options for view protocols:

| Scope | Label | Description |
|---|---|---|
Personal| Only me, this content is not ready to be shared.| The content is only visible to to the user that created it.
Public| Anyone, this is public content.| The content is visible to all site users.
Any|This content may be shared with members of ANY protocols listed.| To view the content the user must be a member of at least one of the given protocols and have the corresponding OG or site permission to view content of that type.
All|This content may only be shared with members belonging to ALL protocols listed.|To view the content the user must be a member of all of the given protocols and have the corresponding OG or site permission to view content of that type.

## Update Protocols
Update protocols control who can edit existing content.
| Scope | Label | Description |
|---|---|---|
Default|Use standard Mukurtu roles.| The default setting uses the value of the read scope to determine who can update the content. For personal, only the owning user can update. For public, this is not a valid selection. For any/all, the user must have the required protocol membership to view and have the corresponding OG or site permission to update content of that type.
Any|This content may be updated only by members of ANY protocols listed.| To update the content the user must be a member of any of the given protocols and have the corresponding OG or site permission to update content of that type.
All|This content may be updated only by members belonging to ALL protocols listed.| To update the content the user must be a member of all of the given protocols and have the corresponding OG or site permission to update content of that type.
