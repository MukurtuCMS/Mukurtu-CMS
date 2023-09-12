# Migrating from Mukurtu CMS version 3

## What will be migrated?
- [Taxonomy Vocabularies](#taxonomy-vocabulary-mapping)
- Communities
  - Community Memberships
- Cultural Protocols
  - Cultural Protocol Memberships
- Users
- Content
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


### Taxonomy Vocabulary Mapping
In Mukurtu CMS version 4, some taxonomy vocabularies have been renamed or no longer exist. The following table shows the mapping between the two versions and the corresponding migration ID.
|Version 3|Version 4|Migration ID|
|-|-|-|
|Authors|Authors|`mukurtu_cms_v3_terms_authors`|
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
|Scald tags|???||
|Subject|Subject|`mukurtu_cms_v3_terms_subject`|
|Tags|Keywords|`mukurtu_cms_v3_terms_keywords`|
|Teacher|N/A||
|Type|Type|`mukurtu_cms_v3_terms_type`|
|Unit Length|N/A||
|Week of|N/A||


### Entity/Bundle Mapping
|Name|Version 3 Entity Type ID|Version 3 Bundle|Version 4 Entity Type ID|Version 4 Bundle|Migration ID|
|-|-|-|-|-|-|
|Community|node|community|community|community|`mukurtu_cms_v3_communities`|
|Cultural Protocol|node|cultural_protocol_group|protocol|protocol|`mukurtu_cms_v3_cultural_protocols`|
|Article|node|article|???|???||
|Basic Page|node|page|???|???||
|Collection|node|collection|node|collection||
|Dictionary Word|node|dictionary_word|node|dictionary_word|`mukurtu_cms_v3_dictionary_word`|
|Digital Heritage|node|digital_heritage|node|digital_heritage|`mukurtu_cms_v3_digital_heritage`|
|Digital Heritage Admin Notification|node|dhan|N/A|N/A||
|Fixity Check|node|fixity_check|N/A|N/A||
|Language Community|node|language_community|???|???||
|Lesson|node|lesson|N/A|N/A||
|Panel|node|panel|N/A|N/A||
|Person|node|person|node|person||
|Personal Collection|node|personal_collection|personal_collection|personal_collection||
|Unit Plan|node|unit_plan|N/A|N/A||
|Word List|node|word_list|node|word_list||

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

### Formatted Text Formats
The following table shows the formatted text format mapping between version 3 and version 4.
|Version 3|Version 4|
|-|-|
|filtered_html|basic_html|
|plain_text|plain_text|
|full_html|full_html|
|markdown|???|
|ds_code|???|

### Files
> You MUST have Drupal private file storage configured prior to migration.

|Type|Migration ID|
|-|-|
|Public|`mukurtu_cms_v3_file`|
|Private|`mukurtu_cms_v3_file_private`|

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
