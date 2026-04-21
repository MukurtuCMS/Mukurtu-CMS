<?php

namespace Drupal\search_api_solr\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Utility\Utility;

/**
 * Common formatter settings for SearchApiSolrHighlighted* formatters.
 */
trait SearchApiSolrHighlightedFormatterSettingsTrait {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'prefix' => '<strong>',
      'suffix' => '</strong>',
      'strict' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['prefix'] = [
      '#title' => t('Prefix'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('prefix'),
      '#description' => t('The prefix for a highlighted snippet, usually an opening HTML tag. Ensure that the selected text format for this field allows this tag.'),
    ];

    $form['suffix'] = [
      '#title' => t('Suffix'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('suffix'),
      '#description' => t('The suffix for a highlighted snippet, usually a closing HTML tag. Ensure that the selected text format for this field allows this tag.'),
    ];

    $form['strict'] = [
      '#title' => t('Strict'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('strict'),
      '#description' => t('Be more strict when highlighting. Depending on the Solr configuration details some highlighted fragments could be false positives, for example substring matches etc. Using this strict setting, some redundant or false positives could be avoided. But in general it is recommended to configure Solr correctly.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = t('Highlighting: @prefixtext snippet@suffix',
      [
        '@prefix' => $this->getSetting('prefix'),
        '@suffix' => $this->getSetting('suffix'),
      ]
    );
    return $summary;
  }

  /**
   * Get highlighted field item value based on latest search results.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field item.
   * @param string $value
   *   The filed item value.
   * @param string $langcode
   *   The requested language.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheableMetadata
   *   The cache metadata for the highlighted field item value.
   *
   * @return string
   *   The highlighted field item value.
   */
  protected function getHighlightedValue(FieldItemInterface $item, $value, $langcode, RefinableCacheableDependencyInterface $cacheableMetadata) {
    /** @var \Drupal\search_api\Utility\QueryHelperInterface $queryHelper */
    $queryHelper = \Drupal::service('search_api.query_helper');

    $id_langcode = $item->getLangcode();
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $item->getEntity();
    if ($entity instanceof TranslatableInterface) {
      if ($entity->hasTranslation($langcode)) {
        // In case of a non-translatable field of a translatable entity the
        // item language might not match the search_api ID language.
        $id_langcode = $langcode;
      }
    }
    $item_id = Utility::createCombinedId('entity:' . $entity->getEntityTypeId(), $entity->id() . ':' . $id_langcode);
    $highlighted_keys = [];
    $strict_keys = [];

    $cacheableMetadata->addCacheableDependency($entity);

    foreach ($queryHelper->getAllResults() as $resultSet) {
      $cacheableMetadata->addCacheableDependency($resultSet->getQuery());
      $query_keys = $resultSet->getQuery()->getKeys() ?: [];
      foreach ($query_keys as $index => $key) {
        if (is_numeric($index)) {
          $strict_keys[] = mb_strtolower($key);
        }
      }

      if (empty($strict_keys)) {
        continue;
      }

      foreach ($resultSet->getResultItems() as $resultItem) {
        if ($resultItem->getId() === $item_id) {
          if ($highlighted_keys_tmp = $resultItem->getExtraData('highlighted_keys')) {
            $highlighted_keys = array_merge($highlighted_keys, $highlighted_keys_tmp);
          }
        }
      }
    }

    $highlighted_keys = array_unique($highlighted_keys);

    if ($this->getSetting('strict')) {
      foreach ($highlighted_keys as $index => $key) {
        $key_lower = mb_strtolower($key);

        // Remove keys that are not part of strict keys.
        if (!empty($strict_keys) && !in_array($key_lower, $strict_keys)) {
          $contains = FALSE;
          foreach ($strict_keys as $strict_key) {
            if (str_contains($key_lower, $strict_key)) {
              $contains = TRUE;
              break;
            }
          }

          if (!$contains) {
            unset($highlighted_keys[$index]);
            continue;
          }
        }

        // Remove keys that are part of other keys.
        foreach ($highlighted_keys as $key2) {
          $key2_lower = mb_strtolower($key2);
          if (mb_strlen($key_lower) < mb_strlen($key2_lower) && str_contains($key2_lower, $key_lower)) {
            unset($highlighted_keys[$index]);
            continue 2;
          }
        }
      }
    }

    foreach ($highlighted_keys as $key) {
      $value = preg_replace('/' . preg_quote($key, '/') . '/i', $this->getSetting('prefix') . $key . $this->getSetting('suffix'), $value);
    }

    return $value;
  }

}
