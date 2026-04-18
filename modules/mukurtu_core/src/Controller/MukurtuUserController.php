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

      // Prompt the approving user to also add the new member to communities.
      $add_url = Url::fromRoute('mukurtu_protocol.add_user_to_community', ['user' => $uid])->toString();
      \Drupal::messenger()->addStatus($this->t(
        '@name has been approved. <a href="@url">Add them to a community</a>.',
        ['@name' => $user->getDisplayName(), '@url' => $add_url]
      ));
    }

    $redirect = \Drupal::request()->headers->get('referer') ?: Url::fromRoute('view.mukurtu_people.page_1')->toString();
    $response->addCommand(new RedirectCommand($redirect));
    return $response;
  }

  public function blockAjax($uid) {
    $user = User::load($uid);
    $response = new AjaxResponse();

    if ($user) {
      $account = $this->currentUser();
      if (!$account->hasPermission('administer users')) {
        // Community managers cannot block administrators or Mukurtu managers.
        $protected = array_intersect(self::PROTECTED_ROLES, $user->getRoles());
        if (!empty($protected)) {
          $response->addCommand(new MessageCommand('You do not have permission to block this user.', NULL, ['type' => 'error']));
          return $response;
        }

        // Community managers may only block users who share one of their
        // managed communities.
        $current_user = User::load($account->id());
        $cm_community_ids = [];
        foreach (Og::getMemberships($current_user) as $m) {
          if ($m->getGroupBundle() === 'community' && $m->hasPermission('manage members')) {
            $cm_community_ids[] = $m->getGroupId();
          }
        }
        $target_community_ids = [];
        foreach (Og::getMemberships($user) as $m) {
          if ($m->getGroupBundle() === 'community') {
            $target_community_ids[] = $m->getGroupId();
          }
        }
        if (empty(array_intersect($cm_community_ids, $target_community_ids))) {
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
