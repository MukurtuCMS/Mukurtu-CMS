<?php

namespace Drupal\mukurtu_submissions\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Landing page shown after a successful public submission.
 */
class ThankYouController extends ControllerBase {

  public function __construct(
    protected EntityTypeBundleInfoInterface $entityBundleInfo,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info'),
    );
  }

  /**
   * Builds the thank-you page.
   */
  public function view(string $entity_type_id, string $bundle): array {
    $matches = $this->entityTypeManager()->getStorage('mukurtu_submission_settings')->loadByProperties([
      'target_entity_type_id' => $entity_type_id,
      'target_bundle' => $bundle,
    ]);
    $settings = reset($matches) ?: NULL;

    $thank_you_text = $settings ? $settings->getThankYouText() : [];
    if (!empty($thank_you_text['value'])) {
      $build = [
        '#type' => 'processed_text',
        '#text' => $thank_you_text['value'],
        '#format' => $thank_you_text['format'] ?? NULL,
      ];
    }
    else {
      $bundle_info = $this->entityBundleInfo->getBundleInfo($entity_type_id);
      $label = $bundle_info[$bundle]['label'] ?? $bundle;
      $build = [
        '#markup' => $this->t('Thank you for your submission. Your @type has been received and will be reviewed by a site administrator before it is published.', ['@type' => $label]),
      ];
    }

    if ($settings) {
      $build['#cache']['tags'] = $settings->getCacheTags();
    }

    return $build;
  }

}
