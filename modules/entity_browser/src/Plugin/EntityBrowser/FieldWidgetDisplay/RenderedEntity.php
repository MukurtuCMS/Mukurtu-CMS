<?php

namespace Drupal\entity_browser\Plugin\EntityBrowser\FieldWidgetDisplay;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\entity_browser\FieldWidgetDisplayBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the fully rendered entity.
 *
 * @EntityBrowserFieldWidgetDisplay(
 *   id = "rendered_entity",
 *   label = @Translation("Rendered entity"),
 *   description = @Translation("Displays fully rendered entity.")
 * )
 */
class RenderedEntity extends FieldWidgetDisplayBase implements ContainerFactoryPluginInterface {

  /**
   * Entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityDisplayRepository = $container->get('entity_display.repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity) {
    if (!$entity->access('view')) {
      $parameters = [
        '@label' => $entity->getEntityType()->getSingularLabel(),
        '@id' => $entity->id(),
        '@title' => $entity->label(),
      ];
      $restricted_access_label = $entity->access('view label')
       ? new FormattableMarkup('@label @id (@title)', $parameters)
       : new FormattableMarkup('@label @id', $parameters);
      return ['#markup' => $restricted_access_label];
    }

    $build = $this->entityTypeManager->getViewBuilder($this->configuration['entity_type'])
      ->view($entity, $this->configuration['view_mode']);

    $build['#entity_browser_suppress_contextual'] = TRUE;

    // If the entity is render cached, add another cache key to differentiate
    // it from other places using the same view mode.
    if (!empty($build['#cache']['keys'])) {
      $build['#cache']['keys'][] = 'entity_browser';
    }

    return $build;
  }

  /**
   * Get the label from the view mode.
   *
   * @return string
   *   View mode label.
   */
  public function getViewModeLabel() {
    if (!empty($this->configuration['entity_type']) && !empty($this->configuration['view_mode'])) {
      $view_modes = $this->entityDisplayRepository->getViewModeOptions($this->configuration['entity_type']);
      return $view_modes[$this->configuration['view_mode']];
    }

    return $this->t('Default');
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $options = [];
    foreach ($this->entityDisplayRepository->getViewModeOptions($this->configuration['entity_type']) as $id => $view_mode_label) {
      $options[$id] = $view_mode_label;
    }

    return [
      'view_mode' => [
        '#type' => 'select',
        '#title' => $this->t('View mode'),
        '#description' => $this->t('Select view mode to be used when rendering entities.'),
        '#default_value' => $this->configuration['view_mode'],
        '#options' => $options,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'view_mode' => 'default',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    if ($view_mode = $this->entityTypeManager->getStorage('entity_view_mode')->load($this->configuration['entity_type'] . '.' . $this->configuration['view_mode'])) {
      $dependencies[$view_mode->getConfigDependencyKey()][] = $view_mode->getConfigDependencyName();
    }
    return $dependencies;
  }

}
