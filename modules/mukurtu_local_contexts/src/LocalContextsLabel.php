<?php

namespace Drupal\mukurtu_local_contexts;

class LocalContextsLabel extends LocalContextsHubBase {
  protected $title;
  protected $privacy;
  protected $valid;
  protected $project_id;
  protected $label_id;
  protected $display;
  public $name;
  public $img_url;
  public $svg_url;
  public $audio_url;
  public $community;
  public $default_text;
  public $language;
  public $translations;

  public function __construct($id) {
    parent::__construct();
    list($this->project_id, $this->label_id, $this->display) = explode(':', $id);
    $this->load();
  }

  protected function load() {
    $query = $this->db->select('mukurtu_local_contexts_labels', 'l')
      ->condition('l.project_id', $this->project_id)
      ->condition('l.id', $this->label_id)
      ->fields('l', [
        'id', 'name', 'img_url', 'svg_url',
        'audio_url', 'community', 'default_text', 'language',
      ]);
    $result = $query->execute();

    $tQuery = $this->db->select('mukurtu_local_contexts_label_translations', 't')
      ->condition('t.label_id', $this->label_id)
      ->fields('t', ['id', 'locale', 'language', 'name', 'text']);
    $tResult = $tQuery->execute();

    $label = $result->fetchAssoc();
    $this->name = $label['name'] ?? '';
    $this->img_url = $label['img_url'] ?? NULL;
    $this->svg_url = $label['svg_url'] ?? NULL;
    $this->audio_url = $label['audio_url'] ?? NULL;
    $this->community = $label['community'] ?? '';
    $this->default_text = $label['default_text'] ?? '';
    $this->language = $label['language'] ?? NULL;

    $this->translations = $this->indexTranslations($tResult->fetchAll(\PDO::FETCH_ASSOC));
  }

}
