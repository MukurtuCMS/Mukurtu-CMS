<?php

namespace Drupal\mukurtu_local_contexts;

use PDO;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\og\Og;
use Drupal\Core\Session\AccountInterface;

class LocalContextsSupportedProjectManager {

  /**
   * Create a new LocalContextsSupportedProjectManager instance.
   *
   * @param \Drupal\Core\Database\Connection $db
   *   The database connection.
   */
  public function __construct(protected Connection $db) {
  }

  /**
   * Determine if a project ID matches a legacy (v3-migrated) TK Labels project.
   *
   * Legacy projects are synthetic projects created by mukurtu_migrate to
   * preserve v3 TK Labels customizations. They are never created through the
   * Local Contexts Hub sync UI and should not be offered for new selections.
   *
   * @param string $id
   *   The project ID to check.
   *
   * @return bool
   *   True if the ID matches 'default_tk', 'sitewide_tk', or 'comm_{nid}_tk'.
   */
  public function isLegacyProjectId(string $id): bool {
    return $id === 'default_tk' || $id === 'sitewide_tk' || (bool) preg_match('/^comm_\d+_tk$/', $id);
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
    $query->fields('p', ['id', 'provider_id', 'title', 'privacy', 'updated', 'status', 'archived']);
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
   * @param bool $exclude_legacy
   *   Whether to exclude legacy (v3-migrated) projects from the result.
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
      ->fields('p', ['id', 'provider_id', 'title', 'privacy', 'updated', 'status', 'status_message', 'archived']);
    $query->orderBy('p.title');

    $result = $query->execute();
    $projects = $result->fetchAllAssoc('id', PDO::FETCH_ASSOC);

    if ($exclude_legacy) {
      foreach (array_keys($projects) as $id) {
        if ($this->isLegacyProjectId((string) $id)) {
          unset($projects[$id]);
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
   * @param bool $exclude_legacy
   *   Whether to exclude legacy (v3-migrated) projects from the result.
   *
   * @return array
   *   The project information, keyed by project ID.
   */
  public function getGroupSupportedProjects(ContentEntityInterface $group, $exclude_legacy = FALSE): array {
    $query = $this->db->select('mukurtu_local_contexts_supported_projects', 'sp');
    $query->join('mukurtu_local_contexts_projects', 'p', 'sp.project_id = p.id');
    $query
      ->condition('sp.type', $group->getEntityTypeId())
      ->condition('sp.group_id', $group->id())
      ->fields('p', ['id', 'provider_id', 'title', 'privacy', 'updated', 'status', 'status_message', 'archived']);
    $query->orderBy('p.title');

    $result = $query->execute();
    $projects = $result->fetchAllAssoc('id', PDO::FETCH_ASSOC);

    if ($exclude_legacy) {
      foreach (array_keys($projects) as $id) {
        if ($this->isLegacyProjectId((string) $id)) {
          unset($projects[$id]);
        }
      }
    }
    return $projects;
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

    $this->deleteProjectCacheRows($project_id);
  }

  /**
   * Delete all cached data for a project: its labels, notices, and their
   * translations, its supported-project mapping rows (across every
   * site/community/protocol scope), and the project row itself.
   *
   * Shared by removeProject() (the admin-triggered "un-support this
   * project" flow, which refuses to delete an in-use project unless
   * force_delete is set) and purgeDeletedProject() (which always deletes,
   * because the project has been confirmed deleted on the hub itself).
   *
   * @param string $project_id
   *   The project ID to purge from the local cache.
   */
  private function deleteProjectCacheRows(string $project_id): void {
    // Delete labels provided by the project.
    $labels = $this->getAllLabels();
    foreach ($labels as $label_id => $label) {
      if ($label['project_id'] == $project_id) {
        $this->db->delete('mukurtu_local_contexts_labels')
          ->condition('id', $label_id)
          ->condition('project_id', $project_id)
          ->execute();
        $this->db->delete('mukurtu_local_contexts_label_translations')
          ->condition('label_id', $label_id)
          ->execute();
      }
    }

    // Delete notices provided by the project. Notice IDs are compound
    // ("{project_id}:{type}") because the notices table has no single "id"
    // column - its primary key is (project_id, type).
    $notices = $this->getAllNotices();
    foreach ($notices as $notice_id => $notice) {
      if ($notice['project_id'] == $project_id) {
        [, $type] = explode(':', $notice_id, 2);
        $this->db->delete('mukurtu_local_contexts_notices')
          ->condition('project_id', $project_id)
          ->condition('type', $type)
          ->execute();
        $this->db->delete('mukurtu_local_contexts_notice_translations')
          ->condition('project_id', $project_id)
          ->condition('type', $type)
          ->execute();
      }
    }

    // Delete any project usage tracking.
    $this->db->delete('mukurtu_local_contexts_supported_projects')
      ->condition('project_id', $project_id)
      ->execute();

    // Delete the project itself.
    $this->db->delete('mukurtu_local_contexts_projects')
      ->condition('id', $project_id)
      ->execute();
  }

  /**
   * Hard-delete a project confirmed deleted from the Local Contexts Hub.
   *
   * Unlike removeProject() (the admin-triggered "un-support this project"
   * flow, which refuses to delete an in-use project and never touches
   * content), this deletes regardless of use, and also strips references
   * to the project from any content that has them - leaving those
   * references dangling once the cache is gone causes silent ghost
   * rendering and, on the next save, silent loss of the reference from the
   * entity, so they must be actively cleaned up rather than left behind.
   *
   * Must only ever be called after
   * \Drupal\mukurtu_local_contexts\LocalContextsProject::isConfirmedDeleted()
   * is TRUE for the given project - never for unauthorized/forbidden/error
   * statuses, which are recoverable and must never trigger this.
   *
   * @param string $project_id
   *   The confirmed-deleted project ID.
   * @param string $reason
   *   Why the project was purged, stored in the purge log.
   *
   * @return array{labels: int, notices: int, nodes_queued: int}
   *   Counts of what was purged/queued, or all zeros if the purge was
   *   skipped (legacy project ID, lock contention, or the project's
   *   persisted status is no longer not_found).
   */
  public function purgeDeletedProject(string $project_id, string $reason = 'not_found'): array {
    $empty = ['labels' => 0, 'notices' => 0, 'nodes_queued' => 0];

    // Legacy (v3-migrated) synthetic project IDs are never on the real
    // hub and will 404 forever - they must never be auto-purged.
    if ($this->isLegacyProjectId($project_id)) {
      return $empty;
    }

    $lock = \Drupal::lock();
    $lock_name = 'mukurtu_local_contexts_purge_' . $project_id;
    if (!$lock->acquire($lock_name, 30)) {
      // Another process is already purging (or otherwise modifying) this
      // project. Skip quietly; it will be re-evaluated next cron run.
      return $empty;
    }

    try {
      // Defensive re-check: confirm the project is still confirmed-deleted
      // before doing anything destructive, in case its status changed (or
      // never actually met the grace period) between the caller's check
      // and now. This makes the method safe to call on its own rather
      // than relying solely on the cron caller's gate.
      $project = new LocalContextsProject($project_id);
      if (!$project->isConfirmedDeleted()) {
        return $empty;
      }

      $title = $project->getTitle();
      $labels = array_merge($project->getLabels('tk'), $project->getLabels('bc'));
      $notices = $project->getNotices();
      $scopes = $this->getSupportedProjectScopes($project_id);

      // Build the compound field values these labels/notices would be
      // stored as on content, before their cache rows (and the IDs they're
      // built from) are deleted.
      $labelAndNoticeValues = array_merge(
        array_map(fn($label) => $project_id . ':' . $label['id'] . ':label', $labels),
        array_map(fn($notice_id) => $notice_id . ':notice', array_keys($notices)),
      );

      $nodesQueued = $this->queueReferenceRemoval($project_id, $labelAndNoticeValues);

      $counts = [
        'labels' => count($labels),
        'notices' => count($notices),
        'nodes_queued' => $nodesQueued,
      ];

      $this->deleteProjectCacheRows($project_id);

      $now = \Drupal::time()->getRequestTime();
      foreach ($scopes as $scope) {
        $this->db->insert('mukurtu_local_contexts_purge_log')->fields([
          'project_id' => $project_id,
          'title' => $title,
          'type' => $scope['type'],
          'group_id' => $scope['group_id'],
          'reason' => $reason,
          'nodes_affected' => $nodesQueued,
          'purged' => $now,
          'dismissed' => 0,
        ])->execute();
      }

      \Drupal::logger('mukurtu_local_contexts')->notice(
        'Purged Local Contexts project @title (@id): confirmed deleted from the hub after @count consecutive not_found sync attempts. Removed @labels label(s) and @notices notice(s) from the local cache, and queued reference cleanup on @nodes node(s).',
        [
          '@title' => $title ?? $project_id,
          '@id' => $project_id,
          '@count' => $project->getNotFoundCount(),
          '@labels' => $counts['labels'],
          '@notices' => $counts['notices'],
          '@nodes' => $nodesQueued,
        ]
      );

      return $counts;
    }
    finally {
      $lock->release($lock_name);
    }
  }

  /**
   * Find every node referencing a project (via
   * field_local_contexts_projects) or any of its labels/notices (via
   * field_local_contexts_labels_and_notices), and queue each distinct node
   * for reference cleanup. Queued items are drained by the
   * mukurtu_local_contexts_reference_cleanup queue worker on subsequent
   * cron runs.
   *
   * @param string $project_id
   *   The project ID being purged.
   * @param array $label_and_notice_values
   *   The full compound field values (e.g. "{project_id}:{label_id}:label")
   *   belonging to this project, built before its cache rows were deleted.
   *
   * @return int
   *   The number of distinct nodes queued.
   */
  public function queueReferenceRemoval(string $project_id, array $label_and_notice_values): int {
    $nids = \Drupal::entityQuery('node')
      ->condition('field_local_contexts_projects', $project_id)
      ->accessCheck(FALSE)
      ->execute();

    if ($label_and_notice_values) {
      $nids += \Drupal::entityQuery('node')
        ->condition('field_local_contexts_labels_and_notices', $label_and_notice_values, 'IN')
        ->accessCheck(FALSE)
        ->execute();
    }

    if (!$nids) {
      return 0;
    }

    $queue = \Drupal::queue('mukurtu_local_contexts_reference_cleanup');
    foreach ($nids as $nid) {
      $queue->createItem([
        'nid' => $nid,
        'project_id' => $project_id,
        'label_and_notice_values' => $label_and_notice_values,
      ]);
    }

    return count($nids);
  }

  /**
   * Get every scope (site/community/protocol) currently supporting a
   * project ID.
   *
   * @param string $project_id
   *   The project ID.
   *
   * @return array
   *   A list of ['type' => ..., 'group_id' => ...] rows.
   */
  private function getSupportedProjectScopes(string $project_id): array {
    $query = $this->db->select('mukurtu_local_contexts_supported_projects', 'sp')
      ->condition('sp.project_id', $project_id)
      ->fields('sp', ['type', 'group_id']);
    return $query->execute()->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Get undismissed purge log entries for a given scope.
   *
   * @param string $type
   *   'site', 'community', or 'protocol'.
   * @param int $group_id
   *   The group entity ID, or 0 for site.
   *
   * @return array
   *   The undismissed purge log rows for this scope.
   */
  public function getUndismissedPurgeNotices(string $type, int $group_id): array {
    $query = $this->db->select('mukurtu_local_contexts_purge_log', 'log')
      ->condition('type', $type)
      ->condition('group_id', $group_id)
      ->condition('dismissed', 0)
      ->fields('log', ['id', 'project_id', 'title', 'reason', 'nodes_affected', 'purged']);
    $query->orderBy('purged', 'DESC');
    return $query->execute()->fetchAllAssoc('id', PDO::FETCH_ASSOC);
  }

  /**
   * Mark purge log entries as dismissed.
   *
   * @param array $ids
   *   The purge log entry IDs to dismiss.
   */
  public function dismissPurgeNotices(array $ids): void {
    if (!$ids) {
      return;
    }
    $this->db->update('mukurtu_local_contexts_purge_log')
      ->condition('id', $ids, 'IN')
      ->fields(['dismissed' => 1])
      ->execute();
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
    $query->fields('p', ['provider_id', 'title', 'privacy', 'updated', 'status']);
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
    $query->fields('p', ['provider_id', 'title', 'privacy', 'updated', 'status']);
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
        'status' => $notice['status'],
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
    $query->fields('p', ['provider_id', 'title', 'privacy', 'updated', 'status']);
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
    $query->fields('p', ['provider_id', 'title', 'privacy', 'updated', 'status']);

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
        'status' => $notice['status'],
      ];
    }

    return $notices;
  }

}
