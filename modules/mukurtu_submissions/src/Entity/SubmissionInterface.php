<?php

namespace Drupal\mukurtu_submissions\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for defining Submission entities.
 */
interface SubmissionInterface extends ContentEntityInterface {

  const STATUS_PENDING = 'pending';
  const STATUS_APPROVED = 'approved';
  const STATUS_REJECTED = 'rejected';

  /**
   * Gets the entity type ID of the submitted content.
   *
   * @return string
   */
  public function getTargetEntityTypeId(): string;

  /**
   * Gets the entity ID of the submitted content.
   *
   * @return int|string|null
   */
  public function getTargetId();

  /**
   * Gets the entity object the submission is about, if it still exists.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   */
  public function getTargetEntity();

  /**
   * Gets the submitter's name.
   *
   * @return string|null
   */
  public function getSubmitterName(): ?string;

  /**
   * Gets the submitter's email address.
   *
   * @return string|null
   */
  public function getSubmitterEmail(): ?string;

  /**
   * Gets the submitter's access expectations hint.
   *
   * @return string|null
   */
  public function getAccessExpectations(): ?string;

  /**
   * Gets the review status: pending, approved, or rejected.
   *
   * @return string
   */
  public function getReviewStatus(): string;

  /**
   * Sets the review status.
   *
   * @param string $status
   *   One of the STATUS_* constants.
   *
   * @return $this
   */
  public function setReviewStatus(string $status);

}
