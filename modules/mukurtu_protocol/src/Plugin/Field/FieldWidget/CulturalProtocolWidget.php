<?php

namespace Drupal\mukurtu_protocol\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_protocol\Entity\CommunityInterface;
use Drupal\mukurtu_protocol\Entity\ProtocolInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\mukurtu_protocol\CulturalProtocols;

/**
 * Mukurtu Cultural Protocol widget.
 *
 * @FieldWidget(
 *   id = "cultural_protocol_widget",
 *   label = @Translation("Cultural Protocol widget"),
 *   field_types = {
 *     "cultural_protocol",
 *   }
 * )
 */
class CulturalProtocolWidget extends WidgetBase {
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['third_party_settings'], $container->get('entity_type.manager'));
  }

  /**
   * Sanitizes a string label to display as an option.
   *
   * @param string $label
   *   The label to sanitize.
   */
  protected function sanitizeLabel(&$label) {
    // Allow a limited set of HTML tags.
    $label = FieldFilteredMarkup::create($label);
  }

  protected function getOptions() {
    // Get the protocol options for the user.
    $protocolIds = CulturalProtocols::getProtocolsByUserPermission(['apply protocol']);

    /** @var \Drupal\mukurtu_protocol\Entity\ProtocolInterface[] $protocols */
    $protocols = $this->entityTypeManager->getStorage('protocol')->loadMultiple($protocolIds);

    $multipleCommunities['#title'] = $this->t('Multiple Communities');
    $multipleCommunities['#id'] = 'protocols-with-multiple-communities';
    foreach ($protocols as $protocol) {
      $communities = $protocol->getCommunities();

      // Handle orphaned protocols, even though that shouldn't happen...
      if (empty($communities)) {
        $communityNames = [$this->t('No Communities')];
      } else {
        $communityNames = array_map(fn ($e) => $e->getName(), $communities);
      }

      if (count($communities) > 1) {
        $multipleCommunities['#options'][$protocol->id()]['#title'] = $this->buildProtocolLabel($protocol);
        $multipleCommunities['#options'][$protocol->id()]['#communities'] = $communityNames;
      } else {
        $community = reset($communities);
        if ($community instanceof CommunityInterface) {
          $options[$community->id()]['#title'] = $community->getName();
          $options[$community->id()]['#id'] = $community->id();
          $options[$community->id()]['#options'][$protocol->id()]['#title'] = $this->buildProtocolLabel($protocol);
          $options[$community->id()]['#options'][$protocol->id()]['#communities'] = $communityNames;
        }
      }
    }

    // Put multiple communities at the bottom of the list.
    if (!empty($multipleCommunities['#options'])) {
      $options['multiple'] = $multipleCommunities;
    }

    array_walk_recursive($options, [$this, 'sanitizeLabel']);

    return $options;
  }

  /**
   * Build a label for the protocol name + sharing setting.
   *
   * @param \Drupal\mukurtu_protocol\Entity\ProtocolInterface $protocol
   *   The protocol.
   *
   * @return string
   *   The label string.
   */
  public static function buildProtocolLabel(ProtocolInterface $protocol) {
    $sharingValues = $protocol->getFieldDefinition('field_access_mode')->getSetting('allowed_values');
    return $protocol->getName() . " ({$sharingValues[$protocol->getSharingSetting()]})";
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $protocols = $items[$delta]->protocols ?? "";
    $selectedProtocols = str_replace('|', '', explode(',', $protocols));
    $sharing_setting = $items[$delta]->sharing_setting ?? NULL;

    // Get sharing setting options.
    $sharingOptions = CulturalProtocols::getItemSharingSettingOptions();

    // Build the community/protocol options.
    $protocolsWithCommunityOptions = $this->getOptions();
    $communities = [
      '#type' => 'details',
      '#title' => $this->t("Select cultural protocols to apply to the item."),
      '#open' => TRUE,
    ];
    $c_delta = 0;

    foreach ($protocolsWithCommunityOptions as $communityID => $communityOption) {
      // Build the community headers.
      $communities[$c_delta] = [
        '#type' => 'item',
        '#title' => $communityOption['#title'],
      ];

      foreach ($communityOption['#options'] as $id => $protocolOption) {
        // If a protocol is in multiple communities, show the
        // communities in the checkbox description.
        $description = "";
        if (count($protocolOption['#communities']) > 1) {
          $description = implode(', ', $protocolOption['#communities']);
        }

        // Build the protocol checkboxes.
        $communities[$c_delta]['protocols'][$id] = [
          '#type' => 'checkbox',
          '#title' => $protocolOption['#title'],
          '#description' => $description,
          '#return_value' => $id,
          '#default_value' => in_array($id, $selectedProtocols),
        ];
      }

      $c_delta++;
    }

    $element+= [
      '#type' => 'fieldset',
      '#field_title' => $this->fieldDefinition->getLabel(),
      '#open' => TRUE,
      'sharing_setting' => [
        '#type' => 'radios',
        '#title' => $this->t('Sharing Setting'),
        '#options' => $sharingOptions,
        '#default_value' => $sharing_setting ?? 'all',
      ],
      'protocol_selection' => $communities,
      '#element_validate' => [
        [static::class, 'validate'],
      ],
    ];

    return ['value' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $massagedValues = [];
    foreach ($values as $delta => $value) {
      $subvalue = $value['value'];
      $protocols = [];
      foreach ($subvalue['protocol_selection'] as $community) {
        if (!is_array($community) || !isset($community['protocols'])) {
          continue;
        }
        $protocols = array_merge(array_filter($community['protocols']), $protocols);
      }
      $massagedValues[$delta]['sharing_setting'] = $subvalue['sharing_setting'];
      $massagedValues[$delta]['protocols'] = implode(',', array_map(fn($p) => "|$p|", array_map('trim', $protocols)));
    }

    return $massagedValues;
  }

  public static function validate($element, FormStateInterface $form_state) {
    // Make sure at least one protocol is selected.
    $selectedProtocolCount = 0;
    foreach ($element['protocol_selection'] as $k => $subelement) {
      if (!is_numeric($k) || !isset($subelement['protocols'])) {
        continue;
      }

      foreach ($subelement['protocols'] as $j => $protocolCheckbox) {
        if (is_numeric($j) && isset($protocolCheckbox['#type']) && $protocolCheckbox['#type'] == 'checkbox') {
          if ($protocolCheckbox['#value']) {
            $selectedProtocolCount += 1;
          }
        }
      }
    }

    if(!$selectedProtocolCount) {
      $form_state->setError($element['protocol_selection'], t("At least one Cultural Protocol must be selected."));
    }
  }

}
