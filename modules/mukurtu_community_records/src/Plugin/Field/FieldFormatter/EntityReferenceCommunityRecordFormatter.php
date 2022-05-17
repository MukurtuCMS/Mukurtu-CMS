<?php

namespace Drupal\mukurtu_community_records\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Field\FieldConfigInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
  protected $entityTypeManager;

  /**
   * Constructs a FormatterBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['label'], $configuration['view_mode'], $configuration['third_party_settings'], $container->get('entity_type.manager'));
  }


  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $view_builder = $this->entityTypeManager->getViewBuilder('node');

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

      $record = $this->entityTypeManager->getStorage($entity_type_id)->load($id);
      $communities = $record->get('field_communities')->referencedEntities() ?? NULL;

      $names = [];
      foreach ($communities as $community) {
       $names[] = $community ? $community->getName() : $record->getTitle();
      }
      $title = implode(', ', $names);

      // Tab.
      $elements['records'][$element_id] = [
        '#type' => 'details',
        '#title' => $title,
        '#summary' => "summary",
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
    $elements['#attached']['library'][] = 'mukurtu_community_records/community-records';


    return [$elements];
  }

}
