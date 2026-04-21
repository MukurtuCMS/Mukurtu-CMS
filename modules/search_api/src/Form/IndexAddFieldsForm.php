<?php

namespace Drupal\search_api\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;
use Drupal\Core\Url;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Processor\ConfigurablePropertyInterface;
use Drupal\search_api\Processor\ProcessorPropertyInterface;
use Drupal\search_api\Utility\DataTypeHelperInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\search_api\Utility\Utility;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for adding fields to a search index.
 */
class IndexAddFieldsForm extends EntityForm implements TrustedCallbackInterface {

  use UnsavedConfigurationFormTrait;

  /**
   * The fields helper.
   *
   * @var \Drupal\search_api\Utility\FieldsHelperInterface
   */
  protected $fieldsHelper;

  /**
   * The data type helper.
   *
   * @var \Drupal\search_api\Utility\DataTypeHelperInterface|null
   */
  protected $dataTypeHelper;

  /**
   * The index for which the fields are configured.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $entity;

  /**
   * The parameters of the current page request.
   *
   * @var array
   */
  protected $parameters;

  /**
   * List of types that failed to map to a Search API type.
   *
   * The unknown types are the keys and map to arrays of fields that were
   * ignored because they are of this type.
   *
   * @var string[][]
   */
  protected $unmappedFields = [];

  /**
   * The "add field" buttons.
   *
   * Buttons cannot trigger form submits via ajax within a table unless they are
   * moved there in pre-render.
   *
   * @see preRenderForm()
   *
   * @todo Remove this once #3486574 is fixed.
   */
  protected array $addFieldButtons = [];

