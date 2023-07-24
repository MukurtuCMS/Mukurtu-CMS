<?php

namespace Drupal\mukurtu_local_contexts;

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
    $db = \Drupal::database();
    $query = $db->select('mukurtu_local_contexts_projects', 'project')
      ->condition('project.id', $id)
      ->fields('project', ['id', 'provider_id', 'title', 'privacy', 'updated']);
    $result = $query->execute();
    $project = $result->fetchAssoc();

    if ($project === FALSE) {
      if ($hubProject = $this->fetchProjectFromHub($id)) {
        return [
          'title' => $hubProject['title'],
          'privacy' => $hubProject['privacy'],
        ];
      }
    }

    return $project;
  }

  protected function fetchProjectFromHub($id) {
    $db = \Drupal::database();
    if ($project = $this->get("projects/{$id}")) {
      if (empty($project['unique_id'])) {
        return $project;
      }

      // Update our local cache of this project.
      $projectFields = [
          'id' => $project['unique_id'],
          'provider_id' => $project['provider_id'],
          'title' => $project['title'],
          'privacy' => $project['project_privacy'],
          'updated' => time(),
      ];

      $query = $db->update('mukurtu_local_contexts_projects')
        ->condition('id', $id)
        ->fields($projectFields);
      $result = $query->execute();

      // Project doesn't exist in our local cache, insert it.
      if (!$result) {
        $query = $db->insert('mukurtu_local_contexts_projects')->fields($projectFields);
        $result = $query->execute();
        $prior_cached_tk_labels = [];
      } else {
        // Get any existing cached labels so we can compare to the response from
        // the hub and delete any that are no longer there.
        $prior_cached_tk_labels = $this->getTkLabels();
      }

      // Cache the labels.
      foreach ($project['tk_labels'] as $label) {
        $labelFields = [
          'id' => $label['unique_id'],
          'project_id' => $project['unique_id'],
          'name' => $label['name'],
          'type' => $label['label_type'],
          'locale' => $label['language_tag'] ?? NULL,
          'language' => $label['language'] ?? NULL,
          'img_url' => $label['img_url'],
          'svg_url' => $label['svg_url'],
          'audio_url' => $label['audiofile'] ?? NULL,
          'community' => $label['community'],
          'default_text' => $label['label_text'],
          'updated' => time(),
        ];

        $query = $db->update('mukurtu_local_contexts_labels')
          ->condition('id', $label['unique_id'])
          ->condition('project_id', $project['unique_id'])
          ->fields($labelFields);
        $result = $query->execute();

        // Track labels we've updated so we can compare versus our last result
        // from the hub and determine if any have been deleted.
        if (isset($prior_cached_tk_labels[$label['unique_id']])) {
          unset($prior_cached_tk_labels[$label['unique_id']]);
        }

        if (!$result) {
          $query = $db->insert('mukurtu_local_contexts_labels')->fields($labelFields);
          $result = $query->execute();
        }

        $translations = $label['translations'] ?? [];
        foreach ($translations as $translation) {
          $translationFields = [
            'id' => $translation['unique_id'],
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
          $query = $db->delete('mukurtu_local_contexts_label_translations')
            ->condition('label_id', $label['unique_id']);
          $query->execute();
          $query = $db->insert('mukurtu_local_contexts_label_translations')->fields($translationFields);
          $result = $query->execute();
        }
      }

      // Delete any cached TK Labels and translations that no longer exist;
      foreach ($prior_cached_tk_labels as $deleted_tk_label_id => $deleted_tk_label) {
        $query = $db->delete('mukurtu_local_contexts_label_translations')
          ->condition('label_id', $deleted_tk_label_id);
        $query->execute();

        $query = $db->delete('mukurtu_local_contexts_labels')
          ->condition('id', $deleted_tk_label_id)
          ->condition('project_id', $project['unique_id']);
        $query->execute();
      }

    }
    return $project;
  }

  public function getTkLabels() {
    $db = \Drupal::database();
    $query = $db->select('mukurtu_local_contexts_labels', 'l')
      ->condition('l.project_id', $this->id)
      ->fields('l', ['id', 'name', 'svg_url', 'default_text']);
    $result = $query->execute();

    $labels = [];
    while($label = $result->fetchAssoc()) {
      $labels[$label['id']] = [
        'id' => $label['id'],
        'name' => $label['name'],
        'svg_url' => $label['svg_url'],
        'text' => $label['default_text'],
      ];
    }

    return $labels;
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

}