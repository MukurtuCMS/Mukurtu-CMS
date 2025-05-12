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
use Drupal\mukurtu_protocol\Plugin\Field\FieldType\CulturalProtocolItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

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

  /**
   * Build the community/protocol options.
   *
   * @return mixed[]
   *   Array of all community/protocol options, indexed by ID.
   */
  protected function buildOptions($protocol_ids): array {
    $options = [];
    $protocols = [];

    /** @var \Drupal\mukurtu_protocol\Entity\ProtocolInterface[] $protocols */
    if (!empty($protocol_ids)) {
      $protocols = $this->entityTypeManager->getStorage('protocol')->loadMultiple($protocol_ids);
    }

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
    $sharing_setting = $items[$delta]->sharing_setting ?? NULL;
    $current_protocol_ids = $items[$delta]->getProtocolIds();
    $used_protocol_ids = [];

    // Build the community/protocol options.
    $protocolsWithCommunityOptions = $this->buildOptions($items[$delta]->getSettableProtocolIds());
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
          '#default_value' => in_array($id, $current_protocol_ids),
        ];

        // Track the protocols we've rendered for the user, so that we can
        // add any unused protocols (inaccessible references) as hidden values.
        $used_protocol_ids[] = $id;
      }

      $c_delta++;
    }

    $missing_protocol_ids = array_diff($current_protocol_ids, $used_protocol_ids);

    // If the user can only apply a single currently enabled protocol, show
    // them a message warning them that if they remove it, they won't be able
    // to edit the item any more.
    if (count($missing_protocol_ids) > 0 && (count($current_protocol_ids) - count($missing_protocol_ids) == 1)) {
      $warning = $this->t('Warning: If you remove the single remaining cultural protocol you have permission to apply, you will no longer be able to edit this item.');
      $communities['single_applicable_protocol_warning'] = [
        '#type' => 'markup',
        '#markup' => "<div class=\"messages messages--warning\">$warning</div>",
      ];
    }

    // Add any missing protocol IDs as hidden values. Currently we're not
    // letting the user unset these. This may change. If we didn't add these
    // here, they would be lost on submit.
    if (!empty($missing_protocol_ids)) {
      foreach ($missing_protocol_ids as $missing_protocol_id) {
        $communities[0]['protocols'][$missing_protocol_id] = [
          '#type' => 'hidden',
          '#value' => $missing_protocol_id,
        ];
      }

      $inaccessibleMessage = $this->buildInaccessbileProtocolsMessage($missing_protocol_ids);
      $communities['inaccessible_cultural_protocols'] = [
        '#type' => 'markup',
        '#markup' => "<div class=\"messages messages--warning\">$inaccessibleMessage</div>",
      ];
    }

    $element+= [
      '#type' => 'fieldset',
      '#field_title' => $this->fieldDefinition->getLabel(),
      '#open' => TRUE,
      'protocol_selection' => $communities,
      'sharing_setting' => [
        '#type' => 'radios',
        '#title' => $this->t('Sharing Setting'),
        '#options' => $items[$delta]->getSettableSharingOptions(),
        '#default_value' => $sharing_setting ?? 'all',
      ],
      '#element_validate' => [
        [static::class, 'validate'],
      ],
    ];

    return ['value' => $element];
  }

  /**
   * Build the inaccessible protocol message.
   *
   * @param array $missing_protocol_ids
   *  The array of protocol IDs the user does not have access to.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *  The translated markup message.
   */
  protected function buildInaccessbileProtocolsMessage(array $missing_protocol_ids) {
    if (empty($missing_protocol_ids)) {
      return NULL;
    }

    // Fetch the communities for all the protocols the user is not able to see.
    $protocols = $this->entityTypeManager->getStorage('protocol')->loadMultiple($missing_protocol_ids);
    $user_can_view_all_communities = TRUE;
    $visible_missing_protocol_names = [];
    $missing_protocol_communities = [];

    foreach ($protocols as $protocol) {
      if ($protocol->access('view')) {
        $visible_missing_protocol_names[$protocol->id()] = $protocol->getName();
        continue;
      }

      // Check if the user can see the community. If they can, we want to
      // show them the name so they have some idea who to talk to if they need
      // to get these inaccessible protocols changed.
      $communities = $protocol->getCommunities();
      foreach ($communities as $community) {
        if ($community->access('view')) {
          $missing_protocol_communities[$community->id()] = $community->getName();
        } else {
          // Track if there are any communities the user cannot see. We want to
          // change the message to the user if this is the case.
          $user_can_view_all_communities = FALSE;
        }
      }
    }

    if (!empty($visible_missing_protocol_names) && empty($missing_protocol_communities)) {
      return $this->t('There are cultural protocols in use on this item you do not have access to alter: %protocols.', [
        '%protocols' => implode(', ', $visible_missing_protocol_names),
      ]);
    }

    if (!empty($visible_missing_protocol_names) && !empty($missing_protocol_communities)) {
      if ($user_can_view_all_communities) {
        return $this->t('There are cultural protocols in use on this item you do not have access to alter: %protocols. There are cultural protocols in use on this item you do not have access to view, from the following communities: %communities.', [
          '%protocols' => implode(', ', $visible_missing_protocol_names),
          '%communities' => implode(', ', $missing_protocol_communities),
        ]);
      }
      return $this->t('There are cultural protocols in use on this item you do not have access to alter: %protocols. There are cultural protocols in use on this item you do not have access to view, including some from communities you cannot view and others from the following communities: %communities.', [
        '%protocols' => implode(', ', $visible_missing_protocol_names),
        '%communities' => implode(', ', $missing_protocol_communities),
      ]);
    }

    // $visible_missing_protocol_names is empty at this point.
    if (!empty($missing_protocol_communities)) {
      if ($user_can_view_all_communities) {
        return $this->t("There are cultural protocols in use on this item you do not have access to view, from the following communities: %communities", [
          '%communities' => implode(', ', $missing_protocol_communities),
        ]);
      }
      return $this->t("There are cultural protocols in use on this item you do not have access to view, including some from communities you cannot view and others from the following communities: %communities.", [
        '%communities' => implode(', ', $missing_protocol_communities),
      ]);
    }
    return $this->t("There are cultural protocols in use on this item that you do not have access to view.");
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
      $massagedValues[$delta]['protocols'] = CulturalProtocolItem::formatProtocols($protocols);
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
        if (is_numeric($j) && isset($protocolCheckbox['#type']) && ($protocolCheckbox['#type'] == 'checkbox' || $protocolCheckbox['#type'] == 'hidden')) {
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
