<?php

declare(strict_types=1);

namespace Drupal\mukurtu_person\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mukurtu_core\Service\RelatedContentGrouper;
use Drupal\mukurtu_person\Entity\Person;

/**
 * Hook implementations for person preprocessing.
 */
final class PersonPreprocessHooks {

  use StringTranslationTrait;

  public function __construct(
    protected RelatedContentGrouper $relatedContentGrouper,
  ) {}

  /**
   * Implements hook_preprocess_HOOK() for node templates.
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    $node = $variables['node'];

    if (!$node instanceof Person || $variables['view_mode'] !== 'full') {
      return;
    }

    // Building the grouped, filterable render array here rather than in a
    // formatter, since field_all_related_content is shared by several
    // content types and only Person/Place need this grouping.
    $variables['content']['field_all_related_content'] = $this->relatedContentGrouper->build($node);
    // Changing the title of the related content field to "Referenced Content" here because otherwise it's
    // changed for every single content type using this field.
    $variables['content']['field_all_related_content']['#title'] = $this->t('Referenced Content');
  }

}
