<?php

declare(strict_types=1);

namespace Drupal\mukurtu_submissions;

use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Tracks entities the current session has legitimately just created via a
 * "quick create" flow (e.g. mukurtu_person's inline "Create a new person
 * record" link on a related-entity field), so a form submitted later in the
 * SAME session can reference one of them even though the entity isn't
 * independently viewable yet - a fresh submission is unpublished with no
 * cultural protocol assigned, so node_access denies everyone but its owner
 * (the submissions service account), which is exactly what core's
 * ValidReferenceConstraintValidator flags as an invalid reference. See
 * PublicSubmissionForm::isReferenceToSessionCreatedEntity().
 *
 * Session-bound (private tempstore, keyed by the session cookie) rather
 * than tied to the mukurtu_submission entity's self-reported submitter
 * info - a different visitor's session cannot forge an entry here, which
 * is the actual security boundary this exception relies on. Deliberately
 * NOT one-time-use like mukurtu_person's own broadcast tempstore key - an
 * entry needs to still be present whenever the visitor eventually submits
 * the form that references it, which may be a separate request from the
 * one that created the entity.
 */
class SessionCreatedEntities {

  const TEMPSTORE_COLLECTION = 'mukurtu_submissions';
  const TEMPSTORE_KEY = 'session_created_entities';

  public function __construct(
    protected PrivateTempStoreFactory $tempStoreFactory,
  ) {}

  /**
   * Records that the current session just created $entity_type_id:$id.
   */
  public function record(string $entity_type_id, string $id): void {
    $store = $this->tempStoreFactory->get(self::TEMPSTORE_COLLECTION);
    $created = $store->get(self::TEMPSTORE_KEY) ?? [];
    $created["$entity_type_id:$id"] = TRUE;
    $store->set(self::TEMPSTORE_KEY, $created);
  }

  /**
   * Whether the current session recorded creating $entity_type_id:$id.
   */
  public function wasCreatedThisSession(string $entity_type_id, string $id): bool {
    $store = $this->tempStoreFactory->get(self::TEMPSTORE_COLLECTION);
    $created = $store->get(self::TEMPSTORE_KEY) ?? [];
    return !empty($created["$entity_type_id:$id"]);
  }

}
