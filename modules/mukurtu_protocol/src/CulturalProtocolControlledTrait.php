<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_core\BaseFieldDefinition;
use Drupal\og\Og;
use Drupal\mukurtu_protocol\CulturalProtocols;
use Drupal\mukurtu_protocol\Entity\ProtocolInterface;
use Drupal\mukurtu_protocol\Plugin\Field\FieldType\CulturalProtocolItem;

trait CulturalProtocolControlledTrait {
  public static function getProtocolFieldDefinitions(): array {
    $definitions = [];

    $definitions['field_cultural_protocols'] = BaseFieldDefinition::create('cultural_protocol')
      ->setLabel('Cultural Protocols')
      ->setCardinality(1)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $definitions;
  }

  /**
   * Get the cultural protocol sharing setting.
   *
   * @return string
   *  The sharing setting, 'any' or 'all'.
   */
  public function getSharingSetting(): string {
    return $this->get('field_cultural_protocols')->sharing_setting ?? 'all';
  }

  /**
   * Get the cultural protocol sharing setting.
   *
   * @param string $option
   *  The sharing setting, 'any' or 'all'.
   *
   * @return \Drupal\mukurtu_protocol\CulturalProtocolControlledInterface
   *  The protocol controlled entity.
   */
  public function setSharingSetting($option): CulturalProtocolControlledInterface {
    $value['protocols'] = $this->get('field_cultural_protocols')->protocols ?? '';
    $value['sharing_setting'] = $option;
    return $this->set('field_cultural_protocols', $value);
  }

  /**
   * Get the protocol IDs.
   */
  public function getProtocols() {
    return CulturalProtocolItem::unformatProtocols($this->get('field_cultural_protocols')->protocols);
  }

  /**
   * Get the protocol entities.
   */
  public function getProtocolEntities() {
    $ids = $this->getProtocols();
    return empty($ids) ? [] : $this->entityTypeManager()->getStorage('protocol')->loadMultiple($ids);
  }

  /**
   * Set the protocols.
   */
  public function setProtocols($protocols) {
    // Handle both array of IDs and array of protocol entities.
    $protocol_ids = [];
    if (!empty($protocols)) {
      $protocol_ids = array_map(fn($p) => $p instanceof ProtocolInterface ? $p->id() : $p, $protocols);
    }

    $value['protocols'] = CulturalProtocolItem::formatProtocols($protocol_ids);
    $value['sharing_setting'] = $this->get('field_cultural_protocols')->sharing_setting ?? 'all';
    return $this->set('field_cultural_protocols', $value);
  }

  /**
   * Get the affiliated community entities, based on applied protocols.
   */
  public function getCommunities() {
    $communities = [];
    $protocols = $this->getProtocolEntities();
    /** @var \Drupal\mukurtu_protocol\Entity\ProtocolInterface $protocol */
    foreach ($protocols as $protocol) {
      $pCommunities = $protocol->getCommunities();
      foreach ($pCommunities as $pCommunity) {
        $communities[$pCommunity->id()] = $pCommunity;
      }
    }
    return $communities;
  }

  public function getMemberProtocols(?AccountInterface $user = NULL): array {
    $memberships = [];

    // Load the current user if a user wasn't provided.
    if (!$user) {
      $current_user = \Drupal::currentUser();
      $user = $this->entityTypeManager()->getStorage('user')->load($current_user->id());
    }

    $protocols = $this->getProtocolEntities();
    if (empty($protocols)) {
      return $memberships;
    }

    foreach ($protocols as $protocol) {
      /** @var \Drupal\mukurtu_protocol\Entity\ProtocolInterface $protocol */
      if ($protocol->isOpen()) {
        // Everybody is a "member" of an open protocol.
        $memberships[$protocol->id()] = $protocol;
        continue;
      }

      // Strict protocol, need to lookup actual membership.
      if (Og::getMembership($protocol, $user)) {
        $memberships[$protocol->id()] = $protocol;
      }
    }

    return $memberships;
  }

  public function isProtocolSetMember(AccountInterface $user): bool {
    $all = $this->getSharingSetting() == 'all';
    $allProtocols = $this->getProtocols();
    $memberProtocols = $this->getMemberProtocols($user);

    if ($all) {
      return (count($allProtocols) == count($memberProtocols));
    }
    return !empty($memberProtocols);
  }

  protected function getNodeAccessGrants() {
    $grants = [];
    $protocols = $this->getProtocols();

    // Deny grant for missing/broken protocols.
    $grants[] = [
      'realm' => 'protocols',
      'gid' => 0,
      'grant_view' => 0,
      'grant_update' => 0,
      'grant_delete' => 0,
      'priority' => 0,
    ];

    if ($this->getSharingSetting() == 'all') {
      // User needs the grant that represents the set of all the
      // node's protocols.
      $gid = CulturalProtocols::getProtocolSetId($protocols);

      if ($gid) {
        $grants[] = [
          'realm' => 'protocols',
          'gid' => $gid,
          'grant_view' => 1,
          'grant_update' => 0,
          'grant_delete' => 0,
          'priority' => 0,
        ];
      }
    } else {
      // In this case, membership in any of involved protocols is
      // sufficient.
      foreach ($protocols as $protocol) {
        $gid = CulturalProtocols::getProtocolSetId([$protocol]);
        if ($gid) {
          $grants[] = [
            'realm' => 'protocols',
            'gid' => $gid,
            'grant_view' => 1,
            'grant_update' => 0,
            'grant_delete' => 0,
            'priority' => 0,
          ];
        }
      }
    }

    return $grants;
  }

  protected function getNonNodeAccessGrants() {
    $grants = [];
    $protocols = $this->getProtocols();

    if ($this->getSharingSetting() == 'all') {
      // User needs the grant that represents the set of all the
      // node's protocols.
      $gid = CulturalProtocols::getProtocolSetId($protocols);
      if ($gid) {
        $grants[] = $gid;
      }
    } else {
      // In this case, membership in any of involved protocols is
      // sufficient.
      foreach ($protocols as $protocol) {
        $gid = CulturalProtocols::getProtocolSetId([$protocol]);
        if ($gid) {
          $grants[] = $gid;
        }
      }
    }
    return $grants;
  }

  /**
   * @inheritDoc
   */
  public function getAccessGrants(): array {
    if ($this->getEntityTypeId() == 'node') {
      return $this->getNodeAccessGrants();
    }
    return $this->getNonNodeAccessGrants();
  }

  /**
   * @inheritDoc
   */
  public function removeAccessGrants(): void {
    $connection = \Drupal::database();
    // No matter what, we're clearing out the old grants.
    $connection->delete('mukurtu_protocol_access')
      ->condition('id', $this->id())
      ->condition('langcode', $this->langcode->value)
      ->condition('entity_type_id', $this->getEntityTypeId())
      ->execute();
  }

  /**
   * @inheritDoc
   */
  public function buildAccessGrants(): void {
    // We only care about non-node entities, nodes have the
    // node_access grant system.
    if ($this->getEntityTypeId() != 'node') {
      // No matter what, we're clearing out the old grants.
      $this->removeAccessGrants();

      // Get the current grants.
      $grants = $this->getAccessGrants();
      if (!empty($grants)) {
        $connection = \Drupal::database();

        // We've got new grants, add them to the table.
        foreach ($grants as $grant) {
          $connection->insert('mukurtu_protocol_access')
            ->fields([
              'id' => $this->id(),
              'langcode' => $this->langcode->value,
              'entity_type_id' => $this->getEntityTypeId(),
              'protocol_set_id' => $grant,
              'grant_view' => 1,
            ])
            ->execute();
        }
      }
    }
  }

}
