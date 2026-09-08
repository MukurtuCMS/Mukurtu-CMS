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

    // This page is inherently a one-time, personalized landing page - the
    // same URL is shared by every submission of a given bundle, but each
    // visitor's own request may carry session-specific follow-up behavior
    // (e.g. mukurtu_person's broadcast to a still-open tab after a "create
    // a new person record" quick-create). max-age here covers the render/
    // dynamic-page-cache layer and browser Cache-Control, but core's own
    // anonymous page_cache module deliberately ignores max-age for its own
    // storage decision (see PageCache::storeResponse()'s own comment - it
    // relies on cache tags for invalidation instead, treating responses as
    // effectively permanent otherwise) - only an explicit kill-switch
    // trigger stops it from storing this response. That still isn't
    // enough on its own: the kill switch only affects whether *this*
    // request's response gets written, not whether an earlier, unrelated
    // visitor's response is read back for a later request - so it has to
    // run on every visit, unconditionally, not just when there happens to
    // be something session-specific to attach this time. Confirmed live:
    // an earlier, unrelated visit here got cached, silently breaking the
    // broadcast for every later visitor (including ones who legitimately
    // needed it) until the stale entry was manually cleared.
    \Drupal::service('page_cache_kill_switch')->trigger();
    $build['#cache']['max-age'] = 0;

    return $build;
  }

}
