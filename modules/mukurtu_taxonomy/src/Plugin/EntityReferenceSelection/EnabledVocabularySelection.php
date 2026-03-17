<?php

declare(strict_types=1);

namespace Drupal\mukurtu_taxonomy\Plugin\EntityReferenceSelection;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\taxonomy\Plugin\EntityReferenceSelection\TermSelection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity reference selection plugin that filters by Mukurtu enabled vocabularies.
 */
#[EntityReferenceSelection(
  id: "mukurtu_enabled_vocabulary:taxonomy_term",
  label: new TranslatableMarkup("Mukurtu Enabled Vocabulary Selection"),
  group: "mukurtu_enabled_vocabulary",
  weight: 1,
  entity_types: ["taxonomy_term"],
)]
class EnabledVocabularySelection extends TermSelection {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, AccountInterface $current_user, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityRepositoryInterface $entity_repository, protected ConfigFactoryInterface $configFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $module_handler, $current_user, $entity_field_manager, $entity_type_bundle_info, $entity_repository);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity.repository'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'vocabulary_config_key' => 'person_records_enabled_vocabularies',
    ] + parent::defaultConfiguration();
  }

  /**
   * Resolves the target bundles from Mukurtu taxonomy settings.
   *
   * @return array
   *   An array of enabled vocabulary machine names.
   */
  protected function resolveTargetBundles(): array {
    $config_key = $this->configuration['vocabulary_config_key'] ?? 'person_records_enabled_vocabularies';
    $bundles = $this->configFactory->get('mukurtu_taxonomy.settings')->get($config_key);
    return is_array($bundles) ? $bundles : [];
  }

  /**
   * Sets the resolved target bundles into the configuration.
   */
  protected function applyResolvedBundles(): void {
    $resolved = $this->resolveTargetBundles();
    // Use NULL (allow all) if no bundles resolved, to avoid locking out
    // all results when config is not yet set. Use the resolved array otherwise.
    $this->configuration['target_bundles'] = !empty($resolved) ? array_combine($resolved, $resolved) : [];
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS'): QueryInterface {
    $this->applyResolvedBundles();
    return parent::buildEntityQuery($match, $match_operator);
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0): array {
    $this->applyResolvedBundles();
    return parent::getReferenceableEntities($match, $match_operator, $limit);
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableNewEntities(array $entities): array {
    $resolved = $this->resolveTargetBundles();
    if (!empty($resolved)) {
      $entities = array_filter($entities, function ($entity) use ($resolved) {
        return in_array($entity->bundle(), $resolved);
      });
    }
    return parent::validateReferenceableNewEntities($entities);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Remove target_bundles elements — bundles are derived from config.
    unset($form['target_bundles']);
    unset($form['target_bundles_update']);

    $form['vocabulary_config_key'] = [
      '#type' => 'select',
      '#title' => $this->t('Vocabulary configuration'),
      '#options' => [
        'person_records_enabled_vocabularies' => $this->t('Person Records Enabled Vocabularies'),
        'place_records_enabled_vocabularies' => $this->t('Place Records Enabled Vocabularies'),
      ],
      '#default_value' => $this->configuration['vocabulary_config_key'],
      '#description' => $this->t('Select which set of enabled vocabularies to use for filtering autocomplete results.'),
    ];

    return $form;
  }

}
