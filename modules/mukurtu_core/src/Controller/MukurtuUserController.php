<?php

namespace Drupal\mukurtu_core\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Ajax\MessageCommand;

class MukurtuUserController extends ControllerBase {
  use StringTranslationTrait;

  /**
   * Check if user can administer users.
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access() {
    $account = $this->currentUser();
    if ($account->hasPermission('administer users')) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

  public function approveAjax($uid) {
    // This is the user we want to approve (unblock).
    $user = \Drupal\user\Entity\User::load($uid);
    $content = [];

    $response = new AjaxResponse();
    if ($user) {
      if ($user->status->value == 0) {
        // Unblock the user.
        $user->set('status', TRUE);
        $user->save();
      }

      $content['mukurtu_toggle_user_approval'] = [
        '#type' => 'link',
        '#title' => t('Block User'),
        '#url' => Url::fromRoute('mukurtu_core.block_user', ['uid' => $user->id()]),
        '#attributes' => [
          'class' => ['use-ajax'],
        ],
      ];
      $response->addCommand(new ReplaceCommand('.links a', $content));
      $response->addCommand(new MessageCommand('User approved successfully.'));
    }

    return $response;
  }

  public function blockAjax($uid) {
    // This is the user we want to approve (unblock).
    $user = \Drupal\user\Entity\User::load($uid);
    $content = [];

    $response = new AjaxResponse();
    if ($user) {
      if ($user->status->value != 0) {
        // Block the user.
        $user->set('status', FALSE);
        $user->save();
      }

      $content['mukurtu_toggle_user_approval'] = [
        '#type' => 'link',
        '#title' => t('Approve User'),
        '#url' => Url::fromRoute('mukurtu_core.approve_user', ['uid' => $user->id()]),
        '#attributes' => [
          'class' => ['use-ajax'],
        ],
      ];
      $response->addCommand(new ReplaceCommand('.links a', $content));
      $response->addCommand(new MessageCommand('User blocked successfully.'));
    }

    return $response;
  }

}
