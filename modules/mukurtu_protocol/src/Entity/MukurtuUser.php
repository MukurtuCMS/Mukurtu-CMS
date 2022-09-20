<?php

namespace Drupal\mukurtu_protocol\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\user\Entity\User;
use Drupal\mukurtu_protocol\Entity\MukurtuUserInterface;
use Drupal\og\Og;

class MukurtuUser extends User implements MukurtuUserInterface {

  /**
   * {@inheritDoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    // Use the account name as the display name by default.
    if ($this->hasField('field_display_name') && empty($this->get('field_display_name')->getValue())) {
      $this->set('field_display_name', $this->getAccountName());
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getCommunities() {
    $memberships = array_filter(Og::getMemberships($this), fn ($m) => $m->getGroupBundle() === 'community');
    if (!empty($memberships)) {
      return array_map(fn($m) => $m->getGroup(), $memberships);
    }
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function getProtocols() {
    $memberships = array_filter(Og::getMemberships($this), fn ($m) => $m->getGroupBundle() === 'protocol');
    if (!empty($memberships)) {
      return array_map(fn ($m) => $m->getGroup(), $memberships);
    }
    return [];
  }

}
