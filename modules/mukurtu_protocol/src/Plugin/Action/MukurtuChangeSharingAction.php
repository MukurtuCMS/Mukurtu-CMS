<?php

namespace Drupal\mukurtu_protocol\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\mukurtu_protocol\CulturalProtocols;

/**
 * Bulk action that sets the sharing setting of the selected items.
 */
#[Action(
  id: 'mukurtu_change_sharing_action',
  label: new TranslatableMarkup('Change Sharing Setting'),
  confirm_form_route_name: 'views_bulk_operations.confirm',
  type: 'node',
)]
class MukurtuChangeSharingAction extends ProtocolChangeActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['sharing' => NULL];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['sharing'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select Sharing Setting'),
      '#options' => CulturalProtocols::getItemSharingSettingOptions(),
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['sharing'] = $form_state->getValue('sharing');
  }

  /**
   * {@inheritdoc}
   */
  protected function change(CulturalProtocolControlledInterface $item): ?TranslatableMarkup {
    if ($item->getSharingSetting() === $this->configuration['sharing']) {
      return $this->t('Already uses this sharing setting');
    }
    $item->setSharingSetting($this->configuration['sharing']);
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function doneMessage(): TranslatableMarkup {
    return $this->t('Sharing setting changed');
  }

  /**
   * {@inheritdoc}
   */
  protected function revisionLogMessage(): string {
    return (string) $this->t('Changed sharing to @setting in a bulk operation.', ['@setting' => $this->configuration['sharing']]);
  }

  /**
   * {@inheritdoc}
   *
   * Moving from "any" to "all" narrows access whenever there is more than one
   * protocol. Moving to "any" only widens it.
   */
  protected function memberLossNotices(CulturalProtocolControlledInterface $before, CulturalProtocolControlledInterface $after, string $label): array {
    if ($after->getSharingSetting() !== 'all' || count($before->getProtocols()) < 2) {
      return [];
    }
    return [$this->t("@item: members who aren't in all of its protocols will lose access.", ['@item' => $label])];
  }

}
