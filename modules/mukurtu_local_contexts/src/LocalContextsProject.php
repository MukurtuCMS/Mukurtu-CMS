<?php

namespace Drupal\mukurtu_local_contexts;

class LocalContextsProject extends LocalContextsHubBase {

  /**
   * @var string The 36 character Local Contexts project ID.
   */
  protected $id;

  /**
   * @var string|null The title of the project.
   */
  protected ?string $title;

  /**
   * @var string|null The privacy setting of the project.
   */
  protected ?string $privacy;

  /**
   * @var int|null Timestamp of the last updated time.
   */
  protected ?int $updated;

  /**
   * @var bool Whether the project has been loaded from the API.
   */
  protected bool $valid;

  /**
   * @var string|null Any error that occurs during the fetch process.
   */
  protected ?string $errorMessage;

  public function __construct($id) {
    parent::__construct();
    $this->id = $id;
    $project = $this->load();
    $this->valid = $project !== FALSE;
    $this->title = $project['title'] ?? NULL;
    $this->privacy = $project['privacy'] ?? NULL;
    $this->updated = $project['updated'] ?? NULL;
  }

  /**
   * Loads this Local Contexts project from the database by its LC ID.
   *
   * @return array|bool
   *   The title and privacy information if found. FALSE if not found.
   */
  protected function load() {
    $query = $this->db
      ->select('mukurtu_local_contexts_projects', 'project')
      ->condition('project.id', $this->id)
      ->fields('project', ['id', 'provider_id', 'title', 'privacy', 'updated']);
    $result = $query->execute();
    return $result->fetchAssoc();
  }

  /**
   * Updates the local copy of a project from the hub and updates the object.
   *
   * @param $api_key
   *   The API key to use for the fetch request.
   *
   * @return bool
   *   Whether the project was successfully fetched from the hub. Any errors
   *   can be retrieved from the getErrorMessage() method.
   */
  public function fetchFromHub($api_key) {
    $id = $this->id;
    if ($project = $this->lcApi->makeRequest("projects/{$id}", $api_key)) {
      if (empty($project['unique_id'])) {
        $this->errorMessage = $project['detail'] ?? '';
        return FALSE;
      }

      // Update the object properties.
      $this->title = $project['title'];
      $this->privacy = $project['project_privacy'];
      $this->updated = $this->requestTime;

      // Provider ID seems to sometimes be an array, sometimes a string.
      $provider_id = is_array($project['external_ids']['providers_id']) ? reset($project['external_ids']['providers_id']) : $project['external_ids']['providers_id'];

      // Update our local copy of this project.
      $projectFields = [
        'id' => $id,
        'provider_id' => $provider_id,
        'title' => $this->title,
        'privacy' => $this->privacy,
        'updated' => $this->updated,
      ];

      $query = $this->db->update('mukurtu_local_contexts_projects')
        ->condition('id', $id)
        ->fields($projectFields);
      $result = $query->execute();

      // Project doesn't exist in our local copy, insert it.
      if (!$result) {
        $query = $this->db->insert('mukurtu_local_contexts_projects')->fields($projectFields);
        $query->execute();
        $prior_saved_labels = [];
        $prior_saved_notices = [];
      }
      else {
        // Get any existing saved tk labels, bc labels, and notices so we can
        // compare to the response from the hub and delete any that are no
        // longer there.
        $prior_saved_labels = array_merge($this->getLabels("tk"), $this->getLabels("bc"));
        $prior_saved_notices = $this->getNotices();
      }
      // Save the tk labels and their translations.
      if (isset($project['tk_labels'])) {
        $this->saveLabels($project['tk_labels'], "tk", $project['unique_id'], $prior_saved_labels);
        $this->saveLabelTranslations($project['tk_labels']);
      }

      // Save the bc labels and their translations.
      if (isset($project['bc_labels'])) {
        $this->saveLabels($project['bc_labels'], "bc", $project['unique_id'], $prior_saved_labels);
        $this->saveLabelTranslations($project['bc_labels']);
      }

      // Save the notices and their translations.
      if (isset($project['notice'])) {
        $this->saveNoticesAndTranslations($project['notice'], $project['unique_id'], $prior_saved_notices);
      }

      // Delete any saved tk, bc labels, notices, and their translations that
      // no longer exist.
      $this->deleteSavedLabelsAndTranslations($prior_saved_labels, $project['unique_id']);
      $this->deleteSavedNoticesAndTranslations($prior_saved_notices, $project['unique_id']);
    }

    return isset($project['unique_id']);
  }

