<?php

namespace Drupal\mukurtu_protocol\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Session\AccountInterface;
use Drupal\og\Og;
use Drupal\user\UserInterface;

/**
 * Controller for the user memberships page.
 */
class UserMembershipsController extends ControllerBase {

  public function content() {
    $user = $this->entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
    return $this->buildPage($user);
  }

  public function contentForUser(UserInterface $user) {
    return $this->buildPage($user);
  }

  public function access(AccountInterface $account, UserInterface $user) {
    if ($account->id() == $user->id()) {
      return AccessResult::allowedIf($account->isAuthenticated())->cachePerUser();
    }
    return AccessResult::allowedIfHasPermission($account, 'administer users')->cachePerPermissions();
  }

  public function title(UserInterface $user) {
    $display_name = $user->get('field_display_name')->value ?: $user->getAccountName();
    return $this->t("@name's Memberships", ['@name' => $display_name]);
  }

  protected function buildPage(UserInterface $user) {
    $all_memberships = Og::getMemberships($user);
    $bubbleable_metadata = new BubbleableMetadata();

    $community_memberships = array_filter($all_memberships, fn($m) => $m->getGroupBundle() === 'community');
    $protocol_memberships_by_id = [];
    foreach (array_filter($all_memberships, fn($m) => $m->getGroupBundle() === 'protocol') as $pm) {
      $group = $pm->getGroup();
      if ($group) {
        $protocol_memberships_by_id[$group->id()] = $pm;
      }
    }

    $assigned_protocol_ids = [];
    $communities = [];

    foreach ($community_memberships as $cm) {
      $community = $cm->getGroup();
      if (!$community) {
        continue;
      }

      $community_roles = array_values(array_map(
        fn($r) => $r->label(),
        array_filter($cm->getRoles(), fn($r) => !in_array($r->getName(), ['member', 'non-member']))
      ));

      $protocols_in_community = [];
      foreach (array_keys($protocol_memberships_by_id) as $protocol_id) {
        $pm = $protocol_memberships_by_id[$protocol_id];
        $protocol = $pm->getGroup();
        if (!$protocol) {
          continue;
        }
        $protocol_community_ids = array_map(fn($c) => $c->id(), $protocol->getCommunities());
        if (in_array($community->id(), $protocol_community_ids)) {
          $protocol_roles = array_values(array_map(
            fn($r) => $r->label(),
            array_filter($pm->getRoles(), fn($r) => !in_array($r->getName(), ['member', 'non-member']))
          ));
          $protocols_in_community[] = [
            'name' => $protocol->label(),
            'url' => $protocol->toUrl()->toString($bubbleable_metadata),
            'roles' => $protocol_roles,
          ];
          $assigned_protocol_ids[] = $protocol_id;
        }
      }

      $communities[] = [
        'name' => $community->label(),
        'url' => $community->toUrl()->toString($bubbleable_metadata),
        'roles' => $community_roles,
        'protocols' => $protocols_in_community,
      ];
    }

    $orphan_protocols = [];
    foreach ($protocol_memberships_by_id as $protocol_id => $pm) {
      if (in_array($protocol_id, $assigned_protocol_ids)) {
        continue;
      }
      $protocol = $pm->getGroup();
      if (!$protocol) {
        continue;
      }
      $protocol_roles = array_values(array_map(
        fn($r) => $r->label(),
        array_filter($pm->getRoles(), fn($r) => !in_array($r->getName(), ['member', 'non-member']))
      ));
      $orphan_protocols[] = [
        'name' => $protocol->label(),
        'url' => $protocol->toUrl()->toString($bubbleable_metadata),
        'roles' => $protocol_roles,
      ];
    }

    $build = [
      '#theme' => 'mukurtu_user_memberships',
      '#communities' => $communities,
      '#orphan_protocols' => $orphan_protocols,
      '#user' => $user,
    ];
    $bubbleable_metadata->applyTo($build);
    return $build;
  }

}
