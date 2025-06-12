## Description
This module provides media content warnings--click-through overlays on top of media tagged with sensitive taxonomy terms ("taxonomy term warnings"), or media that depicts and/or references a deceased person ("people warnings"). Supported terms are those in the People or Media Tags taxonomy vocabularies.

Media content warnings settings can be found on the dashboard under Site Configuration at `/admin/config/mukurtu/content-warnings`.

## Requirements
### People warnings
1. Enable taxonomy records on the `People` vocabulary at `/admin/config/mukurtu/taxonomy/records`. (admin only)
2. Enable People warnings at `/admin/config/mukurtu/content-warnings`. (admin only)
3. Create a term in the `People` taxonomy vocabulary of the deceased person's name, e.g. 'Alice'. (anyone with edit permissions)
4. Create a media item representing the deceased person; add the `People term` from the previous step to the `People field`. Save the media item.
5. Add the media item from the previous step to the `Media Assets` field of its corresponding person record.
6. On that person record, add the person's corresponding People term ('Alice') to the `Representative Terms` field.
7. Mark the person as 'deceased' using the checkbox near the bottom of the form.
8. After saving the person record, the media should be overlaid with a content warning.

### Taxonomy term (Media Tags) warnings
1. Enable taxonomy records on the Media Tags vocabulary (at /admin/config/mukurtu/taxonomy/records)
2. Add one or more trigger terms to the Media Tags vocabulary.
3. Customize your content warnings at /admin/config/mukurtu/content-warnings. The media tags on your site are the terms you can choose from. You can customize the warning text for each term.
4. Attach this term to a media item's Media Tags field.
5. Add this media item to any content item's media assets field to see the content warning overlay.

This module provides the `Apply Media Content Warnings` OG permission to protocols, see `MukurtuContentWarningsEventSubscriber`.
This module also provides the sitewide permission `Create media content warnings`, see `mukurtu_content_warnings.permissions.yml`.
