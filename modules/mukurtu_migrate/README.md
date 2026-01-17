# Migrating from Mukurtu CMS version 3

## What will be migrated?
- [Taxonomy Vocabularies](#taxonomy-vocabulary-mapping)
- Communities
  - Community Memberships
- [Language Communities](#language-communities)
  - [Language Community Memberships](#language-community-membership-mapping)
    - Language Community Community Memberships
    - Language Community Protocol Memberships
- Cultural Protocols
  - Cultural Protocol Memberships
- [Users](#users)
- Content
  - Article
  - Basic Page
  - Collection
  - Dictionary Word
  - Digital Heritage
  - Person
  - Word List
- Media
  - Audio
  - File
  - Image
  - Video
- [Files](#files)
  - Public
  - Private
- Personal Collections
- Comments
- Community Record Relationships
- Multi-page Items
- Taxonomy Record Relationships
- [Local Contexts Legacy TK Labels](#local-contexts-legacy-tk-labels)
- [Media Content Warnings](#media-content-warnings)

### Users
User accounts are migrated in two different migrations. Both should be used in migration_lookup plugins when dealing with users.
|Migration ID|Description|
|-|-|
|mukurtu_cms_v3_users_uid1|Migrates UID 1. Does not migrate name, password, or email.|
|mukurtu_cms_v3_users|Migrates all other users and includes all stock Drupal 7 user fields.|


### Taxonomy Vocabulary Mapping
In Mukurtu CMS version 4, some taxonomy vocabularies have been renamed or no longer exist. The following table shows the mapping between the two versions and the corresponding migration ID.
|Version 3|Version 4|Migration ID|
|-|-|-|
|Authors|N/A||
|Category|Category|`mukurtu_cms_v3_terms_category`|
|Class Period Length|N/A||
|Contributor|Contributor|`mukurtu_cms_v3_terms_contributor`|
|Creator|Creator|`mukurtu_cms_v3_terms_creator`|
|Format|Format|`mukurtu_cms_v3_terms_format`|
|Grade Level|N/A||
|Interpersonal Relationship|Interpersonal Relationship|`mukurtu_cms_v3_terms_interpersonal_relationship`|
|Language|Language|`mukurtu_cms_v3_terms_language`|
|Media Folders|N/A||
|Media Type|N/A||
|Part of Speech|Word Type|`mukurtu_cms_v3_terms_word_type`|
|People|People|`mukurtu_cms_v3_terms_people`|
|Publisher|Publisher|`mukurtu_cms_v3_terms_publisher`|
|Scald tags|Media Tags|`mukurtu_cms_v3_terms_media_tags`|
|Subject|Subject|`mukurtu_cms_v3_terms_subject`|
|Tags|Keywords|`mukurtu_cms_v3_terms_keywords`|
|Teacher|N/A||
|Type|Type|`mukurtu_cms_v3_terms_type`|
|Unit Length|N/A||
|Week of|N/A||

### Language Communities
In Mukurtu CMS version 4, language communities no longer exist. During migration, version 3 language communities are transformed into version 4 communities, migrating only the source field 'title'. A new, open protocol of the same title is created and automatically assigned to the new community.

### Language Community Membership Mapping
Below are the mappings for language community memberships:

Language Community Community Memberships (ID: `mukurtu_cms_v3_language_community_community_memberships`)
|Version 3 Role Name|Version 4 Role Name|
|-|-|
|administrator member|community-community-community_manager|

Language Community Protocol Memberships (ID: `mukurtu_cms_v3_language_community_protocol_memberships`)
|Version 3 Role Name|Version 4 Role Name|
|-|-|
|language contributor|protocol-protocol-language_contributor|
|language steward|protocol-protocol-language_steward|

### Entity/Bundle Mapping
|Name|Version 3 Entity Type ID|Version 3 Bundle|Version 4 Entity Type ID|Version 4 Bundle|Migration ID|
|-|-|-|-|-|-|
|Community|node|community|community|community|`mukurtu_cms_v3_communities`|
|Language Community|node|language_community|community|community|`mukurtu_cms_v3_language_communities`|
|Cultural Protocol|node|cultural_protocol_group|protocol|protocol|`mukurtu_cms_v3_cultural_protocols`|
|Language Community Protocol|node|language_community|protocol|protocol|`mukurtu_cms_v3_language_community_protocols`|
|Article|node|article|node|article|`mukurtu_cms_v3_article`|
|Basic Page|node|page|node|page|`mukurtu_cms_v3_page`|
|Collection|node|collection|node|collection|`mukurtu_cms_v3_collection`|
|Dictionary Word|node|dictionary_word|node|dictionary_word|`mukurtu_cms_v3_dictionary_word`|
|Digital Heritage|node|digital_heritage|node|digital_heritage|`mukurtu_cms_v3_digital_heritage`|
|Digital Heritage Admin Notification|node|dhan|N/A|N/A||
|Fixity Check|node|fixity_check|N/A|N/A||
|Language Community|node|language_community|???|???||
|Lesson|node|lesson|N/A|N/A||
|Panel|node|panel|N/A|N/A||
|Person|node|person|node|person|`mukurtu_cms_v3_person`|
|Personal Collection|node|personal_collection|personal_collection|personal_collection|`mukurtu_cms_v3_personal_collection`|
|Unit Plan|node|unit_plan|N/A|N/A||
|Word List|node|word_list|node|word_list|`mukurtu_cms_v3_word_list`|

### Migration from Scald to Drupal Media Entities
Mukurtu CMS version 4 uses Drupal media entities in lieu of Scald atoms. The table below shows the mappings.

> Note that Audio has been split into two separate bundles, local audio and Soundcloud.

> Note that Video has been split into two separate bundles, local video and remote video.

|Name|Version 3 Scald Type|Version 4 Entity Type ID|Version 4 Bundle|Migration ID|
|-|-|-|-|-|
|Audio|audio|media|audio|`mukurtu_cms_v3_media_audio`|
|Audio|audio|media|soundcloud|???|
|File|file|media|document|`mukurtu_cms_v3_media_document`|
|Image|image|media|image|`mukurtu_cms_v3_media_image`|
|Video|video|media|video|`mukurtu_cms_v3_media_video`|
|Video|video|media|remote_video||

### Media Content Warnings
In v3, Media content warnings are stored in three database variables (found in the `variable` table):
- `mukurtu_content_warning_people`
- `mukurtu_content_warning_taxonomy`
- `mukurtu_content_warning_scald_contexts`

After migration, these settings are stored in config as `mukurtu_content_warnings.settings`.

> Note: v4 currently doesn't have a way to translate scald contexts into media contexts, so the settings at `mukurtu_content_warning_scald_contexts` will just sit in config for now.

### Formatted Text Formats
The following table shows the formatted text format mapping between version 3 and version 4.

> Note that markdown and Display Suite text formats will not be migrated.

|Version 3|Version 4|
|-|-|
|filtered_html|basic_html|
|plain_text|plain_text|
|full_html|full_html|
|markdown|N/A|
|ds_code|N/A|

### Files
> You MUST have Drupal private file storage configured prior to migration.

|Type|Migration ID|
|-|-|
|Public|`mukurtu_cms_v3_file`|
|Private|`mukurtu_cms_v3_file_private`|

### Local Contexts Legacy TK Labels
In v3, there are TK labels that are outdated and no longer used by the Local Contexts Hub. There are three types of labels: community-customized, sitewide-customized, and not customized (default). However in v4, Local Context TK labels are always attached to a Local Contexts project. During migration from v3, a project is generated for sitewide labels, default labels, and for each community that has customized TK labels. Additional group- and site-supported projects are created for compatibility with v4.

|Type|Migration ID|
|-|-|
|Community TK Labels|`mukurtu_cms_v3_legacy_tk_community_labels`|
|Community TK Projects|`mukurtu_cms_v3_legacy_tk_community_projects`|
|Group Supported Community TK Projects|`mukurtu_cms_v3_legacy_tk_community_projects_group_supported`|
|Default TK Labels|`mukurtu_cms_v3_legacy_tk_default_labels`|
|Default TK Project|`mukurtu_cms_v3_legacy_tk_default_project`|
|Site Supported Default TK Project|`mukurtu_cms_v3_legacy_tk_default_project_site_supported`|
|Sitewide TK Labels|`mukurtu_cms_v3_legacy_tk_sitewide_labels`|
|Sitewide TK Project|`mukurtu_cms_v3_legacy_tk_sitewide_project`|
|Site Supported Sitewide TK Labels|`mukurtu_cms_v3_legacy_tk_sitewide_project_site_supported`|

### Article Field Migration
The following table shows the fields that will be migrated from version 3 for Articles.

|Source Field|
|-|
|body|
|changed|
|created|
|field_category|
|field_image|
|field_tags|
|status|
|title|

### Basic Page Field Migration
The following table shows the fields that will be migrated from version 3 for Basic Pages.

|Source Field|
|-|
|body|
|changed|
|created|
|field_media_asset|
|status|
|title|

### Collection Field Migration
The following table shows the fields that will be migrated from version 3 for Collections.

|Source Field|
|-|
|changed|
|created|
|field_collection_credit|
|field_collection_image|
|field_collections_child_coll|
|field_collections_parent_coll|
|field_description|
|field_digital_heritage_items|
|field_item_privacy_setting|
|field_related_content|
|field_summary|
|field_tags|
|og_group_ref|
|status|
|title|

### Digital Heritage Field Migration
The following table shows the fields that will be migrated from version 3 for Digital Heritage.

|Source Field|
|-|
|title|
|field_summary|
|field_media_asset|
|og_group_ref|
|field_item_privacy_setting|
|field_creator|
|field_category|
|field_contributor|
|field_original_date|
|field_date|
|body|
|field_tk_body|
|field_description|
|field_tags|
|field_publisher|
|field_rights|
|field_licence_trad|
|field_licence_std_cc|
|field_format|
|field_dh_type|
|field_identifier|
|field_language|
|field_source|
|field_subject|
|field_people|
|field_transcription|
|field_coverage|
|field_coverage_description|
|field_external_links|
|field_community_record_children|
|field_book_children|
|field_book_parent|
|field_related_content|
|field_collection|
|field_personal_collections|
|field_community_record_parent|

### Dictionary Word Field Migration
The following table shows the fields that will be migrated from version 3 for Dictionary Word.

> Note: The automatically-generated open protocol for language communities will be added onto the cultural protocols of the dictionary word during migration.

|Source Field|
|-|
|field_dictionary_word_language|
|field_item_privacy_setting|
|field_related_content|
|field_tags|
|field_word_entry|
|title|

### Multi-page Items
Multi-pages Items are a bespoke entity type in version 4. The title of page 1 from version 3 will be used as the title in version 4.

Migration ID: `mukurtu_cms_v3_multipage_item`

|Source Fields Migrated|
|-|
|changed|
|created|
|field_pages|
|status|
|title|

### Personal Collection Field Migration
The following table shows the fields that will be migrated from version 3 for Personal Collections.

|Source Field|
|-|
|changed|
|created|
|field_collection_credit|
|field_collection_image|
|field_collections_child_coll|
|field_collections_parent_coll|
|field_description|
|field_digital_heritage_items|
|field_item_privacy_setting|
|field_related_content|
|field_summary|
|field_tags|
|field_user_id|
|og_group_ref|
|status|
|title|

### Person Field Migration
The following table shows the fields that will be migrated from version 3 for Person.

|Source Field|
|-|
|changed|
|created|
|field_date_born|
|field_date_died|
|field_deceased|
|field_media_asset|
|field_mukurtu_terms|
|field_related_content|
|field_related_people|
|field_tags|
|status|
|title|

### Taxonomy Term - Base Field Migration
The following table shows the base fields/sub-fields that will be migrated for all taxonomy terms in Mukurtu CMS version 3.
|Source Fields Migrated|
|-|
|tid|
|language|
|weight|
|parent|
|timestamp|
|name|
|description/value|
|[description/format](#formatted-text-formats)|



### Word List Field Migration
The following table shows the fields that will be migrated from version 3 for Word List.

> Note: The automatically-generated open protocol for language communities will be added onto the cultural protocols of the word list during migration.

|Source Field|
|-|
|changed|
|created|
|field_collection_credit|
|field_collection_image|
|field_description|
|field_related_content|
|field_tags|
|field_words|
|status|
|title|
