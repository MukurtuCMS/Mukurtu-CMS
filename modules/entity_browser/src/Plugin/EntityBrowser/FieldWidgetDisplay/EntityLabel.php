<?php

namespace Drupal\entity_browser\Plugin\EntityBrowser\FieldWidgetDisplay;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\entity_browser\FieldWidgetDisplayBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays a label of the entity.
 *
 * @EntityBrowserFieldWidgetDisplay(
 *   id = "label",
 *   label = @Translation("Entity label"),
 *   description = @Translation("Displays entity with a label.")
 * )
 */
class EntityLabel extends FieldWidgetDisplayBase implements ContainerFactoryPluginInterface {

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityRepository = $container->get('entity.repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity) {
    $translation = $this->entityRepository->getTranslationFromContext($entity);

    if (!$translation->access('view label')) {
      $restricted_access_label = new FormattableMarkup('@label @id', [
        '@label' => $entity->getEntityType()->getSingularLabel(),
        '@id' => $entity->id(),
      ]);
      return ['#markup' => $restricted_access_label];
    }

    return ['#markup' => $translation->label()];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return [
      '#prefix' => '<p>',
      '#suffix' => '</p>',
      '#markup' => $this->t('This plugin has no configuration options.'),
    ];
  }

}