  public function getLabels($tk_or_bc) {
    $query = $this->db->select('mukurtu_local_contexts_labels', 'l')
      ->condition('l.project_id', $this->id)
      ->condition('l.tk_or_bc', $tk_or_bc)
      ->fields('l', ['id', 'name', 'img_url', 'svg_url', 'default_text']);
    $result = $query->execute();

    $labels = [];
    while ($label = $result->fetchAssoc()) {
      $labels[$label['id']] = [
        'id' => $label['id'],
        'name' => $label['name'],
        'img_url' => $label['img_url'],
        'svg_url' => $label['svg_url'],
        'text' => $label['default_text'],
      ];
    }

    return $labels;
  }

  public function getNotices() {
    $query = $this->db->select('mukurtu_local_contexts_notices', 'n')
      ->condition('n.project_id', $this->id)
      ->fields('n', ['project_id', 'type', 'name', 'img_url', 'svg_url', 'default_text']);
    $result = $query->execute();

    $notices = [];
    while ($notice = $result->fetchAssoc()) {
      $noticeId = $this->id . ':' . $notice['type'];
      $notices[$noticeId] = [
        'project_id' => $notice['project_id'],
        'notice_type' => $notice['type'],
        'name' => $notice['name'],
        'img_url' => $notice['img_url'],
        'svg_url' => $notice['svg_url'],
        'text' => $notice['default_text'],
      ];
    }

    return $notices;
  }

