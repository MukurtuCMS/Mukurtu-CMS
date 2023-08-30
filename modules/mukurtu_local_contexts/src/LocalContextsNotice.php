<?php

namespace Drupal\mukurtu_local_contexts;

class LocalContextsNotice extends LocalContextsHubBase
{
  protected $title;
  protected $privacy;
  protected $valid;
  protected $project_id;
  protected $type;
  protected $display;
  public $name;
  public $svg_url;
  public $default_text;
  public $translations;

  public function __construct($id)
  {
    parent::__construct();
    list($this->project_id, $this->type, $this->display) = explode(':', $id);
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

    $bookkeep = [];

    // Load every translation.
    while ($translationInfo = $tResult->fetchAssoc()) {
      // Bookkeep the number of locales there are (including where locale is
      // null).

      // Must determine the proper index to store each translation at, since we
      // could have no locales or multiple translations per locale.
      $translationIndex = '';

      // Handle translations with no or null locale.
      if (!isset($translationInfo['locale']) || $translationInfo['locale'] == '') {
        // Check if the bookkeep is tracking translations with no locale yet.
        if (!isset($bookkeep['no_locale_count'])) {
          // If it isn't, initialize the no_locale_count key to 1.
          $bookkeep['no_locale_count'] = 1;
        }
        else {
          // If it is, increment the no locale count.
          $bookkeep['no_locale_count']++;
        }

        // Now set the translation index to the no locale count.
        $translationIndex = strval($bookkeep['no_locale_count']);
      }

      // Handle translations with locales.
      else {
        // Check if this locale exists in the bookkeep.
        if (!isset($bookkeep[$translationInfo['locale']])) {
          // If not, initialize its count to 1.
          $bookkeep[$translationInfo['locale']] = 1;
        }
        else {
          // If so, increment its count by 1.
          $bookkeep[$translationInfo['locale']]++;
        }
        // Generate the translation index but add its count to the end so it's
        // unique.
        $translationIndex = $translationInfo['locale'] . '-' . $bookkeep[$translationInfo['locale']];
      }
      $this->translations[$translationIndex] = $translationInfo;
    }
  }
}
