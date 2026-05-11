<?php

namespace Drupal\mukurtu_core\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\og\Og;
use Drupal\user\Entity\User;

class MukurtuUserController extends ControllerBase {
  use StringTranslationTrait;

  /**
   * Roles that community managers cannot perform operations on.
   */
  const PROTECTED_ROLES = ['administrator', 'mukurtu_manager'];

  const PEOPLE_VIEW_ID = 'mukurtu_people';
  const PEOPLE_DISPLAY_ID = 'page_1';

  /**
   * Check if user can administer users.
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access() {
    $account = $this->currentUser();
    if ($account->hasPermission('administer users')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // Also allow community managers (have 'manage members' in any community).
    $user = User::load($account->id());
    foreach (Og::getMemberships($user) as $membership) {
      if ($membership->getGroupBundle() === 'community' && $membership->hasPermission('manage members')) {
        return AccessResult::allowed()->cachePerUser();
      }
    }

    return AccessResult::forbidden()->cachePerUser();
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
      $add_url = Url::fromRoute('mukurtu_protocol.add_user_to_community', ['user' => $uid]);
      $link = Link::fromTextAndUrl($this->t('Add them to a community'), $add_url)->toString();
      $name = $user->getDisplayName();
      \Drupal::messenger()->addStatus(Markup::create(
        $this->t('@name has been approved.', ['@name' => $name]) . ' ' . $link . '.'
      ));
    }

    $request = \Drupal::request();
    $referer = $request->headers->get('referer');
    $fallback = Url::fromRoute('view.' . self::PEOPLE_VIEW_ID . '.' . self::PEOPLE_DISPLAY_ID)->toString();
    $redirect = ($referer && str_starts_with($referer, $request->getSchemeAndHttpHost()))
      ? $referer
      : $fallback;
    $response->addCommand(new RedirectCommand($redirect));
    return $response;
  }

}

