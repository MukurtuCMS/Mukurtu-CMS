<?php

namespace Drupal\mukurtu_submissions\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Renders a link to the submitted entity.
 *
 * mukurtu_submission points at its target by plain type + id fields rather
 * than an entity_reference field (so it can reference any future
 * submittable entity type), so Views can't resolve a link automatically -
 * this plugin does it by hand.
 *
 * @ViewsField("mukurtu_submission_target")
 */
class SubmissionTarget extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $this->addAdditionalFields(['target_entity_type', 'target_id']);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    /** @var \Drupal\mukurtu_submissions\Entity\SubmissionInterface $submission */
    $submission = $this->getEntity($values);
    $target = $submission->getTargetEntity();
    if (!$target) {
      return $this->t('(deleted)');
    }
    return $target->toLink()->toRenderable();
  }

}
