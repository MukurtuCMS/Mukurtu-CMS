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

    // The view display may have the field hidden. If it did not build the
    // component, don't put one back.
    if (!array_key_exists('field_all_related_content', $variables['content'])) {
      return;
    }

    // Replacing the component discards the weight the view display assigned,
    // which would float the section to the top of its field group, so carry
    // the configured weight over to the replacement.
    $weight = $variables['content']['field_all_related_content']['#weight'] ?? NULL;

    // Building the grouped, filterable render array here rather than in a
    // formatter, since field_all_related_content is shared by several
    // content types and only Person/Place need this grouping.
    $build = $this->relatedContentGrouper->build($node);

    // The grouper returns cache metadata only when there is nothing visible
    // to list, in which case there is no section to label.
    if (isset($build['#theme'])) {
      // Changing the title of the related content field to "Referenced
      // Content" here because otherwise it's changed for every single content
      // type using this field.
      $build['#title'] = $this->t('Referenced Content');
    }

    if ($weight !== NULL) {
      $build['#weight'] = $weight;
    }

    $variables['content']['field_all_related_content'] = $build;
  }

}
