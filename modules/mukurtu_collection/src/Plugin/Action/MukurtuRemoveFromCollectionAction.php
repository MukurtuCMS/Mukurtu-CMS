<?php

namespace Drupal\mukurtu_collection\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;

/**
 * VBO for removing content from collections.
 */
#[Action(
  id: 'mukurtu_remove_from_collection_action',
  label: new TranslatableMarkup('Remove from Collection'),
  type: 'node',
)]
class MukurtuRemoveFromCollectionAction extends MukurtuAddToCollectionAction {

  /**
   * {@inheritdoc}
   */
  protected function removes(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   *
   * Anything already in a collection can be taken out of it.
   */
  protected function isEligibleItem(NodeInterface $node): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function noContainersMessage(): TranslatableMarkup {
    return $this->t('There are no collections you can remove items from.');
  }

  /**
   * {@inheritdoc}
   */
  protected function doneMessage(string $label): TranslatableMarkup {
    return $this->t('Removed from @collection', ['@collection' => $label]);
  }

  /**
   * {@inheritdoc}
   */
  protected function skippedMessage(string $label): TranslatableMarkup {
    return $this->t('Not in @collection', ['@collection' => $label]);
  }

  /**
   * {@inheritdoc}
   */
  protected function failedMessage(string $label): TranslatableMarkup {
    return $this->t("Couldn't remove from @collection", ['@collection' => $label]);
  }

}
