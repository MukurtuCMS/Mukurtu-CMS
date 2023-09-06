<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;

/**
 * Provide OG memberships as a source for migrate.
 *
 * @MigrateSource(
 *   id = "mukurtu_v3_og_memberships",
 *   source_module = "og",
 *   source_provider = "og"
 * )
 */
class MukurtuOgMemberships extends FieldableEntity {

  /**
   * {@inheritdoc}
   */
   public function query() {
    $group_bundle = $this->configuration['group_bundle'];
    $fields = array_keys($this->fields());
    $query = $this->select('og_membership', 'ogm')->fields('ogm', $fields);
    $query->innerJoin('node', 'n', 'n.nid = ogm.gid');
    $query->condition('ogm.group_type', 'node', '=')
      ->condition('n.type', $group_bundle, '=')
      ->condition('ogm.entity_type', 'user', '=');
    return $query;
  }

  protected function getRoles($uid, $gid) {
    $group_bundle = $this->configuration['group_bundle'];
    $query = $this->select('og_users_roles', 'u');
    $query->innerJoin('og_role', 'r', 'r.rid = u.rid');
    $query->condition('r.group_bundle', $group_bundle, '=')
      ->condition('r.group_type', 'node', '=')
      ->condition('u.uid', $uid)
      ->condition('u.gid', $gid)
      ->fields('r', ['name']);

    $result = $query->execute()->fetchAll();
    return array_column($result, 'name');
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $after = parent::prepareRow($row);
    $roles = $this->getRoles($row->get('etid'), $row->get('gid'));
    $row->setSourceProperty('roles', $roles);
    return $after;
  }

  /**
   * {@inheritdoc}
   */
   public function fields() {
    $fields = array(
      'id' => $this->t('The group membership\'s unique ID'),
      'type' => $this->t('Reference to a group membership type'),
      'etid' => $this->t('The entity ID'),
      'entity_type' => $this->t('The entity type (e.g. node, comment, etc)'),
      'gid' => $this->t('The group\'s unique ID'),
      'group_type' => $this->t('The group\'s entity type (e.g. node, comment, etc)'),
      'state' => $this->t('The state of the group content'),
      'field_name' => $this->t('The name of the field holding the group ID, the OG membership is associated with'),
      'language' => $this->t('The language'),
      'created' => $this->t('Created timestamp'),
    );
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['id']['type'] = 'integer';
    $ids['id']['alias'] = 'ogm';
    return $ids;
  }

}
