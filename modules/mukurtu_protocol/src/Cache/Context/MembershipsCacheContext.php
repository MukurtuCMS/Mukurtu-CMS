<?php

declare(strict_types=1);

namespace Drupal\mukurtu_protocol\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Cache\Context\UserCacheContextBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\PrivateKey;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;

/**
 * Defines the MembershipsCacheContext service.
 *
 * Cache context ID: 'mukurtu_memberships'.
 *
 * Varies by all of the user's community and protocol memberships, in every
 * state, with their roles, and by whether the user is user 1. The community,
 * protocol, and media listing query alters in mukurtu_protocol.module filter
 * on exactly these. OG's og_role context isn't enough, because it ignores
 * blocked memberships, and a user blocked in a community loses access to its
 * protocols.
 */
class MembershipsCacheContext extends UserCacheContextBase implements CacheContextInterface {

  /**
   * Context values already computed in this request, keyed by user ID.
   *
   * @var string[]
   */
  protected array $hashes = [];

  /**
   * Constructs a MembershipsCacheContext.
   */
  public function __construct(
    AccountInterface $user,
    protected Connection $database,
    protected PrivateKey $privateKey,
  ) {
    parent::__construct($user);
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Mukurtu community and protocol memberships');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    $uid = (int) $this->user->id();
    if (isset($this->hashes[$uid])) {
      return $this->hashes[$uid];
    }

    $query = $this->database->select('og_membership', 'm');
    $query->leftJoin('og_membership__roles', 'r', 'm.id = r.entity_id');
    $query->fields('m', ['entity_type', 'entity_id', 'state']);
    $query->fields('r', ['roles_target_id']);
    $query->condition('m.uid', $uid);
    $query->condition('m.entity_type', ['community', 'protocol'], 'IN');

    $memberships = [];
    foreach ($query->execute() as $row) {
      $key = "{$row->entity_type}:{$row->entity_id}:{$row->state}";
      $memberships[$key] ??= [];
      if ($row->roles_target_id !== NULL) {
        $memberships[$key][] = $row->roles_target_id;
      }
    }
    ksort($memberships);
    foreach ($memberships as &$roles) {
      sort($roles);
    }

    $prefix = $uid === 1 ? 'super-user' : 'user';
    $this->hashes[$uid] = $prefix . ':' . ($memberships ? $this->hash(serialize($memberships)) : 'none');
    return $this->hashes[$uid];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

  /**
   * Hashes a string with the site's private key and hash salt.
   */
  protected function hash(string $identifier): string {
    return hash('sha256', $this->privateKey->get() . Settings::getHashSalt() . $identifier);
  }

}
