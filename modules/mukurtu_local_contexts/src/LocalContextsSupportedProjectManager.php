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

  /**
   * Add project ID as a site project.
   *
   * @param string $project_id
   *   The project ID to add.
   *
   * @return void
   */
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

  /**
   * Check if a given project ID is a site project.
   *
   * @param string $project_id
   *   The project ID.
   *
   * @return bool
   *   True if the project is a site project.
   */
  public function isSiteSupportedProject($project_id): bool {
    $query = $this->db->select('mukurtu_local_contexts_supported_projects', 'projects')
      ->condition('projects.project_id', $project_id)
      ->condition('type', 'site')
      ->condition('group_id', 0)
      ->fields('projects', ['project_id']);
    $result = $query->execute();
    $projects = $result->fetchAll();
    return empty($projects) ? FALSE : TRUE;
  }

  /**
   * Get all projects that have been added, regardless of scope.
   *
   * Note that this returns the type and group ID of each project as well, and
   * sorts items by type, group ID, and then title.
   *
   * @return array
   *   The project information, keyed by project ID.
   */
  public function getAllProjects(): array {
    $query = $this->db->select('mukurtu_local_contexts_supported_projects', 'sp');
    $query->join('mukurtu_local_contexts_projects', 'p', 'sp.project_id = p.id');
    $query->fields('sp', ['type', 'group_id']);
    $query->fields('p', ['id', 'provider_id', 'title', 'privacy', 'updated']);
    $query->orderBy('sp.type', 'DESC');
    $query->orderBy('sp.group_id');
    $query->orderBy('p.title');

    $result = $query->execute();
    return $result->fetchAllAssoc('id', PDO::FETCH_ASSOC);
  }

  /**
   * Get all labels that have been added, regardless of scope.
   *
   * @return array
   *   The label information, keyed by label ID.
   */
  public function getAllLabels(): array {
    $query = $this->db->select('mukurtu_local_contexts_labels', 'labels');
    $query->join('mukurtu_local_contexts_projects', 'p', 'labels.project_id = p.id');
    $query->fields('labels', ['id', 'name', 'type', 'display']);
    $query->fields('p', ['provider_id', 'title', 'privacy', 'updated']);
    $query->addField('p', 'id', 'project_id');

    $result = $query->execute();
    $labels = $result->fetchAllAssoc('id', PDO::FETCH_ASSOC);
    return $labels;
  }

  /**
   * Get all notices that have been added, regardless of scope.
   *
   * @return array
   *   The notice information, keyed by notice ID.
   */
  public function getAllNotices(): array {
    $query = $this->db->select('mukurtu_local_contexts_notices', 'notices');
    $query->join('mukurtu_local_contexts_projects', 'p', 'notices.project_id = p.id');
    $query->fields('notices', ['type', 'name', 'img_url', 'svg_url', 'default_text', 'display']);
    $query->fields('p', ['provider_id', 'title', 'privacy', 'updated']);
    $query->addField('p', 'id', 'project_id');

    $result = $query->execute();
    $notices = [];
    while ($notice = $result->fetchAssoc()) {
      // We have to form the notices like this because they have compound ids.
      $noticeId = $notice['project_id'] . ':' . $notice['type'];
      $notices[$noticeId] = [
        'project_id' => $notice['project_id'],
        'title' => $notice['title'],
        'type' => $notice['type'],
        'name' => $notice['name'],
        'img_url' => $notice['img_url'],
        'svg_url' => $notice['svg_url'],
        'text' => $notice['default_text'],
        'display' => $notice['display'],
      ];
    }
    return $notices;
  }

  /**
   * Get all site projects that have been added.
   *
   * @return array
   *   The project information, keyed by project ID.
   */
  public function getSiteSupportedProjects($exclude_legacy = FALSE): array {
    $query = $this->db->select('mukurtu_local_contexts_supported_projects', 'sp');
    $query->join('mukurtu_local_contexts_projects', 'p', 'sp.project_id = p.id');
    $query
      ->condition('sp.type', 'site')
      ->condition('sp.group_id', 0)
      ->fields('p', ['id', 'provider_id', 'title', 'privacy', 'updated']);
    $query->orderBy('p.title');

    $result = $query->execute();
    $projects = $result->fetchAllAssoc('id', PDO::FETCH_ASSOC);

    if ($exclude_legacy) {
      foreach (['default_tk', 'sitewide_tk'] as $legacy_id) {
        if (isset($projects[$legacy_id])) {
          unset($projects[$legacy_id]);
        }
      }
    }
    return $projects;
  }

  /**
   * Get all group projects that have been added.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $group
   *   The OG group (community or protocol).
   *
   * @return array
   *   The project information, keyed by project ID.
   */
  public function getGroupSupportedProjects(ContentEntityInterface $group): array {
    $query = $this->db->select('mukurtu_local_contexts_supported_projects', 'sp');
    $query->join('mukurtu_local_contexts_projects', 'p', 'sp.project_id = p.id');
    $query
      ->condition('sp.type', $group->getEntityTypeId())
      ->condition('sp.group_id', $group->id())
      ->fields('p', ['id', 'provider_id', 'title', 'privacy', 'updated']);
    $query->orderBy('p.title');

    $result = $query->execute();
    return $result->fetchAllAssoc('id', PDO::FETCH_ASSOC);
  }

  /**
   * Check if a given project ID is a group project.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $group
   *   The OG group (community or protocol).
   * @param string $project_id
   *   The project ID.
   *
   * @return bool
   *   True if the project is a group project.
   */
  public function isGroupSupportedProject(ContentEntityInterface $group, string $project_id): bool {
    $query = $this->db->select('mukurtu_local_contexts_supported_projects', 'projects')
      ->condition('projects.project_id', $project_id)
      ->condition('projects.type', $group->getEntityTypeId())
      ->condition('projects.group_id', $group->id())
      ->fields('projects', ['project_id']);
    $result = $query->execute();
    $projects = $result->fetchAll();
    return !empty($projects);
  }

  /**
   * Add a given project ID as a group project.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $group
   *   The OG group (community or protocol).
   * @param string $project_id
   *   The project ID to add.
   *
   * @return void
   */
  public function addGroupProject(ContentEntityInterface $group, string $project_id) {
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

  /**
   * Remove a project ID as a site project.
   *
   * @param string $project_id
   *   The project ID to remove.
   *
   * @return void
   */
  public function removeSiteProject($project_id) {
    $query = $this->db->delete('mukurtu_local_contexts_supported_projects')
      ->condition('project_id', $project_id)
      ->condition('type', 'site')
      ->condition('group_id', 0);
    $query->execute();
    // If the group is no longer in use, remove it.
    $this->removeProject($project_id);
  }

  /**
   * Remove a given project ID as a group project.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $group
   *   The OG group (community or protocol).
   * @param string $project_id
   *   The project ID to remove.
   *
   * @return void
   */
  public function removeGroupProject(ContentEntityInterface $group, string $project_id) {
    $query = $this->db->delete('mukurtu_local_contexts_supported_projects')
      ->condition('project_id', $project_id)
      ->condition('type', $group->getEntityTypeId())
      ->condition('group_id', $group->id());
    $query->execute();
    // If the group is no longer in use, remove it.
    $this->removeProject($project_id);
  }

  /**
   * Completely remove a project from the system.
   *
   * @param string $project_id
   *   The project ID to remove.
   * @param bool $force_delete
   *   Whether to delete the project even if it is in use.
   */
  public function removeProject(string $project_id, bool $force_delete = FALSE) {
    // Ensure the project is not in use before deleting.
    if (!$force_delete) {
      $query = $this->db->select('mukurtu_local_contexts_supported_projects', 'projects')
        ->condition('projects.project_id', $project_id)
        ->fields('projects', ['project_id']);
      $result = $query->execute();
      if ($result->fetchAll()) {
        return;
      }
    }

    // Delete labels provided by the project.
    $labels = $this->getAllLabels();
    foreach ($labels as $label_id => $label) {
      if ($label['project_id'] == $project_id) {
        $query = $this->db->delete('mukurtu_local_contexts_labels')
          ->condition('id', $label_id);
        $query->execute();
        $query = $this->db->delete('mukurtu_local_contexts_label_translations')
          ->condition('label_id', $label_id);
        $query->execute();
      }
    }

    // Delete notices provided by the project.
    $notices = $this->getAllNotices();
    foreach ($notices as $notice_id => $notice) {
      if ($notice['project_id'] == $project_id) {
        $query = $this->db->delete('mukurtu_local_contexts_notices')
          ->condition('id', $notice_id);
        $query->execute();
        $query = $this->db->delete('mukurtu_local_contexts_notice_translations')
          ->condition('label_id', $notice_id);
        $query->execute();
      }
    }

    // Delete any project usage tracking.
    $query = $this->db->delete('mukurtu_local_contexts_supported_projects')
      ->condition('project_id', $project_id);
    $query->execute();

    // Delete the project itself.
    $query = $this->db->delete('mukurtu_local_contexts_projects')
      ->condition('id', $project_id);
    $query->execute();
  }

  /**
   * Get all projects a user can use.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return array
   *   The project information, keyed by project ID.
   */
  public function getUserProjects(AccountInterface $account) {
    $projects = $this->getSiteSupportedProjects();

    $memberships = Og::getMemberships($account);
    foreach ($memberships as $membership) {
      $group = $membership->getGroup();
      if ($group) {
        $projects += $this->getGroupSupportedProjects($group);
      }
    }

    return $projects;
  }

  /**
   * Get all site labels.
   *
   * @return array
   *   The label information, keyed by label ID.
   */
  public function getSiteLabels(): array {
    $query = $this->db->select('mukurtu_local_contexts_labels', 'labels');
    $query->join('mukurtu_local_contexts_projects', 'p', 'labels.project_id = p.id');
    $query->join('mukurtu_local_contexts_supported_projects', 'sp', 'labels.project_id = sp.project_id');
    $query
      ->condition('sp.type', 'site')
      ->condition('sp.group_id', 0);
    $query->fields('labels', ['id', 'name', 'type', 'display']);
    $query->fields('p', ['provider_id', 'title', 'privacy', 'updated']);
    $query->addField('p', 'id', 'project_id');

    $result = $query->execute();
    return $result->fetchAllAssoc('id', PDO::FETCH_ASSOC);
  }

  /**
   * Get all site notices.
   *
   * @return array
   *   The notice information, keyed by notice ID.
   */
  public function getSiteNotices(): array {
    $query = $this->db->select('mukurtu_local_contexts_notices', 'notices');
    $query->join('mukurtu_local_contexts_projects', 'p', 'notices.project_id = p.id');
    $query->join('mukurtu_local_contexts_supported_projects', 'sp', 'notices.project_id = sp.project_id');
    $query
      ->condition('sp.type', 'site')
      ->condition('sp.group_id', 0);
    $query->fields('notices', ['type', 'name', 'default_text', 'display']);
    $query->fields('p', ['provider_id', 'title', 'privacy', 'updated']);
    $query->addField('p', 'id', 'project_id');

    $result = $query->execute();
    $notices = [];
    while ($notice = $result->fetchAssoc()) {
      // We have to form the notices like this because they have compound ids.
      $noticeId = $notice['project_id'] . ':' . $notice['type'];
      $notices[$noticeId] = [
        'project_id' => $notice['project_id'],
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

  /**
   * Get all user labels.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return array
   *   The label information, keyed by label ID.
   */
  public function getUserLabels(AccountInterface $account): array {
    $projects = $this->getUserProjects($account);

    if (empty($projects)) {
      return [];
    }

    $project_ids = array_keys($projects);
    $query = $this->db->select('mukurtu_local_contexts_labels', 'labels');
    $query->condition('project_id', $project_ids, 'IN');
    $query->join('mukurtu_local_contexts_projects', 'p', 'labels.project_id = p.id');
    $query->fields('labels', ['id', 'name', 'type', 'display']);
    $query->fields('p', ['provider_id', 'title', 'privacy', 'updated']);
    $query->addField('p', 'id', 'project_id');

    $result = $query->execute();

    $labels = $result->fetchAllAssoc('id', PDO::FETCH_ASSOC);
    return $labels;
  }

  /**
   * Get all user notices.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return array
   *   The notice information, keyed by notice ID.
   */
  public function getUserNotices(AccountInterface $account): array {
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
        'project_id' => $notice['project_id'],
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
