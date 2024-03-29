<?php

namespace Drupal\mukurtu_local_contexts;

use PHPUnit\Framework\Error\Notice;

class LocalContextsProject extends LocalContextsHubBase {
  protected $title;
  protected $privacy;
  protected $valid;
  protected $id;

  public function __construct($id) {
    parent::__construct();
    $this->id = $id;
    $project = $this->fetch($id);
    $this->valid = $project !== FALSE;
    $this->title = $project['title'] ?? NULL;
    $this->privacy = $project['privacy'] ?? NULL;
  }

  protected function fetch($id) {
    if (!$id) {
      return NULL;
    }

    $query = $this->db->select('mukurtu_local_contexts_projects', 'project')
      ->condition('project.id', $id)
      ->fields('project', ['id', 'provider_id', 'title', 'privacy', 'updated']);
    $result = $query->execute();
    $project = $result->fetchAssoc();

    if ($project === FALSE) {
      if ($hubProject = $this->fetchProjectFromHub($id)) {
        return [
          'title' => $hubProject['title'],
          'privacy' => $hubProject['project_privacy'],
        ];
      }
    }

    return $project;
  }

  protected function fetchProjectFromHub($id) {
    if ($project = $this->get("projects/{$id}")) {
      if (empty($project['unique_id'])) {
        return $project;
      }

      // Update our local cache of this project.
      $projectFields = [
        'id' => $project['unique_id'],
        'provider_id' => $project['providers_id'],
        'title' => $project['title'],
        'privacy' => $project['project_privacy'],
        'updated' => time(),
      ];

      $query = $this->db->update('mukurtu_local_contexts_projects')
        ->condition('id', $id)
        ->fields($projectFields);
      $result = $query->execute();

      // Project doesn't exist in our local cache, insert it.
      if (!$result) {
        $query = $this->db->insert('mukurtu_local_contexts_projects')->fields($projectFields);
        $result = $query->execute();
        $prior_cached_labels = [];
        $prior_cached_notices = [];
      } else {
        // Get any existing cached tk labels, bc labels, and notices so we can
        // compare to the response from the hub and delete any that are no
        // longer there.
        $prior_cached_labels = array_merge($this->getLabels("tk"), $this->getLabels("bc"));
        $prior_cached_notices = $this->getNotices();
      }
      // Cache the tk labels and their translations.
      if (isset($project['tk_labels'])) {
        $this->cacheLabels($project['tk_labels'], "tk", $project['unique_id'], $prior_cached_labels);
        $this->cacheLabelTranslations($project['tk_labels']);
      }

      // Cache the bc labels and their translations.
      if (isset($project['bc_labels'])) {
        $this->cacheLabels($project['bc_labels'], "bc", $project['unique_id'], $prior_cached_labels);
        $this->cacheLabelTranslations($project['bc_labels']);
      }

      // Cache the notices and their translations.
      if (isset($project['notice'])) {
        $this->cacheNoticesAndTranslations($project['notice'], $project['unique_id'], $prior_cached_notices);
      }

      // Delete any cached tk, bc labels, notices, and their translations that
      // no longer exist.
      $this->deleteCachedLabelsAndTranslations($prior_cached_labels, $project['unique_id']);
      $this->deleteCachedNoticesAndTranslations($prior_cached_notices, $project['unique_id']);
    }
    return $project;
  }

  public function getLabels($tk_or_bc) {
    $query = $this->db->select('mukurtu_local_contexts_labels', 'l')
      ->condition('l.project_id', $this->id)
      ->condition('l.tk_or_bc', $tk_or_bc)
      ->fields('l', ['id', 'name', 'svg_url', 'default_text']);
    $result = $query->execute();

    $labels = [];
    while ($label = $result->fetchAssoc()) {
      $labels[$label['id']] = [
        'id' => $label['id'],
        'name' => $label['name'],
        'svg_url' => $label['svg_url'],
        'text' => $label['default_text'],
      ];
    }

    return $labels;
  }

