<?php

namespace Drupal\mukurtu_core\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\og\Og;
use Drupal\user\Entity\User;

class MukurtuUserController extends ControllerBase {
  use StringTranslationTrait;

  /**
   * Roles that community managers cannot block.
   */
  const PROTECTED_ROLES = ['administrator', 'mukurtu_manager'];

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

    // Also allow community managers (have 'manage members' in any community).
    $user = User::load($account->id());
    foreach (Og::getMemberships($user) as $membership) {
      if ($membership->getGroupBundle() === 'community' && $membership->hasPermission('manage members')) {
        return AccessResult::allowed();
      }
    }

    return AccessResult::forbidden();
  }

  public function approveAjax($uid) {
    $user = User::load($uid);
    $response = new AjaxResponse();

    if ($user && $user->status->value == 0) {
      $user->set('status', TRUE);
      try {
        $user->save();
      }
      catch (\Throwable $e) {
        // postSave() sends a notification email; if the user has no email
        // address the mail system throws a TypeError. The DB write already
        // completed, so we log and continue.
        \Drupal::logger('mukurtu_core')->warning('Could not send activation email for user @uid: @message', [
          '@uid' => $uid,
          '@message' => $e->getMessage(),
        ]);
      }
    }

    $redirect = \Drupal::request()->headers->get('referer') ?: Url::fromRoute('view.mukurtu_people.page_1')->toString();
    $response->addCommand(new RedirectCommand($redirect));
    return $response;
  }

  public function blockAjax($uid) {
    $user = User::load($uid);
    $response = new AjaxResponse();

    if ($user) {
      // Community managers cannot block administrators or Mukurtu managers.
      $account = $this->currentUser();
      if (!$account->hasPermission('administer users')) {
        $protected = array_intersect(self::PROTECTED_ROLES, $user->getRoles());
        if (!empty($protected)) {
          $response->addCommand(new MessageCommand('You do not have permission to block this user.', NULL, ['type' => 'error']));
          return $response;
        }
      }

      if ($user->status->value != 0) {
        $user->set('status', FALSE);
        try {
          $user->save();
        }
        catch (\Throwable $e) {
          \Drupal::logger('mukurtu_core')->warning('Could not send block email for user @uid: @message', [
            '@uid' => $uid,
            '@message' => $e->getMessage(),
          ]);
        }
      }
    }

    $redirect = \Drupal::request()->headers->get('referer') ?: Url::fromRoute('view.mukurtu_people.page_1')->toString();
    $response->addCommand(new RedirectCommand($redirect));
    return $response;
  }

}
