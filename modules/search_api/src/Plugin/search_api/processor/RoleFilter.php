<?php

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Utility\Utility;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters out users based on their role.
 */
#[SearchApiProcessor(
  id: 'role_filter',
  label: new TranslatableMarkup('Role filter'),
  description: new TranslatableMarkup('Filters out users based on their role.'),
  stages: [
    'alter_items' => 0,
  ],
)]
class RoleFilter extends ProcessorPluginBase implements PluginFormInterface {

  use PluginFormTrait;

  /**
   * The entity type manager.
   */
  protected ?EntityTypeManagerInterface $entityTypeManager = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->setEntityTypeManager($container->get('entity_type.manager'));
    return $processor;
  }

  /**
   * Can only be enabled for an index that indexes the user entity.
   *
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() == 'user') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  public function getEntityTypeManager(): EntityTypeManagerInterface {
    return $this->entityTypeManager ?: \Drupal::entityTypeManager();
  }

  /**
   * Sets the entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @return $this
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager): static {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'default' => TRUE,
      'roles' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $options = array_map(function (RoleInterface $role) {
      return Utility::escapeHtml($role->label());
    }, $this->getEntityTypeManager()->getStorage('user_role')->loadMultiple());

    $form['default'] = [
      '#type' => 'radios',
      '#title' => $this->t('Which users should be indexed?'),
      '#options' => [
        1 => $this->t('All but those from one of the selected roles'),
        0 => $this->t('Only those from the selected roles'),
      ],
      '#default_value' => (int) $this->configuration['default'],
    ];
    $form['roles'] = [
      '#type' => 'select',
      '#title' => $this->t('Roles'),
      '#options' => $options,
      '#multiple' => TRUE,
      '#size' => min(4, count($options)),
      '#default_value' => array_combine($this->configuration['roles'], $this->configuration['roles']),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $values['default'] = (bool) $values['default'];
    $values['roles'] = array_values(array_filter($values['roles']));
    $form_state->set('values', $values);
    $this->setConfiguration($values);
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items) {
    $selected_roles = array_combine($this->configuration['roles'], $this->configuration['roles']);
    $default = (bool) $this->configuration['default'];

    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item_id => $item) {
      $account = $item->getOriginalObject()->getValue();
      if (!($account instanceof UserInterface)) {
        continue;
      }

      $account_roles = $account->getRoles();
      $account_roles = array_flip($account_roles);
      $has_some_roles = (bool) array_intersect_key($account_roles, $selected_roles);

      // If $default is TRUE, we want to remove those users with at least one of
      // the selected roles. Otherwise, we want to remove those with none.
      if ($default == $has_some_roles) {
        unset($items[$item_id]);
      }
    }
  }

}