  public function getNotices() {
    $query = $this->db->select('mukurtu_local_contexts_notices', 'n')
      ->condition('n.project_id', $this->id)
      ->fields('n', ['project_id', 'type', 'name', 'svg_url', 'default_text']);
    $result = $query->execute();

    $notices = [];
    while ($notice = $result->fetchAssoc()) {
      $noticeId = $this->id . ':' . $notice['type'];
      $notices[$noticeId] = [
        'project_id' => $notice['project_id'],
        'notice_type' => $notice['type'],
        'name' => $notice['name'],
        'svg_url' => $notice['svg_url'],
        'text' => $notice['default_text'],
      ];
    }

    return $notices;
  }

  protected function cacheLabels($labels, $tk_or_bc, $id, &$prior_cached_labels) {
    foreach ($labels as $label) {
      $labelFields = [
        'id' => $label['unique_id'],
        'project_id' => $id,
        'name' => $label['name'],
        'type' => $label['label_type'],
        'locale' => $label['language_tag'] ?? NULL,
        'language' => $label['language'] ?? NULL,
        'img_url' => $label['img_url'],
        'svg_url' => $label['svg_url'],
        'audio_url' => $label['audiofile'] ?? NULL,
        'community' => $label['community'],
        'default_text' => $label['label_text'],
        'display' => 'label',
        'tk_or_bc' => $tk_or_bc,
        'updated' => time(),
      ];

      $query = $this->db->update('mukurtu_local_contexts_labels')
        ->condition('id', $label['unique_id'])
        ->condition('project_id', $id)
        ->fields($labelFields);
      $result = $query->execute();

      // Track labels we've updated so we can compare versus our last result
      // from the hub and determine if any have been deleted.
      if (isset($prior_cached_labels[$label['unique_id']])) {
        unset($prior_cached_labels[$label['unique_id']]);
      }

      if (!$result) {
        $query = $this->db->insert('mukurtu_local_contexts_labels')->fields($labelFields);
        $result = $query->execute();
      }
    }
  }

  protected function cacheLabelTranslations($labels) {
    foreach ($labels as $label) {
      $translations = $label['translations'] ?? [];
      foreach ($translations as $translation) {
        $translationFields = [
          'label_id' => $label['unique_id'],
          'locale' => $translation['language_tag'] ?? NULL,
          'language' => $translation['language'] ?? NULL,
          'name' => $translation['translated_name'] ?? '',
          'text' => $translation['translated_text'] ?? '',
          'updated' => time(),
        ];

        // The label hub doesn't identify translations. Users can
        // alter locale/language as they see fit, so we will delete all
        // translations and insert them all fresh rather than trying to
        // update.
        $query = $this->db->delete('mukurtu_local_contexts_label_translations')
          ->condition('label_id', $label['unique_id']);
        $query->execute();
        $query = $this->db->insert('mukurtu_local_contexts_label_translations')->fields($translationFields);
        $query->execute();
      }
    }
  }

  protected function cacheNoticesAndTranslations($notices, $projectId, &$prior_cached_notices) {
    // For our db storage, notice translations need notice_type and project_id,
    // so merging the caching of notices with translations makes more sense here.
    foreach ($notices as $notice) {
      $noticeFields = [
        'project_id' => $projectId,
        'name' => $notice['name'],
        'type' => $notice['notice_type'],
        'img_url' => $notice['img_url'],
        'svg_url' => $notice['svg_url'],
        'default_text' => $notice['default_text'],
        'display' => 'notice',
        'updated' => time(),
      ];

      $query = $this->db->update('mukurtu_local_contexts_notices')
        ->condition('type', $notice['notice_type'])
        ->condition('project_id', $projectId)
        ->fields($noticeFields);
      $result = $query->execute();

      // Cache any translations for this notice.
      $this->cacheNoticeTranslations($notice, $projectId, $notice['notice_type']);

      // Track notices we've updated so we can compare versus our last result
      // from the hub and determine if any have been deleted.
      // The ids of prior cached notices are 'project_id:notice_type'.
      $noticeId = $projectId . ':' . $notice['notice_type'];
      if (isset($prior_cached_notices[$noticeId])) {
        unset($prior_cached_notices[$noticeId]);
      }

      if (!$result) {
        $query = $this->db->insert('mukurtu_local_contexts_notices')->fields($noticeFields);
        $result = $query->execute();
      }
    }
  }

