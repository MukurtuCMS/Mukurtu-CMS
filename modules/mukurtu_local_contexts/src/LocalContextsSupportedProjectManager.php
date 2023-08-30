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

  public function getAllLabels() {
    $query = $this->db->select('mukurtu_local_contexts_labels', 'labels');
    $query->join('mukurtu_local_contexts_projects', 'p', 'labels.project_id = p.id');
    $query->fields('labels', ['id', 'name', 'type', 'display']);
    $query->fields('p', ['id', 'provider_id', 'title', 'privacy', 'updated']);

    $result = $query->execute();
    $labels = $result->fetchAllAssoc('id', PDO::FETCH_ASSOC);
    return $labels;
  }

  public function getAllNotices() {
    $query = $this->db->select('mukurtu_local_contexts_notices', 'notices');
    $query->join('mukurtu_local_contexts_projects', 'p', 'notices.project_id = p.id');
    $query->fields('notices', ['type', 'name', 'default_text', 'display']);
    $query->fields('p', ['id', 'provider_id', 'title', 'privacy', 'updated']);

    $result = $query->execute();
    $notices = [];
    while ($notice = $result->fetchAssoc()) {
      // We have to form the notices like this because they have compound ids.
      $noticeId = $notice['project_id'] . ':' . $notice['type'];
      $notices[$noticeId] = [
        'p_id' => $notice['project_id'],
        'title' => $notice['title'],
        'type' => $notice['type'],
        'name' => $notice['name'],
        'svg_url' => $notice['svg_url'],
        'text' => $notice['default_text'],
        'display' => $notice['display'],
      ];
    }
    return $notices;
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

  public function getSiteLabels() {
    $query = $this->db->select('mukurtu_local_contexts_labels', 'labels');
    $query->join('mukurtu_local_contexts_projects', 'p', 'labels.project_id = p.id');
    $query->join('mukurtu_local_contexts_supported_projects', 'sp', 'labels.project_id = sp.project_id');
    $query
      ->condition('sp.type', 'site')
      ->condition('sp.group_id', 0);
    $query->fields('labels', ['id', 'name', 'type', 'display']);
    $query->fields('p', ['id', 'provider_id', 'title', 'privacy', 'updated']);

    $result = $query->execute();
    $labels = $result->fetchAllAssoc('id', PDO::FETCH_ASSOC);

    return $labels;
  }

  public function getSiteNotices() {
    $query = $this->db->select('mukurtu_local_contexts_notices', 'notices');
    $query->join('mukurtu_local_contexts_projects', 'p', 'notices.project_id = p.id');
    $query->join('mukurtu_local_contexts_supported_projects', 'sp', 'notices.project_id = sp.project_id');
    $query
      ->condition('sp.type', 'site')
      ->condition('sp.group_id', 0);
    $query->fields('notices', ['type', 'name', 'default_text', 'display']);
    $query->fields('p', ['id', 'provider_id', 'title', 'privacy', 'updated']);

    $result = $query->execute();
    $notices = [];
    while ($notice = $result->fetchAssoc()) {
      // We have to form the notices like this because they have compound ids.
      $noticeId = $notice['project_id'] . ':' . $notice['type'];
      $notices[$noticeId] = [
        'p_id' => $notice['project_id'],
        'title' => $notice['title'],
        'type' => $notice['type'],
        'name' => $notice['name'],
        'svg_url' => $notice['svg_url'],
        'text' => $notice['default_text'],
        'display' => $notice['display'],
      ];
    }
    return $notices;
  }

  public function getUserLabels(AccountInterface $account) {
    $projects = $this->getUserProjects($account);

    if (empty($projects)) {
      return [];
    }

    $project_ids = array_keys($projects);
    $query = $this->db->select('mukurtu_local_contexts_labels', 'labels');
    $query->condition('project_id', $project_ids, 'IN');
    $query->join('mukurtu_local_contexts_projects', 'p', 'labels.project_id = p.id');
    $query->fields('labels', ['id', 'name', 'type', 'display']);
    $query->fields('p', ['id', 'provider_id', 'title', 'privacy', 'updated']);

    $result = $query->execute();

    $labels = $result->fetchAllAssoc('id', PDO::FETCH_ASSOC);
    return $labels;
  }

  public function getUserNotices(AccountInterface $account) {
    $projects = $this->getUserProjects($account);

    if (empty($projects)) {
      return [];
    }

    $project_ids = array_keys($projects);
    $query = $this->db->select('mukurtu_local_contexts_notices', 'notices');
    $query->condition('project_id', $project_ids, 'IN');
    $query->join('mukurtu_local_contexts_projects', 'p', 'notices.project_id = p.id');
    $query->fields('notices', ['project_id', 'type', 'name', 'default_text', 'display', 'svg_url']);
    $query->fields('p', ['provider_id', 'title', 'privacy', 'updated']);

    $result = $query->execute();
    $notices = [];
    while ($notice = $result->fetchAssoc()) {
      // We have to form the notices like this because they have compound ids.
      $noticeId = $notice['project_id'] . ':' . $notice['type'];
      $notices[$noticeId] = [
        'p_id' => $notice['project_id'],
        'title' => $notice['title'],
        'type' => $notice['type'],
        'name' => $notice['name'],
        'svg_url' => $notice['svg_url'],
        'text' => $notice['default_text'],
        'display' => $notice['display'],
      ];
    }

    return $notices;
  }

}