  protected function saveLabels($labels, $tk_or_bc, $id, &$prior_saved_labels) {
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
        'community' => $label['community']['name'],
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
      if (isset($prior_saved_labels[$label['unique_id']])) {
        unset($prior_saved_labels[$label['unique_id']]);
      }

      if (!$result) {
        $query = $this->db->insert('mukurtu_local_contexts_labels')->fields($labelFields);
        $query->execute();
      }
    }
  }

  protected function saveLabelTranslations($labels) {
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

  protected function saveNoticesAndTranslations($notices, $projectId, &$prior_saved_notices) {
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

      // Save any translations for this notice.
      $this->saveNoticeTranslations($notice, $projectId, $notice['notice_type']);

      // Track notices we've updated so we can compare versus our last result
      // from the hub and determine if any have been deleted.
      // The ids of prior saved notices are 'project_id:notice_type'.
      $noticeId = $projectId . ':' . $notice['notice_type'];
      if (isset($prior_saved_notices[$noticeId])) {
        unset($prior_saved_notices[$noticeId]);
      }

      if (!$result) {
        $query = $this->db->insert('mukurtu_local_contexts_notices')->fields($noticeFields);
        $result = $query->execute();
      }
    }
  }

  protected function saveNoticeTranslations($notice, $projectId, $noticeType) {
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

  protected function deleteSavedLabelsAndTranslations(&$prior_saved_labels, $id) {
    foreach ($prior_saved_labels as $deleted_label_id => $deleted_label) {
      $query = $this->db->delete('mukurtu_local_contexts_label_translations')
        ->condition('label_id', $deleted_label_id);
      $query->execute();

      $query = $this->db->delete('mukurtu_local_contexts_labels')
        ->condition('id', $deleted_label_id)
        ->condition('project_id', $id);
      $query->execute();
    }
  }

  protected function deleteSavedNoticesAndTranslations(&$prior_saved_notices, $id) {
    $id = '';
    $noticeType = '';

    foreach ($prior_saved_notices as $deleted_notice_id => $deleted_notice) {
      [$id, $noticeType] = explode(':', $deleted_notice_id);

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

  public function getUrl(): string {
    $endpoint = $this->configFactory->get(self::SETTINGS_CONFIG_KEY)->get('hub_endpoint') ?? LocalContextsApi::DEFAULT_HUB_URL;
    $baseUrl = preg_replace('#/api/v2/?$#', '', rtrim($endpoint, '/'));
    return $baseUrl . '/projects/' . $this->id . '/';
  }

  public function getPrivacy() {
    return $this->privacy;
  }

  public function getUpdated() {
    return $this->updated;
  }

  public function isValid() {
    return $this->valid;
  }

  public function inUse() : bool {
    $referencing = $this->getReferencingNodeIds();

    if (!empty($referencing['project'])) {
      return TRUE;
    }

    foreach ($referencing['labels_and_notices'] as $nids) {
      if (!empty($nids)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Get the IDs of nodes referencing this project.
   *
   * Checks both the whole-project field (field_local_contexts_projects) and
   * the individual label/notice field (field_local_contexts_labels_and_notices),
   * independently - a project can be referenced via either or both, and
   * unlike a simple "is this project in use" check, this needs per-label/
   * notice granularity so callers can tell exactly which references exist.
   *
   * @return array
   *   An array with keys:
   *   - 'project': int[] of node IDs referencing this project via the
   *     whole-project field.
   *   - 'labels_and_notices': array keyed by label ID or notice type (the
   *     same identifier that appears as the middle segment of a
   *     "project_id:label_id_or_type:display" field value), each value an
   *     int[] of node IDs referencing that specific label/notice.
   */
  public function getReferencingNodeIds(): array {
    $referencing = [
      'project' => [],
      'labels_and_notices' => [],
    ];

    $query = \Drupal::entityQuery('node')
      ->condition('field_local_contexts_projects', $this->id)
      ->accessCheck(FALSE);
    $referencing['project'] = array_values($query->execute());

    $labels = array_merge($this->getLabels('tk'), $this->getLabels('bc'));
    foreach ($labels as $labelId => $label) {
      $value = $this->id . ':' . $labelId . ':label';
      $query = \Drupal::entityQuery('node')
        ->condition('field_local_contexts_labels_and_notices', $value)
        ->accessCheck(FALSE);
      $results = array_values($query->execute());
      if (!empty($results)) {
        $referencing['labels_and_notices'][$labelId] = $results;
      }
    }

    $notices = $this->getNotices();
    foreach ($notices as $notice) {
      $type = $notice['notice_type'];
      $value = $this->id . ':' . $type . ':notice';
      $query = \Drupal::entityQuery('node')
        ->condition('field_local_contexts_labels_and_notices', $value)
        ->accessCheck(FALSE);
      $results = array_values($query->execute());
      if (!empty($results)) {
        $referencing['labels_and_notices'][$type] = $results;
      }
    }

    return $referencing;
  }

  /**
   * Resolve the display name of a specific label or notice on this project.
   *
   * @param string $refType
   *   Either 'label' or 'notice'.
   * @param string $refId
   *   The label ID (for 'label') or notice type (for 'notice').
   *
   * @return string|null
   *   The display name, or NULL if no matching label/notice is found.
   */
  public function resolveLabelOrNoticeName(string $refType, string $refId): ?string {
    if ($refType === 'notice') {
      foreach ($this->getNotices() as $notice) {
        if ($notice['notice_type'] === $refId) {
          return $notice['name'];
        }
      }
      return NULL;
    }

    $labels = array_merge($this->getLabels('tk'), $this->getLabels('bc'));
    return $labels[$refId]['name'] ?? NULL;
  }

  /**
   * Get the node IDs referencing one specific label or notice.
   *
   * Unlike getReferencingNodeIds()['labels_and_notices'][$refId], this
   * queries the exact "project_id:ref_id:ref_type" compound value directly,
   * so it can't be affected by a label ID and a notice type colliding on the
   * same string (which would otherwise silently overwrite one another in
   * that merged, flatly-keyed array).
   *
   * @param string $refType
   *   Either 'label' or 'notice'.
   * @param string $refId
   *   The label ID (for 'label') or notice type (for 'notice').
   *
   * @return int[]
   *   Node IDs referencing this exact label/notice.
   */
  public function getReferencingNodeIdsForRef(string $refType, string $refId): array {
    $value = $this->id . ':' . $refId . ':' . $refType;
    $query = \Drupal::entityQuery('node')
      ->condition('field_local_contexts_labels_and_notices', $value)
      ->accessCheck(FALSE);
    return array_values($query->execute());
  }

}
