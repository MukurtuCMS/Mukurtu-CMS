## Description
This module provides media content warnings--custom code to enable click-through overlays on top of media tagged with sensitive taxonomy terms. Supported terms are terms in either the People and/or Media Tags taxonomy vocabularies.

Settings for media content warnings are found on the dashboard under Site Configuration (or at /admin/config/mukurtu/content-warnings).

## Requirements
### People warnings
1. Enable taxonomy records on the People vocabulary (at /admin/config/mukurtu/taxonomy/records)
2. Enable People warnings at /admin/config/mukurtu/content-warnings
3. When creating a media item that depicts or references a Person record, and that person is deceased, add that person's name to the People vocabulary.
4. Add the media item from the previous step to the media assets field of its corresponding person record.
5. On that person record, add the person's corresponding People term to the Representative Terms field on the Relations tab to see the content warning overlay.

### Taxonomy term (Media Tags) warnings
1. Enable taxonomy records on the Media Tags vocabulary (at /admin/config/mukurtu/taxonomy/records)
2. Add terms to the Media Tags vocabulary.
3. Customize your content warnings at /admin/config/mukurtu/content-warnings. The media tags on your site are the terms you can choose from. You can customize the warning text for each term.
4. Attach this term to a media item's Media Tags field.
5. Add this media item to any content item's media assets field to see the content warning overlay.
