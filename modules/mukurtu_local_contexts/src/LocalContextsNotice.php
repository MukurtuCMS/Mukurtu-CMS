<?php

namespace Drupal\mukurtu_local_contexts;

class LocalContextsNotice extends LocalContextsHubBase
{
  protected $title;
  protected $privacy;
  protected $valid;
  protected $project_id;
  protected $type;
  public $name;
  public $svg_url;
  public $default_text;
  public $translations;
  public $locale;
  public $language;
  public $translationName;
  public $translationText;

  public function __construct($id)
  {
    parent::__construct();
    list($this->project_id, $this->type) = explode(':', $id);
    $this->load();
  }

  protected function load()
  {
    $query = $this->db->select('mukurtu_local_contexts_notices', 'n')
      ->condition('n.project_id', $this->project_id)
      ->condition('n.type', $this->type)
      ->fields('n', ['name', 'svg_url', 'default_text']);
    $result = $query->execute();

    $notice = $result->fetchAssoc();
    $this->name = $notice['name'] ?? '';
    $this->svg_url = $notice['svg_url'] ?? NULL;
    $this->default_text = $notice['default_text'] ?? '';

    $tQuery = $this->db->select('mukurtu_local_contexts_notice_translations', 't')
      ->condition('t.project_id', $this->project_id)
      ->condition('t.type', $this->type)
      ->fields('t', ['locale', 'language', 'name', 'text']);
    $tResult = $tQuery->execute();

    $translationInfo = $tResult->fetchAssoc();
    $this->locale = $translationInfo['locale'] ?? '';
    $this->language = $translationInfo['language'] ?? '';
    $this->translationName = $translationInfo['name'] ?? '';
    $this->translationText = $translationInfo['text'] ?? '';
  }
}
