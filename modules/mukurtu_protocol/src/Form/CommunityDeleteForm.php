<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\ConfirmFormHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_protocol\Entity\CommunityInterface;

/**
 * Provides a form for deleting Community entities.
 *
 * While the community still has protocols or sub-communities, the form
 * explains what is blocking the delete instead of offering to delete.
 *
 * @ingroup mukurtu_protocol
 */
class CommunityDeleteForm extends ContentEntityDeleteForm {

  use DeleteBlockerListTrait;

  /**
   * Checks access to the delete form.
   *
   * @param \Drupal\mukurtu_protocol\Entity\CommunityInterface $community
   *   The community.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(CommunityInterface $community, AccountInterface $account): AccessResultInterface {
    /** @var \Drupal\mukurtu_protocol\CommunityAccessControlHandler $handler */
    // The route access check gets a form instance that hasn't been set up
    // by the entity type manager, so look the handler up directly.
    $handler = \Drupal::entityTypeManager()->getAccessControlHandler('community');
    return $handler->deleteFormAccess($community, $account);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\mukurtu_protocol\Entity\CommunityInterface $community */
    $community = $this->getEntity();
    $protocol_ids = array_keys($community->getProtocols());
    $child_ids = array_column($community->get('field_child_communities')->getValue(), 'target_id');
    if (empty($protocol_ids) && empty($child_ids)) {
      return parent::buildForm($form, $form_state);
    }

    $cacheability = new CacheableMetadata();

    if (!empty($protocol_ids)) {
      $sorted = $this->sortDeleteBlockers(['protocol' => $protocol_ids], $this->currentUser(), $cacheability);

      $form['protocols_warning'] = $this->buildDeleteBlockedWarning([
        '#type' => 'inline_template',
        '#template' => '{{ reason }} {{ action }}',
        '#context' => [
          'reason' => $this->formatPlural(count($protocol_ids),
            "This community can't be deleted because 1 cultural protocol still belongs to it.",
            "This community can't be deleted because @count cultural protocols still belong to it."
          ),
          'action' => $this->formatPlural(count($protocol_ids),
            'Delete this protocol, or remove this community from it, first.',
            'Delete these protocols, or remove this community from them, first.'
          ),
        ],
      ]);

      $form['protocols'] = $this->buildDeleteBlockerList($this->t('Cultural protocols in this community'), $sorted);

      if ($sorted['restricted'] > 0) {
        $form['protocols_restricted'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->formatPlural($sorted['restricted'],
            "You don't have access to 1 of these cultural protocols. Contact a community manager to update it.",
            "You don't have access to @count of these cultural protocols. Contact a community manager to update them."
          ),
        ];
      }
    }

    if (!empty($child_ids)) {
      $sorted = $this->sortDeleteBlockers(['community' => $child_ids], $this->currentUser(), $cacheability);

      $form['children_warning'] = $this->buildDeleteBlockedWarning([
        '#type' => 'inline_template',
        '#template' => '{{ reason }} {{ action }}',
        '#context' => [
          'reason' => $this->formatPlural(count($child_ids),
            "This community can't be deleted because it has 1 sub-community.",
            "This community can't be deleted because it has @count sub-communities."
          ),
          'action' => $this->formatPlural(count($child_ids),
            'Remove the sub-community first.',
            'Remove the sub-communities first.'
          ),
        ],
      ]);

      $form['children'] = $this->buildDeleteBlockerList($this->t('Sub-communities'), $sorted);
    }

    $form['actions'] = [
      '#type' => 'actions',
      'cancel' => ConfirmFormHelper::buildCancelLink($this, $this->getRequest()),
    ];

    $cacheability->addCacheableDependency($community)->applyTo($form);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // A protocol or child community could have been added after the form
    // loaded.
    /** @var \Drupal\mukurtu_protocol\Entity\CommunityInterface $community */
    $community = $this->getEntity();
    if (!$community->access('delete')) {
      $form_state->setRedirectUrl($community->toUrl('delete-form'));
      return;
    }
    parent::submitForm($form, $form_state);
  }

}
