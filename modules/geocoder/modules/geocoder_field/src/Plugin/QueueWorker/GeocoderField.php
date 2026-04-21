<?php

namespace Drupal\geocoder_field\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Geocodes entities.
 *
 * @QueueWorker(
 *   id = \Drupal\geocoder_field\Plugin\QueueWorker\GeocoderField::ID,
 *   title = @Translation("Geocode entities"),
 *   cron = {"time" = 15}
 * )
 */
class GeocoderField extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  const ID = 'geocoder_field';

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The preprocessor manager.
   *
   * @var \Drupal\geocoder_field\PreprocessorPluginManager
   */
  protected $preprocessorManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityRepository = $container->get('entity.repository');
    $instance->preprocessorManager = $container->get('plugin.manager.geocoder.preprocessor');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (empty($data)) {
      return;
    }

    [$entityTypeId, $entityUuid] = $data;
    $entity = $this->entityRepository->loadEntityByUuid($entityTypeId, $entityUuid);

    if (!$entity instanceof ContentEntityInterface) {
      return;
    }

    $fields = $this->preprocessorManager->getOrderedGeocodeFields($entity);
    $result = _geocoder_field_process($entity, $fields);

    // Set drupal static cache so to mark the entity as being saved by the
    // geocoder queue. This is going to stop any further processing in the
    // geocoder_field_entity_presave.
    $cache = &drupal_static('geocoder_queued_entity:' . $entityTypeId . ':' . $entityUuid, FALSE);
    if ($result && !$cache) {
      $cache = TRUE;
      $entity->save();
    }
  }

}
