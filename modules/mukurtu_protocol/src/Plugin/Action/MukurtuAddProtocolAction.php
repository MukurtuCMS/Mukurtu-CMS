<?php

namespace Drupal\mukurtu_protocol\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\mukurtu_protocol\CulturalProtocols;

/**
 * Bulk action that adds a cultural protocol to the selected items.
 */
#[Action(
  id: 'mukurtu_add_protocol_action',
  label: new TranslatableMarkup('Add Cultural Protocol'),
  confirm_form_route_name: 'views_bulk_operations.confirm',
  type: 'node',
)]
class MukurtuAddProtocolAction extends ProtocolChangeActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['protocol' => NULL];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $options = [];
    $ids = CulturalProtocols::getProtocolsByUserPermission(['apply protocol'], $this->currentUser);
    foreach ($ids ? $this->entityTypeManager->getStorage('protocol')->loadMultiple($ids) : [] as $protocol) {
      $options[$protocol->id()] = $this->label($protocol);
    }
    natcasesort($options);

    if (!$options) {
      $form['protocol'] = [
        '#type' => 'item',
        '#markup' => $this->t('There are no cultural protocols you can apply.'),
      ];
      // Nothing to apply; leave only Cancel.
      $form['actions']['submit']['#access'] = FALSE;
      return $form;
    }

    $form['protocol'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Cultural Protocol'),
      '#options' => $options,
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['protocol'] = (int) $form_state->getValue('protocol');
  }

  /**
   * {@inheritdoc}
   */
  protected function change(CulturalProtocolControlledInterface $item): ?TranslatableMarkup {
    $ids = $item->getProtocols();
    if (in_array($this->configuration['protocol'], $ids)) {
      return $this->t('Already has this protocol');
    }
    $ids[] = $this->configuration['protocol'];
    $item->setProtocols($ids);
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function doneMessage(): TranslatableMarkup {
    return $this->t('Protocol added');
  }

  /**
   * {@inheritdoc}
   */
  protected function revisionLogMessage(): string {
    return (string) $this->t('Added @protocol in a bulk operation.', ['@protocol' => $this->protocolLabel()]);
  }

  /**
   * {@inheritdoc}
   *
   * With "any" sharing a new protocol only widens access. With "all", members
   * of the existing protocols also need the new one.
   */
  protected function memberLossNotices(CulturalProtocolControlledInterface $before, CulturalProtocolControlledInterface $after, string $label): array {
    $protocol = $this->entityTypeManager->getStorage('protocol')->load($this->configuration['protocol']);
    if ($before->getSharingSetting() !== 'all' || !$protocol || $protocol->isOpen()) {
      return [];
    }
    return [
      $this->t("@item: members who aren't also in @protocol will lose access.", [
        '@item' => $label,
        '@protocol' => $this->protocolLabel(),
      ]),
    ];
  }

  /**
   * Returns the configured protocol's label.
   */
  protected function protocolLabel(): string {
    $protocol = $this->entityTypeManager->getStorage('protocol')->load($this->configuration['protocol']);
    return $protocol ? $this->label($protocol) : (string) $this->configuration['protocol'];
  }

}
