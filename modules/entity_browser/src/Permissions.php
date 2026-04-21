<?php

namespace Drupal\entity_browser;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates routes for entity browsers.
 */
class Permissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity browser storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $browserStorage;

  /**
   * Constructs Permissions object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->browserStorage = $entity_type_manager->getStorage('entity_browser');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Dynamically set permissions for entity browsers with routes.
   */
  public function permissions() {
    $permissions = [];
    /** @var \Drupal\entity_browser\EntityBrowserInterface[] $browsers */
    $browsers = $this->browserStorage->loadMultiple();

    foreach ($browsers as $browser) {
      if ($browser->route()) {
        $permissions['access ' . $browser->id() . ' entity browser pages'] = [
          'title' => $this->t('Access @name pages', ['@name' => $browser->label()]),
          'description' => $this->t('Access pages that %browser uses to operate.', ['%browser' => $browser->label()]),
          'dependencies' => [
            $browser->getConfigDependencyKey() => [
              $browser->getConfigDependencyName(),
            ],
          ],
        ];
      }
    }

    return $permissions;
  }

}
