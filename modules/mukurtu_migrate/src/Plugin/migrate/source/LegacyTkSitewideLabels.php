<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Row;

/**
 *
 * @MigrateSource(
 *   id = "mukurtu_v3_legacy_tk_sitewide_labels"
 * )
 */
class LegacyTkSitewideLabels extends SqlBase
{
  protected $customLabels;

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator()
  {
    $this->customLabels = [];

    // Query to get all the sitewide customized labels.
    $query = $this->select('variable', 'v')
    ->fields('v', ['name', 'value'])
    ->condition('name', 'mukurtu_customized_TK_%', 'LIKE');
    $result = $query->execute()->fetchAll();

    // Check all the sitewide label values.
    foreach ($result as $row) {
      $value = unserialize($row['value']);
      if ($value) {
        $intermediate_label_name = str_replace('mukurtu_customized_', '', $row['name']);
        $cleaned_label_name = str_replace('_', ' ', $intermediate_label_name);

        $labelInitials = $this->getLabelInitials($cleaned_label_name);
        $labelId = 'sitewide_tk_' . $labelInitials;
        $this->customLabels[$labelId] = [
          'id' => $labelId,
          'name' => $cleaned_label_name,
          'default_text' => strip_tags($value),
        ];
      }
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
      'name' => $this->t('Variable name'),
      'value' => $this->t('Variable value'),
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
    $row->setSourceProperty('project_id', 'sitewide_tk');

    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $language = \Drupal::languageManager()->getCurrentLanguage()->getName();
    $row->setSourceProperty('locale', $langcode);
    $row->setSourceProperty('language', $language);

    $initials = $this->getLabelInitials($row->getSourceProperty('name'));
    $row->setSourceProperty('img_url', $this->buildUrl('img', $initials));
    $row->setSourceProperty('svg_url', $this->buildUrl('svg', $initials));
    $row->setSourceProperty('audio_url', '');

    $row->setSourceProperty('community', 'N/A');
    $row->setSourceProperty('type', 'Legacy');
    $row->setSourceProperty('display', 'label');
    $row->setSourceProperty('tk_or_bc', 'tk');

    $row->setSourceProperty('updated', time());

    return parent::prepareRow($row);
  }

  protected function buildUrl($type, $labelInitials)
  {
    $baseUrl = 'https://raw.githubusercontent.com/kimberlychristen/Local-Contexts/master/';
    $semiBuiltUrl = $baseUrl . $labelInitials . '/label_' . $labelInitials;
    if ($type == 'img') {
      return $semiBuiltUrl . '.png';
    } else if ($type == 'svg') {
      return $semiBuiltUrl . '.svg';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function __toString()
  {
    return 'mukurtu_v3_legacy_tk_sitewide_labels';
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
