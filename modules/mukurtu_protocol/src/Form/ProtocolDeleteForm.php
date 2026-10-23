<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\ConfirmFormHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_protocol\Entity\ProtocolInterface;

/**
 * Provides a form for deleting Protocol entities.
 *
 * While content or media still uses the protocol, the form explains what is
 * blocking the delete instead of offering to delete.
 *
 * @ingroup mukurtu_protocol
 */
class ProtocolDeleteForm extends ContentEntityDeleteForm {

  use DeleteBlockerListTrait;

  /**
   * Checks access to the delete form.
   *
   * @param \Drupal\mukurtu_protocol\Entity\ProtocolInterface $protocol
   *   The protocol.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(ProtocolInterface $protocol, AccountInterface $account): AccessResultInterface {
    /** @var \Drupal\mukurtu_protocol\ProtocolAccessControlHandler $handler */
    // The route access check gets a form instance that hasn't been set up
    // by the entity type manager, so look the handler up directly.
    $handler = \Drupal::entityTypeManager()->getAccessControlHandler('protocol');
    return $handler->deleteFormAccess($protocol, $account);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\mukurtu_protocol\Entity\ProtocolInterface $protocol */
    $protocol = $this->getEntity();
    $ids = $protocol->getReferencingEntityIds();
    $total = array_sum(array_map('count', $ids));
    if ($total === 0) {
      return parent::buildForm($form, $form_state);
    }

    $cacheability = new CacheableMetadata();
    $sorted = $this->sortDeleteBlockers($ids, $this->currentUser(), $cacheability);

    $form['warning'] = $this->buildDeleteBlockedWarning([
      '#type' => 'inline_template',
      '#template' => '{{ reason }} {{ action }}',
      '#context' => [
        'reason' => $this->buildBlockedReason($total),
        'action' => $this->formatPlural($total,
          'Remove the protocol from this item first.',
          'Remove the protocol from these items first.'
        ),
      ],
    ]);

    $form['blockers'] = $this->buildDeleteBlockerList($this->t('Items using this protocol'), $sorted);

    if ($sorted['restricted'] > 0) {
      // Items can be hidden for any reason, such as a strict protocol or an
      // unpublished draft, so don't say which.
      $form['restricted'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->formatPlural($sorted['restricted'],
          "You don't have access to 1 of these items. Contact a protocol steward to update it.",
          "You don't have access to @count of these items. Contact a protocol steward to update them."
        ),
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
      'cancel' => ConfirmFormHelper::buildCancelLink($this, $this->getRequest()),
    ];

    $cacheability->addCacheableDependency($protocol)->applyTo($form);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Content could have started using the protocol after the form loaded.
    /** @var \Drupal\mukurtu_protocol\Entity\ProtocolInterface $protocol */
    $protocol = $this->getEntity();
    $ids = $protocol->getReferencingEntityIds();
    $total = array_sum(array_map('count', $ids));
    if ($total > 0) {
      $this->messenger()->addError($this->buildBlockedReason($total));
      $form_state->setRedirectUrl($protocol->toUrl('delete-form'));
      return;
    }
    parent::submitForm($form, $form_state);
  }

  /**
   * Builds the reason a protocol can't be deleted.
   *
   * @param int $total
   *   The number of items using the protocol.
   *
   * @return \Drupal\Core\StringTranslation\PluralTranslatableMarkup
   *   The reason.
   */
  protected function buildBlockedReason(int $total) {
    return $this->formatPlural($total,
      "This cultural protocol can't be deleted because 1 item still uses it.",
      "This cultural protocol can't be deleted because @count items still use it."
    );
  }

}
