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
  public $svg_url;
  public $default_text;
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
      ->fields('l', ['id', 'name', 'svg_url', 'default_text']);
    $result = $query->execute();

    $tQuery = $this->db->select('mukurtu_local_contexts_label_translations', 't')
      ->condition('t.label_id', $this->label_id)
      ->fields('t', ['id', 'locale', 'language', 'name', 'text']);
    $tResult = $tQuery->execute();

    $label = $result->fetchAssoc();
    $this->name = $label['name'] ?? '';
    $this->svg_url = $label['svg_url'] ?? NULL;
    $this->default_text = $label['default_text'] ?? '';

    // Load every translation.
    $bookkeep = [];
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
          // If it is, increment the no locale count by 1.
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
