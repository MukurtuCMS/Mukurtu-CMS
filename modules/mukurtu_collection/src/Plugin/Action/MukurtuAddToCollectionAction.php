<?php

namespace Drupal\mukurtu_collection\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mukurtu_collection\Controller\MukurtuAddItemToCollectionController;
use Drupal\mukurtu_core\Plugin\Action\ContainerMembershipActionBase;
use Drupal\node\NodeInterface;

/**
 * VBO for adding content to collections.
 */
#[Action(
  id: 'mukurtu_add_to_collection_action',
  label: new TranslatableMarkup('Add to Collection'),
  type: 'node',
)]
class MukurtuAddToCollectionAction extends ContainerMembershipActionBase {

  /**
   * {@inheritdoc}
   */
  protected function containerBundle(): string {
    return 'collection';
  }

  /**
   * {@inheritdoc}
   */
  protected function itemsField(): string {
    return MUKURTU_COLLECTION_FIELD_NAME_ITEMS;
  }

  /**
   * {@inheritdoc}
   */
  protected function isEligibleItem(NodeInterface $node): bool {
    return MukurtuAddItemToCollectionController::isValidCollectionItemBundle($node);
  }

  /**
   * {@inheritdoc}
   */
  protected function ineligibleReason(): TranslatableMarkup {
    return $this->t("This type of content can't be added to a collection.");
  }

  /**
   * {@inheritdoc}
   */
  protected function selectLabel(): TranslatableMarkup {
    return $this->t('Select Collection');
  }

  /**
   * {@inheritdoc}
   */
  protected function noContainersMessage(): TranslatableMarkup {
    return $this->t('There are no collections you can add items to.');
  }

  /**
   * {@inheritdoc}
   */
  protected function doneMessage(string $label, string $url): TranslatableMarkup {
    return $this->t('Added to <a href=":url">@collection</a>', ['@collection' => $label, ':url' => $url]);
  }

  /**
   * {@inheritdoc}
   */
  protected function skippedMessage(string $label, string $url): TranslatableMarkup {
    return $this->t('Already in <a href=":url">@collection</a>', ['@collection' => $label, ':url' => $url]);
  }

  /**
   * {@inheritdoc}
   */
  protected function failedMessage(string $label): TranslatableMarkup {
    return $this->t("Couldn't add to @collection", ['@collection' => $label]);
  }

}
