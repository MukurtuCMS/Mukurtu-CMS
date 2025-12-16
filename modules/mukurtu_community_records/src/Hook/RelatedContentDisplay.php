<?php

declare(strict_types=1);

namespace Drupal\mukurtu_community_records\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Adds related community records / original record content to the node form.
 */
class RelatedContentDisplay {
  use StringTranslationTrait;

  /**
   * Constructs a new RelatedContentDisplay object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_node_form_alter', order: new OrderAfter(['field_group'], [['field_group_form_alter']]))]
  public function nodeFormAlter(&$form, FormStateInterface $form_state, $form_id): void {
    $node = $form_state->getFormObject()->getEntity();
    if (!$node instanceof NodeInterface) {
      return;
    }
    // New nodes are not yet associated with a community record at all.
    if ($node->isNew()) {
      return;
    }
    // Lets not bother if we don't have a Related Content group.
    if (!isset($form['#fieldgroups']['group_related_content'])) {
      return;
    }
    if ($original = $this->getOriginalRecord($node)) {
      $form['original_record'] = [
        '#type' => 'item',
        '#title' => $this->t('Original Record'),
        '#markup' => sprintf('<a href="%s">%s</a>', $original->toUrl()->toString(), $original->label()),
        '#access' => $original->access('view'),
      ];
      $form['#fieldgroups']['group_related_content']->children[] = 'original_record';
      $form['#group_children']['original_record'] = 'group_related_content';
    }

    if ($community_records = $this->getCommunityRecords($node)) {
      // Filter out community records that the user doesn't have access to.
      $community_records = array_filter($community_records, fn(EntityInterface $record) => $record->access('view'));
      $form['community_records'] = [
        '#type' => 'item',
        '#title' => $this->t('Community Records'),
        '#markup' => implode('<br />', array_map(fn(EntityInterface $record) => sprintf('<a href="%s">%s</a>', $record->toUrl()->toString(), $record->label()), $community_records)),
      ];
      $form['#fieldgroups']['group_related_content']->children[] = 'community_records';
      $form['#group_children']['community_records'] = 'group_related_content';
    }
  }

  /**
   * Get the original record for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to get the original record for.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The original record node if found, NULL otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getOriginalRecord(NodeInterface $node): ?EntityInterface {
    $original_target_id = mukurtu_community_records_is_community_record($node);
    if (!$original_target_id) {
      return NULL;
    }
    $original = $this->entityTypeManager->getStorage('node')->load($original_target_id);
    if (!$original instanceof EntityInterface) {
      return NULL;
    }
    return $original;
  }

  /**
   * Get community records for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to get community records for.
   *
   * @return array
   *   An array of community record nodes.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getCommunityRecords(NodeInterface $node): array {
    $community_record_ids = mukurtu_community_records_is_original_record($node);
    return $community_record_ids ? $this->entityTypeManager->getStorage('node')->loadMultiple($community_record_ids) : [];
  }

}
