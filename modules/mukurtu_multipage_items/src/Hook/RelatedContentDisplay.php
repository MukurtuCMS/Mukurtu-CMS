<?php

declare(strict_types=1);

namespace Drupal\mukurtu_multipage_items\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mukurtu_multipage_items\MultipageItemManager;
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
   * @param \Drupal\mukurtu_multipage_items\MultipageItemManager $multipageItemManager
   *   The multipage item manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager, protected MultipageItemManager $multipageItemManager) {}

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
    if ($mpi = $this->multipageItemManager->getMultipageEntity($node)) {
      $form['multipage_item'] = [
        '#type' => 'item',
        '#title' => $this->t('Multipage Item'),
        '#markup' => sprintf('<a href="%s">%s</a>', $mpi->toUrl()->toString(), $mpi->label()),
        '#access' => $mpi->access('view'),
      ];
      $form['#fieldgroups']['group_related_content']->children[] = 'multipage_item';
      $form['#group_children']['multipage_item'] = 'group_related_content';
    }
  }

}
