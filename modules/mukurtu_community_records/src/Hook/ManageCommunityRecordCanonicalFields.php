<?php

declare(strict_types=1);

namespace Drupal\mukurtu_community_records\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\mukurtu_dictionary\Entity\DictionaryWordInterface;
use Drupal\node\NodeInterface;

/**
 * Manages the canonical fields for community records.
 */
class ManageCommunityRecordCanonicalFields {

  /**
   * Community records each preserve certain fields from the original record.
   *
   * List of bundles that can be community records and the fields where the
   * original record has the canonical value.
   *
   * @var array
   */
  protected array $canonicalFieldsPerBundle = [
    'digital_heritage' => [
      'field_media_assets',
    ],
    'dictionary_word' => [
      'title',
    ],
    'word_list' => [
      'field_words',
    ],
    'collection' => [
      'field_items_in_collection',
    ],
  ];

  /**
   * Constructs a new ManageCommunityRecordCanonicalFields object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_node_form_alter')]
  public function nodeFormAlter(&$form, FormStateInterface $form_state, $form_id): void {
    /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();
    $node = $form_object->getEntity();
    if (!$node instanceof NodeInterface) {
      return;
    }
    $original_target_id = mukurtu_community_records_is_community_record($node);
    if (!$original_target_id) {
      return;
    }

    if ($node instanceof DictionaryWordInterface) {
      // Here we're wanting to restrict the title, but we can't just leave it
      // empty because it's required. We need to go with the title of the
      // original record.
      $original_record = $this->getOriginalRecord($node);
      $form['title']['widget'][0]['value']['#default_value'] = $original_record->getTitle() ?? NULL;
    }

    $form[MUKURTU_COMMUNITY_RECORDS_FIELD_NAME_ORIGINAL_RECORD]['#access'] = FALSE;
    if (isset($this->canonicalFieldsPerBundle[$node->bundle()])) {
      foreach ($this->canonicalFieldsPerBundle[$node->bundle()] as $field) {
        $form[$field]['#access'] = FALSE;
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_view().
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    assert($entity instanceof NodeInterface);
    $original_record = $this->getOriginalRecord($entity);
    if (!$original_record) {
      return;
    }

    if (!isset($this->canonicalFieldsPerBundle[$entity->bundle()])) {
      return;
    }

    foreach ($this->canonicalFieldsPerBundle[$entity->bundle()] as $field) {
      if (!$original_record->hasField($field) || $original_record->get($field)->isEmpty()) {
        continue;
      }
      $build[$field] = $original_record->{$field}->view($view_mode);
    }
  }

  /**
   * Gets the original record for a community record node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The community record node to get the original record for.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The original record node if found, NULL otherwise.
   */
  protected function getOriginalRecord(NodeInterface $node): ?NodeInterface {
    $original_target_id = mukurtu_community_records_is_community_record($node);
    if (!$original_target_id) {
      return NULL;
    }
    $node = $this->entityTypeManager->getStorage('node')->load($original_target_id);
    assert($node instanceof NodeInterface);
    return $node;
  }

}
