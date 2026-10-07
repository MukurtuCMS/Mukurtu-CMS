<?php

declare(strict_types=1);

namespace Drupal\mukurtu_multilingual\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mukurtu_multilingual\TranslationStatus\TranslationStatusTracker;

/**
 * Exposes the stored translation status to Views.
 *
 * The table joins onto each covered entity type's data table, so the status
 * pages use those entity types' own base tables. That keeps each type's
 * query access rules (node grants, and the media, community and protocol
 * access tags) applied to every row.
 */
class TranslationStatusViewsHooks {

  use StringTranslationTrait;

  /**
   * The data table and ID column of each covered entity type.
   */
  protected const JOINS = [
    'node' => ['node_field_data', 'nid'],
    'media' => ['media_field_data', 'mid'],
    'taxonomy_term' => ['taxonomy_term_field_data', 'tid'],
    'community' => ['community_field_data', 'id'],
    'protocol' => ['protocol_field_data', 'id'],
  ];

  /**
   * Implements hook_views_data().
   */
  #[Hook('views_data')]
  public function viewsData(): array {
    $table = TranslationStatusTracker::TABLE;
    $data[$table]['table']['group'] = $this->t('Translation status');
    foreach (self::JOINS as $entity_type_id => [$base_table, $id_field]) {
      $data[$table]['table']['join'][$base_table] = [
        'left_field' => $id_field,
        'field' => 'entity_id',
        // One row per target language; an entity with no stored status
        // isn't tracked, so it has nothing to show here.
        'type' => 'INNER',
        'extra' => [['field' => 'entity_type', 'value' => $entity_type_id]],
      ];
    }

    $data[$table]['langcode'] = [
      'title' => $this->t('Target language'),
      'help' => $this->t('The language this row reports on.'),
      'field' => ['id' => 'language'],
      'filter' => ['id' => 'language'],
      'sort' => ['id' => 'standard'],
    ];
    $data[$table]['status'] = [
      'title' => $this->t('Status'),
      'help' => $this->t('Translated, partly translated or not translated.'),
      'field' => ['id' => 'mukurtu_translation_status'],
      'filter' => [
        'id' => 'in_operator',
        'options callback' => TranslationStatusTracker::class . '::statusLabels',
      ],
      'sort' => ['id' => 'standard'],
    ];
    $data[$table]['translated_count'] = [
      'title' => $this->t('Fields translated'),
      'help' => $this->t('How many fields with content in the original are translated.'),
      'field' => ['id' => 'mukurtu_translation_status_count'],
      'sort' => ['id' => 'standard'],
    ];
    $data[$table]['translate_link'] = [
      'title' => $this->t('Translate link'),
      'help' => $this->t("A link to the item's translations page."),
      'real field' => 'entity_id',
      'field' => ['id' => 'mukurtu_translation_status_translate'],
    ];
    return $data;
  }

}
