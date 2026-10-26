<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Plugin\views\field;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders one column of the bulk edit page from the row's entity.
 *
 * Most Mukurtu fields are defined in code and have no Views field handler,
 * so this renders any field by name instead. "protocols", "sharing" and
 * "community" are columns built from the cultural protocol field. Rows are
 * rendered in their own language.
 *
 * @see \Drupal\mukurtu_core\BulkEdit\BulkEditColumns
 */
#[ViewsField('mukurtu_bulk_edit_column')]
class BulkEditColumn extends FieldPluginBase {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EntityRepositoryInterface $entityRepository,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['column'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $this->addAdditionalFields(['langcode' => 'langcode']);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $this->getEntity($values);
    if (!$entity instanceof FieldableEntityInterface) {
      return '';
    }
    $langcode = $this->getValue($values, 'langcode');
    if ($langcode && $entity->hasTranslation($langcode)) {
      $entity = $entity->getTranslation($langcode);
    }

    $column = $this->options['column'];
    if ($entity instanceof CulturalProtocolControlledInterface) {
      switch ($column) {
        case 'protocols':
          return $this->labels($entity->getProtocolEntities());

        case 'sharing':
          return $entity->getSharingSetting() === 'any' ? $this->t('Any') : $this->t('All');

        case 'community':
          return $this->labels($entity->getCommunities());
      }
    }

    if (!$entity->hasField($column) || !$entity->get($column)->access('view')) {
      return '';
    }
    $display = ['label' => 'hidden'];
    // The default moderation state formatter prints the machine name.
    if ($column === 'moderation_state') {
      $display['type'] = 'content_moderation_state';
    }
    return $entity->get($column)->view($display);
  }

  /**
   * Joins entity labels in the current content language.
   */
  protected function labels(array $entities): string {
    return implode(', ', array_map(
      fn($entity) => $this->entityRepository->getTranslationFromContext($entity)->label(),
      $entities,
    ));
  }

}
