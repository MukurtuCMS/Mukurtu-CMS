<?php

namespace Drupal\search_api_solr\TypedData;

use Drupal\Core\TypedData\DataDefinitionInterface;

/**
 * Defines an interface for Solr field definitions.
 */
interface SolrFieldDefinitionInterface extends DataDefinitionInterface {

  /**
   * Gets an array of field properties.
   *
   * @return string[]
   *   An array of properties describing the solr schema. The array keys are
   *   single-character codes, and the values are human-readable labels.
   */
  public function getSchema();

  /**
   * Gets the "dynamic base" of this field.
   *
   * This typically looks like 'ss_*, and is used to aggregate fields based on
   * "hungarian" naming conventions.
   *
   * @return string
   *   The mask describing the solr aggregate field, if there is one.
   */
  public function getDynamicBase();

  /**
   * Determines whether this field is indexed.
   *
   * @return bool
   *   TRUE if the field is indexed, FALSE otherwise.
   */
  public function isIndexed();

  /**
   * Determines whether this field is tokenized.
   *
   * @return bool
   *   TRUE if the field is tokenized, FALSE otherwise.
   */
  public function isTokenized();

  /**
   * Determines whether this field is stored.
   *
   * @return bool
   *   TRUE if the field is stored, FALSE otherwise.
   */
  public function isStored();

  /**
   * Determines whether this field is multi-valued.
   *
   * @return bool
   *   TRUE if the field is multi-valued, FALSE otherwise.
   */
  public function isMultivalued();

  /**
   * Determines whether this field has stored term vectors.
   *
   * @return bool
   *   TRUE if the field has stored term vectors, FALSE otherwise.
   */
  public function isTermVectorStored();

  /**
   * Determines whether this field has the "termOffsets" option set.
   *
   * @return bool
   *   TRUE if the field has the "termOffsets" option set, FALSE otherwise.
   */
  public function isStoreOffsetWithTermVector();

  /**
   * Determines whether this field has the "termPositions" option set.
   *
   * @return bool
   *   TRUE if the field has the "termPositions" option set, FALSE otherwise.
   */
  public function isStorePositionWithTermVector();

  /**
   * Determines whether this field omits norms when indexing.
   *
   * @return bool
   *   TRUE if the field omits norms, FALSE otherwise.
   */
  public function isOmitNorms();

  /**
   * Determines whether this field is lazy-loaded.
   *
   * @return bool
   *   TRUE if the field is lazy-loaded, FALSE otherwise.
   */
  public function isLazy();

  /**
   * Determines whether this field is binary.
   *
   * @return bool
   *   TRUE if the field is binary, FALSE otherwise.
   */
  public function isBinary();

  /**
   * Determines whether this field is compressed.
   *
   * @return bool
   *   TRUE if the field is compressed, FALSE otherwise.
   */
  public function isCompressed();

  /**
   * Determines whether this field sorts missing entries first.
   *
   * @return bool
   *   TRUE if the field sorts missing entries first, FALSE otherwise.
   */
  public function isSortMissingFirst();

  /**
   * Determines whether this field sorts missing entries last.
   *
   * @return bool
   *   TRUE if the field sorts missing entries last, FALSE otherwise.
   */
  public function isSortMissingLast();

  /**
   * Determine whether this field may be suitable for use as a key field.
   *
   * Unfortunately, it seems like the best way to find an actual uniqueKey field
   * according to Solr is to examine the Solr core's schema.xml.
   *
   * @return bool
   *   Whether the field is suitable for use as a key.
   */
  public function isPossibleKey();

  /**
   * Determine whether a field is suitable for sorting.
   *
   * In order for a field to yield useful sorted results in Solr, it must be
   * indexed and not multivalued. If a sort field is tokenized, the tokenization
   * must yield only one token; multiple tokens can result in unpredictable sort
   * ordering. Unfortunately, there's no way to check whether a particular field
   * contains values with multiple tokens.
   *
   * @return bool
   *   Whether the field might be suitable for sorting.
   */
  public function isSortable();

  /**
   * Determine whether a field is suitable for fulltext search.
   *
   * Some fields are tokenized for sort and contain a single, all lowercase
   * value. These fields are not suitable for fulltext search, but there is no
   * general way to tell them apart from fields that are tokenized into multiple
   * terms.
   *
   * @return bool
   *   Whether the field might be suitable for fulltext search.
   */
  public function isFulltextSearchable();

  /**
   * Determine whether a field is suitable for filtering.
   *
   * Fields suitable for filtering must be non-fulltext.  A case-sensitive is
   * used.  When searching on this type of field, only full, exact values will
   * match.
   *
   * @return bool
   *   Whether the field might be suitable for filtering.
   */
  public function isFilterable();

}
