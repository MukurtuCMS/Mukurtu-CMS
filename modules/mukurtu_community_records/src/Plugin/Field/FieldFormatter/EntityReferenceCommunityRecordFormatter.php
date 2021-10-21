<?php

namespace Drupal\mukurtu_community_records\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Field\FieldConfigInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'entity_reference_community_record_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "entity_reference_community_record_formatter",
 *   label = @Translation("Mukurtu tabbed entity reference formatter"),
 *   field_types = {
 *     "entity_reference",
 *     "entity_reference_revisions"
 *   }
 * )
 */
class EntityReferenceCommunityRecordFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // TODO: Is this available for DI?
    $view_builder = \Drupal::entityManager()->getViewBuilder('node');

    $entity = $items->getEntity();

    $elements = [];
    $elements['records'] = [
      '#type' => 'horizontal_tabs',
      '#tree' => TRUE,
      '#prefix' => "<div id=\"community-records-{$entity->id()}\">",
      '#suffix' => '</div>',
    ];

    $entity_type_id = $this->getFieldSettings()['target_type'];
    foreach ($items as $delta => $item) {
      $id = $item->getValue()['target_id'];
      $element_id = "record-$id";

      $record = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($id);
      $community = $record->get(MUKURTU_COMMUNITY_FIELD_NAME_COMMUNITY)->referencedEntities()[0] ?? NULL;
      $title = $community ? $community->getTitle() : $record->getTitle();

      // Tab.
      $elements['records'][$element_id] = [
        '#type' => 'details',
        '#title' => $title,
        '#group' => 'records',
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
      ];

      // Body (rendered record).
      $elements['records'][$element_id]['node']['body'] = $view_builder->view($record, 'community_records_single_record');

      $elements['records'][$element_id]['node']['#tree'] = TRUE;
      $elements['records'][$element_id]['node']['#parents'] = [
        'records',
        $element_id,
        'node',
      ];
    }

    $elements['#attached']['library'][] = 'field_group/element.horizontal_tabs';

    return [$elements];
  }

}
