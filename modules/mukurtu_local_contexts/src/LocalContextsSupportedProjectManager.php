<?php

namespace Drupal\mukurtu_local_contexts;

use PDO;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\og\Og;
use Drupal\Core\Session\AccountInterface;

class LocalContextsSupportedProjectManager {
  protected $db;

  public function __construct() {
    $this->db = \Drupal::database();
  }

  public function addSiteProject($project_id) {
    if (!$this->isSiteSupportedProject($project_id)) {
      $fields = [
        'project_id' => $project_id,
        'type' => 'site',
        'group_id' => 0,
      ];
      $query = $this->db->insert('mukurtu_local_contexts_supported_projects')->fields($fields);
      $query->execute();
    }
  }

  public function isSiteSupportedProject($project_id) {
    $query = $this->db->select('mukurtu_local_contexts_supported_projects', 'projects')
      ->condition('projects.project_id', $project_id)
      ->condition('type', 'site')
      ->condition('group_id', 0)
      ->fields('projects', ['project_id']);
    $result = $query->execute();
    $projects = $result->fetchAll();
    return empty($projects) ? FALSE : TRUE;
  }

  public function getAllProjects() {
    $query = $this->db->select('mukurtu_local_contexts_supported_projects', 'sp');
    $query->join('mukurtu_local_contexts_projects', 'p', 'sp.project_id = p.id');
    $query->fields('p', ['id', 'provider_id', 'title', 'privacy', 'updated']);

    $result = $query->execute();
    $projects = $result->fetchAllAssoc('id', PDO::FETCH_ASSOC);
    return $projects;
  }

  public function getSiteSupportedProjects() {
    $query = $this->db->select('mukurtu_local_contexts_supported_projects', 'sp');
    $query->join('mukurtu_local_contexts_projects', 'p', 'sp.project_id = p.id');
    $query
      ->condition('sp.type', 'site')
      ->condition('sp.group_id', 0)
      ->fields('p', ['id', 'provider_id', 'title', 'privacy', 'updated']);

    $result = $query->execute();
    $projects = $result->fetchAllAssoc('id', PDO::FETCH_ASSOC);
    return $projects;
  }

  public function getGroupSupportedProjects(ContentEntityInterface $group) {
    $query = $this->db->select('mukurtu_local_contexts_supported_projects', 'sp');
    $query->join('mukurtu_local_contexts_projects', 'p', 'sp.project_id = p.id');
    $query
      ->condition('sp.type', $group->getEntityTypeId())
      ->condition('sp.group_id', $group->id())
      ->fields('p', ['id', 'provider_id', 'title', 'privacy', 'updated']);

    $result = $query->execute();
    $projects = $result->fetchAllAssoc('id', PDO::FETCH_ASSOC);
    return $projects;
  }

  public function isGroupSupportedProject(ContentEntityInterface $group, $project_id) {
    $query = $this->db->select('mukurtu_local_contexts_supported_projects', 'projects')
      ->condition('projects.project_id', $project_id)
      ->condition('projects.type', $group->getEntityTypeId())
      ->condition('projects.group_id', $group->id())
      ->fields('projects', ['project_id']);
    $result = $query->execute();
    $projects = $result->fetchAll();
    return empty($projects) ? FALSE : TRUE;
  }

  public function addGroupProject(ContentEntityInterface $group, $project_id) {
    if (!$this->isGroupSupportedProject($group, $project_id)) {
      $fields = [
        'project_id' => $project_id,
        'type' => $group->getEntityTypeId(),
        'group_id' => $group->id(),
      ];
      $query = $this->db->insert('mukurtu_local_contexts_supported_projects')->fields($fields);
      $query->execute();
    }
  }

  public function removeSiteProject($project_id) {
    $query = $this->db->delete('mukurtu_local_contexts_supported_projects')
      ->condition('project_id', $project_id)
      ->condition('type', 'site')
      ->condition('group_id', 0);
    return $query->execute();
  }
  public function removeGroupProject(ContentEntityInterface $group, $project_id) {
    $query = $this->db->delete('mukurtu_local_contexts_supported_projects')
      ->condition('project_id', $project_id)
      ->condition('type', $group->getEntityTypeId())
      ->condition('group_id', $group->id());
    return $query->execute();
  }

  public function getUserProjects(AccountInterface $account) {
    $projects = $this->getSiteSupportedProjects();

    $memberships = Og::getMemberships($account);
    foreach ($memberships as $membership) {
      $projects += $this->getGroupSupportedProjects($membership->getGroup());
    }

    return $projects;
  }

}
