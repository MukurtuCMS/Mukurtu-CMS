<?php

namespace Drupal\mukurtu_submissions\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Landing page shown after a successful public submission.
 */
class ThankYouController extends ControllerBase {

  public function __construct(protected EntityTypeBundleInfoInterface $entityBundleInfo) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.bundle.info'));
  }

  /**
   * Builds the thank-you page.
   */
  public function view(string $entity_type_id, string $bundle): array {
    $bundle_info = $this->entityBundleInfo->getBundleInfo($entity_type_id);
    $label = $bundle_info[$bundle]['label'] ?? $bundle;

    return [
      '#markup' => $this->t('Thank you for your submission. Your @type has been received and will be reviewed by a site administrator before it is published.', ['@type' => $label]),
    ];
  }

}
