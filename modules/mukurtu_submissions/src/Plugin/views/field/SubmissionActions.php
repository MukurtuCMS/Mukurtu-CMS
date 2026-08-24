<?php

namespace Drupal\mukurtu_submissions\Plugin\views\field;

use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Renders "Edit" and "Contact submitter" operation links for a submission.
 *
 * @ViewsField("mukurtu_submission_actions")
 */
class SubmissionActions extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    /** @var \Drupal\mukurtu_submissions\Entity\SubmissionInterface $submission */
    $submission = $this->getEntity($values);
    $links = [];

    $target = $submission->getTargetEntity();
    if ($target && $target->access('update')) {
      $links['edit'] = [
        'title' => $this->t('Edit'),
        'url' => $target->toUrl('edit-form'),
        // A screen reader user scanning the queue's links out of context
        // (e.g. NVDA/JAWS links list) would otherwise hear the same "Edit"
        // label repeated once per row with no way to tell them apart.
        'attributes' => ['aria-label' => $this->t('Edit @title', ['@title' => $target->label()])],
      ];
    }

    $links['contact'] = [
      'title' => $this->t('Contact submitter'),
      'url' => Url::fromRoute('mukurtu_submissions.contact_submitter', ['mukurtu_submission' => $submission->id()]),
      'attributes' => ['aria-label' => $this->t('Contact submitter of @title', ['@title' => $target ? $target->label() : $submission->getSubmitterName()])],
    ];

    return [
      '#type' => 'operations',
      '#links' => $links,
    ];
  }

}
