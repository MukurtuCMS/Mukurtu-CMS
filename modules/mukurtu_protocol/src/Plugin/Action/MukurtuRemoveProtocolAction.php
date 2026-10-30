<?php

namespace Drupal\mukurtu_protocol\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;

/**
 * Bulk action that removes a cultural protocol from the selected items.
 */
#[Action(
  id: 'mukurtu_remove_protocol_action',
  label: new TranslatableMarkup('Remove Cultural Protocol'),
  confirm_form_route_name: 'views_bulk_operations.confirm',
  type: 'node',
)]
class MukurtuRemoveProtocolAction extends MukurtuAddProtocolAction {

  /**
   * {@inheritdoc}
   */
  protected function change(CulturalProtocolControlledInterface $item): ?TranslatableMarkup {
    $ids = $item->getProtocols();
    if (!in_array($this->configuration['protocol'], $ids)) {
      return $this->t("Doesn't have this protocol");
    }
    // An item with no protocols is visible only to its owner.
    if (count($ids) === 1) {
      return $this->t("Can't remove the item's only protocol");
    }
    $item->setProtocols(array_values(array_diff($ids, [$this->configuration['protocol']])));
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function doneMessage(): TranslatableMarkup {
    return $this->t('Protocol removed');
  }

  /**
   * {@inheritdoc}
   */
  protected function revisionLogMessage(): string {
    return (string) $this->t('Removed @protocol in a bulk operation.', ['@protocol' => $this->protocolLabel()]);
  }

  /**
   * {@inheritdoc}
   *
   * With "all" sharing, dropping a protocol only widens access. With "any",
   * members who reached the item only through this protocol lose it.
   */
  protected function memberLossNotices(CulturalProtocolControlledInterface $before, CulturalProtocolControlledInterface $after, string $label): array {
    // Everyone is a member of an open protocol; losing one is covered by the
    // "visible to everyone" notice.
    $protocol = $this->entityTypeManager->getStorage('protocol')->load($this->configuration['protocol']);
    if ($before->getSharingSetting() === 'all' || !$protocol || $protocol->isOpen()) {
      return [];
    }
    return [
      $this->t("@item: members of @protocol who aren't in its other protocols will lose access.", [
        '@item' => $label,
        '@protocol' => $this->protocolLabel(),
      ]),
    ];
  }

}
