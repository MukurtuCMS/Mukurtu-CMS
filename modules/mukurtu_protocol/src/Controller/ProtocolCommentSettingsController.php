<?php

namespace Drupal\mukurtu_protocol\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\mukurtu_protocol\Entity\ProtocolInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountInterface;

/**
 * Controller for protocol comment management.
 */
class ProtocolCommentSettingsController extends ControllerBase {

  /**
   * Page for managing protocol's comment settings.
   */
  public function content(ProtocolInterface $group) {
    $form_state = new FormState();
    $form_state->set('protocol', $group);
    $form = $this->formBuilder()->buildForm('Drupal\mukurtu_protocol\Form\ProtocolCommentSettingsForm', $form_state);
    return $form;
  }

  /**
   * Title callback for comment settings page.
   */
  public function getTitle(ProtocolInterface $group) {
    return $this->t("%protocol Comment Settings", ['%protocol' => $group->getName()]);
  }

  /**
   * Access check for redirect to management page.
   */
  public function access(AccountInterface $account, ProtocolInterface $group) {
    $membership = $group->getMembership($account);
    if ($membership && $membership->hasPermission('administer comments')) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