  protected function cacheNoticeTranslations($notice, $projectId, $noticeType) {
    $translations = $notice['translations'] ?? [];
    foreach ($translations as $translation) {
      $translationFields = [
        'project_id' => $projectId,
        'type' => $noticeType,
        'locale' => $translation['language_tag'] ?? NULL,
        'language' => $translation['language'] ?? NULL,
        'name' => $translation['translated_name'] ?? '',
        'text' => $translation['translated_text'] ?? '',
        'updated' => time(),
      ];

      // The label hub doesn't identify translations. Users can
      // alter locale/language as they see fit, so we will delete all
      // translations and insert them all fresh rather than trying to
      // update.
      $query = $this->db->delete('mukurtu_local_contexts_notice_translations')
        ->condition('project_id', $projectId)
        ->condition('type', $noticeType)
        ->condition('locale', $translation['language_tag']);
      $query->execute();
      $query = $this->db->insert('mukurtu_local_contexts_notice_translations')->fields($translationFields);
      $query->execute();
    }

  }

  protected function deleteCachedLabelsAndTranslations(&$prior_cached_labels, $id) {
    foreach ($prior_cached_labels as $deleted_label_id => $deleted_label) {
      $query = $this->db->delete('mukurtu_local_contexts_label_translations')
        ->condition('label_id', $deleted_label_id);
      $query->execute();

      $query = $this->db->delete('mukurtu_local_contexts_labels')
        ->condition('id', $deleted_label_id)
        ->condition('project_id', $id);
      $query->execute();
    }
  }

  protected function deleteCachedNoticesAndTranslations(&$prior_cached_notices, $id) {
    $id = '';
    $noticeType = '';

    foreach ($prior_cached_notices as $deleted_notice_id => $deleted_notice) {
      list($id, $noticeType) = explode(':', $deleted_notice_id);

      // Delete all translations before deleting the corresponding notice.
      $query = $this->db->delete('mukurtu_local_contexts_notice_translations')
        ->condition('project_id', $id)
        ->condition('type', $noticeType);
      $query->execute();

      $query = $this->db->delete('mukurtu_local_contexts_notices')
        ->condition('project_id', $id)
        ->condition('type', $noticeType);
      $query->execute();
    }
  }

  public function id() {
    return $this->id;
  }

  public function getTitle() {
    return $this->title;
  }

  public function getPrivacy() {
    return $this->privacy;
  }

  public function isValid() {
    return $this->valid;
  }

  public function inUse() : bool {
    // @todo Decide if we should lookup ALL fields of our local contexts types
    // or if we want to keep things hardwired to just our usage.

    // Check if any content is using the projects in the projects field.
    $query = \Drupal::entityQuery('node')
      ->condition('field_local_contexts_projects', $this->id)
      ->accessCheck(FALSE);
    $results = $query->execute();
    if (!empty($results)) {
      return TRUE;
    }

    $labels = array_merge($this->getLabels("tk"), $this->getLabels("bc"));
    $notices = $this->getNotices();

    if (!empty($labels)) {
      // Build the project ID: label ID: 'label' keys.
      $values = array_map(fn($v) => $this->id . ':' . $v['id'] . ':' . 'label', $labels);

      // Check if any content is using those keys.
      $query = \Drupal::entityQuery('node')
        ->condition('field_local_contexts_labels_and_notices', $values, 'IN')
        ->accessCheck(FALSE);
      $results = $query->execute();
      if (!empty($results)) {
        return TRUE;
      }
    }
    else if (!empty($notices)) {
      // Build the project ID: notice type: 'notice' keys.
      $notices = array_keys($notices);
      $values = array_map(fn($v) => strval($v) . ':' . 'notice', $notices);

      // Check if any content is using those keys.
      $query = \Drupal::entityQuery('node')
        ->condition('field_local_contexts_labels_and_notices', $values, 'IN')
        ->accessCheck(FALSE);
      $results = $query->execute();
      if (!empty($results)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
