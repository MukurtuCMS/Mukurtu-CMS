<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 *
 * @MigrateSource(
 *   id = "mukurtu_v3_legacy_tk_community_labels"
 * )
 */
class LegacyTkCommunityLabels extends SqlBase {

  protected $communityNames;
  protected $customLabels;

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator()
  {
    $this->customLabels = [];
    $this->communityNames = [];

    // Query to get all the community customized labels.
    $query = $this->select('variable', 'v')
      ->fields('v', ['name', 'value'])
      ->condition('name', 'mukurtu_comm_custom_TK_%', 'LIKE');
    $result = $query->execute()->fetchAll();

    // Check all the community label values. If the 'custom' property
    // is set, the community has customized that label.
    foreach ($result as $row) {
      $value = unserialize($row['value']);
      foreach ($value as $community_id => $community_label_info) {
        if (is_numeric($community_id)) {
          $custom = $community_label_info['custom'] ?? FALSE;
          if ($custom) {
            $intermediate_label_name = str_replace('mukurtu_comm_custom_', '', $row['name']);
            $cleaned_label_name = str_replace('_', ' ', $intermediate_label_name);

            $labelInitials = $this->getLabelInitials($cleaned_label_name);
            $labelId = 'comm_' . $community_id . '_tk_' . $labelInitials;
            $this->customLabels[$labelId] = [
              'id' => $labelId,
              'label_name' => $cleaned_label_name,
              'label_text' => strip_tags($community_label_info['text']),
              'community_id' => $community_id,
            ];
          }
        }
      }
    }

    // Get all the community titles.
    $query = $this->select('node', 'n')
      ->fields('n', ['nid', 'title'])
      ->condition('type', 'community');
    if ($result = $query->execute()->fetchAllAssoc('nid')) {
      $this->communityNames = array_column($result, 'title', 'nid');
    }

    return new \ArrayIterator($this->customLabels);
  }

  protected function getLabelInitials($labelName)
  {
    $toks = explode('(', $labelName);
    $toks = explode(')', $toks[1]);
    $toks = explode(' ', $toks[0]);
    return strtolower($toks[1]);
  }

  /**
   * {@inheritdoc}
   */
  public function fields()
  {
    return [
      'id' => $this->t('Label ID'),
      'label_name' => $this->t('Label Name'),
      'label_text' => $this->t('Label custom text'),
      'community_id' =>$this->t('Community ID'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds()
  {
    return ['id' => ['type' => 'string']];
  }

  /**
   * {@inheritdoc}
   */
  public function query()
  {
    // Empty on purpose.
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row)
  {
    if ($community_id = $row->getSource()['community_id']) {
      $name = $this->communityNames[$community_id] ?? NULL;
      if ($name) {
        $row->setSourceProperty('project_id', 'comm_' . $community_id . '_tk');
        $row->setSourceProperty('community', $name);
        $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
        $language = \Drupal::languageManager()->getCurrentLanguage()->getName();
        $row->setSourceProperty('locale', $langcode);
        $row->setSourceProperty('language', $language);

        $initials = $this->getLabelInitials($row->getSourceProperty('label_name'));
        $row->setSourceProperty('img_url', $this->buildUrl('img', $initials));
        $row->setSourceProperty('svg_url', $this->buildUrl('svg', $initials));
        $row->setSourceProperty('audio_url', '');
        $row->setSourceProperty('updated', time());
      }
    }

    return parent::prepareRow($row);
  }

  protected function buildUrl($type, $labelInitials) {
    $baseUrl = 'https://raw.githubusercontent.com/kimberlychristen/Local-Contexts/master/';
    $semiBuiltUrl = $baseUrl . $labelInitials . '/label_' . $labelInitials;
    if ($type == 'img') {
      return $semiBuiltUrl . '.png';
    }
    else if ($type == 'svg') {
      return $semiBuiltUrl . '.svg';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function __toString()
  {
    return 'mukurtu_v3_legacy_tk_community_labels';
  }

  /**
   * {@inheritdoc}
   */
  public function count($refresh = FALSE)
  {
    $count = $this->doCount();
    return $count;
  }

  /**
   * {@inheritdoc}
   */
  public function next()
  {
    SourcePluginBase::next();
  }

  /**
   * {@inheritdoc}
   */
  public function rewind(): void
  {
    $this->getIterator()->rewind();
    $this->next();
  }

  protected function doCount()
  {
    $iterator = $this->getIterator();
    return $iterator instanceof \Countable ? $iterator->count() : iterator_count($this->initializeIterator());
  }
}
