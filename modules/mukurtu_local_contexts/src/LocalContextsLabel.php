<?php

namespace Drupal\mukurtu_local_contexts;

class LocalContextsLabel extends LocalContextsHubBase {
  protected $title;
  protected $privacy;
  protected $valid;
  protected $project_id;
  protected $label_id;
  protected $translationId;
  public $name;
  public $svg_url;
  public $default_text;
  public $locale;
  public $language;
  public $translationName;
  public $translationText;

  public function __construct($id) {
    parent::__construct();
    list($this->project_id, $this->label_id) = explode(':', $id);
    $this->load();
  }

  protected function load() {
    $query = $this->db->select('mukurtu_local_contexts_labels', 'l')
      ->condition('l.project_id', $this->project_id)
      ->condition('l.id', $this->label_id)
      ->fields('l', ['id', 'name', 'svg_url', 'default_text']);
    $result = $query->execute();

    $tQuery = $this->db->select('mukurtu_local_contexts_label_translations', 't')
      ->condition('t.label_id', $this->label_id)
      ->fields('t', ['id', 'locale', 'language', 'name', 'text']);
    $tResult = $tQuery->execute();

    $label = $result->fetchAssoc();
    $this->name = $label['name'] ?? '';
    $this->svg_url = $label['svg_url'] ?? NULL;
    $this->default_text = $label['text'] ?? '';

    $translationInfo = $tResult->fetchAssoc();
    $this->translationId = $translationInfo['id'] ?? '';
    $this->locale = $translationInfo['locale'] ?? '';
    $this->language = $translationInfo['language'] ?? '';
    $this->translationName = $translationInfo['name'] ?? '';
    $this->translationText = $translationInfo['text'] ?? '';
  }

}