  /**
   * The "id" attribute of the generated form.
   *
   * @var string
   */
  protected $formIdAttribute;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs an IndexAddFieldsForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\search_api\Utility\FieldsHelperInterface $fields_helper
   *   The fields helper.
   * @param \Drupal\search_api\Utility\DataTypeHelperInterface $data_type_helper
   *   The data type helper.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer to use.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param array $parameters
   *   The parameters for this page request.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FieldsHelperInterface $fields_helper, DataTypeHelperInterface $data_type_helper, RendererInterface $renderer, DateFormatterInterface $date_formatter, MessengerInterface $messenger, array $parameters) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldsHelper = $fields_helper;
    $this->dataTypeHelper = $data_type_helper;
    $this->renderer = $renderer;
    $this->dateFormatter = $date_formatter;
    $this->messenger = $messenger;
    $this->parameters = $parameters;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entity_type_manager = $container->get('entity_type.manager');
    $fields_helper = $container->get('search_api.fields_helper');
    $data_type_helper = $container->get('search_api.data_type_helper');
    $renderer = $container->get('renderer');
    $date_formatter = $container->get('date.formatter');
    $request_stack = $container->get('request_stack');
    $messenger = $container->get('messenger');
    $parameters = $request_stack->getCurrentRequest()->query->all();

    return new static($entity_type_manager, $fields_helper, $data_type_helper, $renderer, $date_formatter, $messenger, $parameters);
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['preRenderForm'];
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_api_index_add_fields';
  }

  /**
   * Retrieves a single page request parameter.
   *
   * @param string $name
   *   The name of the parameter.
   * @param string|null $default
   *   The value to return if the parameter isn't present.
   *
   * @return string|null
   *   The parameter value.
   */
  public function getParameter($name, $default = NULL) {
    return $this->parameters[$name] ?? $default;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $index = $this->entity;

    // Do not allow the form to be cached. See
    // \Drupal\views_ui\ViewEditForm::form().
    $form_state->disableCache();

    $this->checkEntityEditable($form, $index);

    $args['%index'] = $index->label();
    $form['#title'] = $this->t('Add fields to index %index', $args);

    $this->formIdAttribute = Html::getUniqueId($this->getFormId());
    $form['#id'] = $this->formIdAttribute;

    $form['messages'] = [
      '#type' => 'status_messages',
    ];

    $form = $this->buildDatasourcesForm($form, $form_state);

    $form['actions'] = $this->actionsElement($form, $form_state);

    // Log any unmapped types that were encountered.
    if ($this->unmappedFields) {
      $unmapped_fields = [];
      foreach ($this->unmappedFields as $type => $fields) {
        foreach ($fields as $field) {
          $unmapped_fields[] = new FormattableMarkup('@field (type "@type")', [
            '@field' => $field,
            '@type' => $type,
          ]);
        }
      }
      $form['unmapped_types'] = [
        '#type' => 'details',
        '#title' => $this->t('Skipped fields'),
        'fields' => [
          '#theme' => 'item_list',
          '#items' => $unmapped_fields,
          '#prefix' => $this->t('The following fields cannot be indexed since there is no type mapping for them:'),
          '#suffix' => $this->t("If you think one of these fields should be available for indexing, report this in the module's <a href=':url'>issue queue</a>. (Make sure to first search for an existing issue for this field.) Note that entity-valued fields generally can be indexed by either indexing their parent reference field, or their child entity ID field.", [':url' => Url::fromUri('https://www.drupal.org/project/issues/search_api')->toString()]),
        ],
      ];
    }

    if ($this->addFieldButtons) {
      foreach ($this->addFieldButtons as $button) {
        $form['add_field_buttons'][] = $button;
      }
      $form['#pre_render'] = [
        [$this, 'preRenderForm'],
      ];
    }

    return $form;
  }

  /**
   * Builds the data sources portion of the form.
   *
   * Separating this out into a separate method allows other modules to more
   * easily override it.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  protected function buildDatasourcesForm(array $form, FormStateInterface $form_state): array {
    $datasources = [
      '' => NULL,
    ];
    $datasources += $this->entity->getDatasources();
    foreach ($datasources as $datasource_id => $datasource) {
      $item = $this->getDatasourceListItem($datasource);
      if ($item) {
        $form['datasources']['datasource_' . $datasource_id] = $item;
      }
    }
    return $form;
  }

  /**
   * Prerender callback for the form.
   *
   * Moves the buttons into the table now that the AJAX has been prepared since
   * AJAX submit cannot happen as part of a table row.
   *
   * @param array $form
   *   The form.
   *
   * @return array
   *   The processed form.
   *
   * @see https://www.drupal.org/project/drupal/issues/3486574
   */
  public function preRenderForm(array $form): array {
    foreach (Element::children($form['add_field_buttons']) as $key) {
      $button = $form['add_field_buttons'][$key];

      // Check that the table row still exists. Solr for example removes rows in
      // some cases if already added. Other backends allow a field to be added
      // multiple times.
      if (empty($form['datasources'][$button['#datasource_key']]['table']['#rows'][$button['#row_key']])) {
        continue;
      }

      // Move the button into the appropriate table row.
      $form['datasources'][$button['#datasource_key']]['table']['#rows'][$button['#row_key']]['add'] = [
        'data' => $button,
      ];
    }
    unset($form['add_field_buttons']);
    return $form;
  }

  /**
   * Creates a list item for one datasource.
   *
   * @param \Drupal\search_api\Datasource\DatasourceInterface|null $datasource
   *   The datasource, or NULL for general properties.
   *
   * @return array
   *   A render array representing the given datasource and, possibly, its
   *   attached properties.
   */
  protected function getDatasourceListItem(?DatasourceInterface $datasource = NULL) {
    $datasource_id = $datasource?->getPluginId();
    $datasource_id_param = $datasource_id ?: '';
    $properties = $this->entity->getPropertyDefinitions($datasource_id);
    if ($properties) {
      $active_property_path = '';
      $active_datasource = $this->getParameter('datasource');
      if ($active_datasource !== NULL && $active_datasource == $datasource_id_param) {
        $active_property_path = $this->getParameter('property_path', '');
      }

      $base_url = $this->entity->toUrl('add-fields');
      $base_url->setOption('query', ['datasource' => $datasource_id_param]);

      // Add a heading for the section of fields.
      $render = [];
      $render['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $datasource ? $datasource->label() : $this->t('General'),
      ];

      // Build a table of rows containing the available properties.
      $render['table'] = [
        '#type' => 'table',
        '#header' => [
          ['data' => $this->t('Label')],
          ['data' => $this->t('Machine name')],
          ['data' => $this->t('Expand')],
          ['data' => $this->t('Add field')],
        ],
        '#rows' => $this->getPropertiesList($properties, $active_property_path, $base_url, $datasource_id),
        '#empty' => $this->t('No fields are available to add.'),
      ];
      return $render;
    }

    return NULL;
  }

  /**
   * Creates an items list for the given properties.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface[] $properties
   *   The property definitions, keyed by their property names.
   * @param string $active_property_path
   *   The relative property path to the active property.
   * @param \Drupal\Core\Url $base_url
   *   The base URL to which property path parameters should be added for
   *   the navigation links.
   * @param string|null $datasource_id
   *   The datasource ID of the listed properties, or NULL for
   *   datasource-independent properties.
   * @param string $parent_path
   *   (optional) The common property path prefix of the given properties.
   * @param string $label_prefix
   *   (optional) The prefix to use for the labels of created fields.
   * @param int $depth
   *   The current depth in the nesting.
   * @param array $rows
   *   The table rows so far.
   *
   * @return array
   *   An array of table rows representing the given properties and, possibly,
   *   nested properties.
   */
  protected function getPropertiesList(
    array $properties,
    string $active_property_path,
    Url $base_url,
    ?string $datasource_id,
    string $parent_path = '',
    string $label_prefix = '',
    int $depth = 0,
    array $rows = [],
  ): array {
    $active_item = '';
    if ($active_property_path) {
      [$active_item, $active_property_path] = explode(':', $active_property_path, 2) + [1 => ''];
    }

    // Sort the displayed properties alphabetically.
    uasort($properties, [static::class, 'compareFieldLabels']);

    $type_mapping = $this->dataTypeHelper->getFieldTypeMapping();

    $query_base = $base_url->getOption('query');
    foreach ($properties as $key => $property) {
      if ($property instanceof ProcessorPropertyInterface && $property->isHidden()) {
        continue;
      }

      $this_path = $parent_path ? $parent_path . ':' : '';
      $this_path .= $key;

      $label = $property->getLabel();
      $property = $this->fieldsHelper->getInnerProperty($property);

      $can_be_indexed = TRUE;
      $nested_properties = [];
      $parent_child_type = NULL;
      if ($property instanceof ComplexDataDefinitionInterface) {
        $can_be_indexed = FALSE;
        $nested_properties = $this->fieldsHelper->getNestedProperties($property);
        $main_property = $property->getMainPropertyName();
        if ($main_property && isset($nested_properties[$main_property])) {
          $parent_child_type = $property->getDataType() . '.';
          $property = $nested_properties[$main_property];
          $parent_child_type .= $property->getDataType();
          unset($nested_properties[$main_property]);
          $can_be_indexed = TRUE;
        }

        // Don't add the additional "entity" property for entity reference
        // fields which don't target a content entity type.
        if (isset($nested_properties['entity'])) {
          $entity_property = $nested_properties['entity'];
          if ($entity_property instanceof DataReferenceDefinitionInterface) {
            $target = $entity_property->getTargetDefinition();
            if ($target instanceof EntityDataDefinitionInterface) {
              if (!$this->fieldsHelper->isContentEntityType($target->getEntityTypeId())) {
                unset($nested_properties['entity']);
              }
            }
          }
        }

        // Remove hidden properties as well as those that can neither be
        // expanded nor indexed right away so we don't even show an "Expand"
        // link when it won't actually list any nested items.
        foreach ($nested_properties as $nested_key => $nested_property) {
          $nested_property = $this->fieldsHelper->getInnerProperty($nested_property);
          if (
            (
              $nested_property instanceof ProcessorPropertyInterface
              && $nested_property->isHidden()
            )
            || (
              !($nested_property instanceof ComplexDataDefinitionInterface)
              && empty($type_mapping[$nested_property->getDataType()])
            )
          ) {
            unset($nested_properties[$nested_key]);
          }
        }
      }

      // Don't allow indexing of properties with unmapped types. Also, prefer
      // a "parent.child" type mapping (taking into account the parent property
      // for, for example, text fields).
      $type = $property->getDataType();
      if ($parent_child_type && !empty($type_mapping[$parent_child_type])) {
        $type = $parent_child_type;
      }
      elseif (empty($type_mapping[$type])) {
        // Remember the type only if it was not explicitly mapped to FALSE.
        if (!isset($type_mapping[$type])) {
          $this->unmappedFields[$type][] = $label_prefix . $label;
        }
        $can_be_indexed = FALSE;
      }

      // If the property can neither be expanded nor indexed, just skip it.
      if (!$nested_properties && !$can_be_indexed) {
        continue;
      }

      // Create a skeleton of the row array.
      $row = [
        'label' => ['data' => $label],
        'machine_name' => ['data' => Html::escape($this_path)],
        'expand' => ['data' => ''],
        'add' => ['data' => ''],
      ];
      if ($depth > 0) {
        $row['label']['style'] = [
          'style' => 'padding-left: ' . ($depth * 3) . 'rem;',
        ];
      }

      if ($can_be_indexed) {
        // Store the buttons for moving into the table on pre-render. See
        // preRenderForm() for details.
        $this->addFieldButtons[] = [
          '#type' => 'submit',
          '#name' => Utility::createCombinedId($datasource_id, $this_path),
          '#value' => $this->t('Add'),
          '#submit' => ['::addField', '::save'],
          '#attributes' => [
            'class' => [
              'button',
              'button--primary',
              'button--extrasmall',
            ],
          ],
          '#property' => $property,
          '#row_key' => count($rows),
          '#datasource_key' => 'datasource_' . $datasource_id,
          '#prefixed_label' => $label_prefix . $label,
          '#data_type' => $type_mapping[$type],
          '#ajax' => [
            'wrapper' => $this->formIdAttribute,
          ],
        ];
      }

      // This selector is used to maintain focus as the button switches from
      // expand to collapse, so Drupal Core Ajax can find the corresponding
      // matching button.
      $focus_selector = 'js-expand-collapse-focus--' . $key . '--' . $depth;

      // Add the "Expand"/"Collapse" button if applicable.
      $is_active = $key === $active_item;
      if ($nested_properties || $is_active) {
        $link_url = clone $base_url;
        if ($is_active) {
          $link_path = $parent_path;
        }
        else {
          $link_path = $this_path;
          // Auto-expand single-child items.
          if (count($nested_properties) === 1) {
            $nested_property = $this->fieldsHelper->getInnerProperty(reset($nested_properties));
            if ($nested_property instanceof ComplexDataDefinitionInterface) {
              $link_path .= ':' . key($nested_properties);
            }
          }
        }
        $query_base['property_path'] = $link_path;
        $link_url->setOption('query', $query_base);
        $link = [
          '#type' => 'link',
          '#title' => $is_active ? $this->t('Collapse') : $this->t('Expand'),
          '#attributes' => [
            'class' => [
              'button',
              'button--extrasmall',
            ],
            'data-drupal-selector' => [
              $focus_selector,
            ],
          ],
          '#url' => $link_url,
          '#ajax' => [
            'wrapper' => $this->formIdAttribute,
          ],
        ];
        $row['expand']['data'] = $this->renderer->render($link);
      }

      $rows[] = $row;

      // If this is expanded, add rows for all nested properties.
      if ($nested_properties && $is_active) {
        $rows = $this->getPropertiesList(
          $nested_properties,
          $active_property_path,
          $base_url,
          $datasource_id,
          $this_path,
          $label_prefix . $label . ' » ',
          ($depth + 1),
          $rows,
        );
      }
    }

    return $rows;
  }

  /**
   * Compares two properties according to their labels, ignoring case.
   *
   * Used as an uasort() callback in getPropertiesList().
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $a
   *   The first property.
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $b
   *   The second property.
   *
   * @return int
   *   -1, 0 or 1 if the first property should be considered, respectively, less
   *   than, equal to or greater than the second.
   */
  public static function compareFieldLabels(DataDefinitionInterface $a, DataDefinitionInterface $b): int {
    return strnatcasecmp($a->getLabel(), $b->getLabel());
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    return [
      'done' => [
        '#type' => 'link',
        '#title' => $this->t('Done'),
        '#url' => $this->entity->toUrl('fields'),
        '#attributes' => [
          'class' => ['button'],
        ],
      ],
    ];
  }

  /**
   * Form submission handler for adding a new field to the index.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function addField(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    if (!$button) {
      return;
    }

    /** @var \Drupal\Core\TypedData\DataDefinitionInterface $property */
    $property = $button['#property'];

    [$datasource_id, $property_path] = Utility::splitCombinedId($button['#name']);
    $field = $this->fieldsHelper->createFieldFromProperty($this->entity, $property, $datasource_id, $property_path, NULL, $button['#data_type']);
    $field->setLabel($button['#prefixed_label']);
    $this->entity->addField($field);

    if ($property instanceof ConfigurablePropertyInterface) {
      $parameters = [
        'search_api_index' => $this->entity->id(),
        'field_id' => $field->getFieldIdentifier(),
      ];
      $options = [];
      $route = $this->getRequest()->attributes->get('_route');
      if ($route === 'entity.search_api_index.add_fields_ajax') {
        $options['query'] = [
          MainContentViewSubscriber::WRAPPER_FORMAT => 'drupal_ajax',
          'modal_redirect' => 1,
        ];
      }
      $form_state->setRedirect('entity.search_api_index.field_config', $parameters, $options);
    }

    $args['%label'] = $field->getLabel();
    $this->messenger->addStatus($this->t('Field %label was added to the index.', $args));
  }

}
