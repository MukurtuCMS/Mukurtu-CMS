# Description
This module provides integration with the [Local Contexts Hub](https://localcontextshub.org/). There is information about what the Hub is and how to use it in their [API Implementation Guide](https://localcontexts.org/support/api-guide/).

## Projects versus Labels
This module provides Field Types, Formatters, and Widgets for both "Label" and "Projects".

The label fields permit cherry picking specific labels from supported projects. Project fields on the other hand reference an entire Local Contexts Hub Project, if supported, and will display all labels and notices contained within that project. This project oriented approach is the intended use case of the Local Contexts Hub, while the specific label approach represents the way in which users used Labels in Mukurtu CMS version 3.

## Supported Projects
This module provides forms to manage (add, remove) supported projects at the following levels:
* Site
* Community
* Cultural Protocol

A given user's supported projects consists of the site projects plus all group projects the user has membership in.

## Hub Data
All actual project/label/notice data lives on the Hub and can only be modified on the Hub. This data cannot be modified from within Mukurtu CMS. To avoid overwhelming the Hub API endpoint, Local Context projects are synced to custom tables in the database. See `mukurtu_local_contexts_schema` in `mukurtu_local_contexts.install` for the table schemas.

## Legacy Label Support
On some sites, there are some projects with IDs that reference "legacy" such as `default_tk` or `sitewide_tk`. These are a result of migrating from Mukurtu CMS version 3 to version 4. In version 3, users could customize their own versions of labels within their own Mukurtu CMS site. This is no longer supported, in an effort to encourage people to utilize the Local Contexts Hub. However, to smooth the transition, version 4 provides "legacy" support that will migrate their old version 3 labels and allow them to continue to use them with version 4 content. However in version 4 they will not be able to alter those legacy labels or add new legacy labels.
