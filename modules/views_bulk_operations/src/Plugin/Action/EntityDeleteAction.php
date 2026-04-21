<?php

declare(strict_types=1);

namespace Drupal\views_bulk_operations\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Delete entity action.
 */
#[Action(
  id: 'views_bulk_operations_delete_entity',
  label: new TranslatableMarkup('Delete selected entities / translations'),
  type: ''
)]
class EntityDeleteAction extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new EntityDeleteAction object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Logger channel for VBO.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly LoggerChannelInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.views_bulk_operations')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?EntityInterface $entity = NULL): TranslatableMarkup {
    if ($entity instanceof TranslatableInterface && !$entity->isDefaultTranslation()) {
      try {
        $untranslated_entity = $entity->getUntranslated();
        $untranslated_entity->removeTranslation($entity->language()->getId());
        $untranslated_entity->save();
      }
      catch (EntityStorageException $e) {
        // If the untranslated entity got deleted before
        // the translated one, an EntityStorageException will be thrown.
        // We can ignore it as the translated entity will be deleted anyway.
      }
      return $this->t('Delete translations');
    }
    else {
      $entity->delete();

      $entityType = $entity->getEntityType();
      $type = $entityType->getLabel();
      if ($entityType->hasKey('bundle')) {
        $type = $bundle = $entity->bundle();
        if ($entity instanceof Node) {
          $type = NodeType::load($bundle)->label();
        }
      }

      $this->logger->notice('@type %label (id: @id) has been deleted in a bulk action from view %view.', [
        '@type' => $type,
        '@id' => $entity->id(),
        '%label' => $entity->label(),
        '%view' => $this->view->id(),
      ]);

      return $this->t('Delete @entity-type entities', [
        '@entity-type' => $entity->getEntityType()->getSingularLabel(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object->access('delete', $account, $return_as_object);
  }

}
