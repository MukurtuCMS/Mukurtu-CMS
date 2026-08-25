<?php

declare(strict_types=1);

namespace Drupal\mukurtu_submissions;

use Symfony\Component\HttpFoundation\RequestStack;

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
 * Uses the raw HTTP session directly, NOT PrivateTempStore (despite this
 * being exactly the kind of session-scoped data PrivateTempStore exists
 * for) - PrivateTempStore's own storage key is resolved from the CURRENT
 * USER ACCOUNT (PrivateTempStore::getOwner()), not the session itself,
 * and PublicSubmissionForm::submitForm() (like any other module's "quick
 * create" flow saving through it) wraps the actual entity save in
 * AccountSwitcher::switchTo(uid 1) - so a hook_ENTITY_TYPE_insert()
 * implementation calling record() during that save (which is exactly
 * when it needs to) would write under UID 1's identity instead of the
 * real visitor's, and never be found again by that same visitor's own,
 * correctly-anonymous later request. Confirmed live: this was the actual
 * cause of the whole "quick create" flow never working end to end, not
 * just a theoretical risk. A plain session is untouched by account
 * switching - it's tied to the session cookie, not to whichever account
 * \Drupal::currentUser() happens to report at a given moment.
 *
 * A different visitor's session cannot forge an entry here (each has its
 * own, cookie-scoped session), which is the actual security boundary the
 * exception in PublicSubmissionForm relies on. Deliberately NOT one-time-
 * use like mukurtu_person's own broadcast tempstore key - an entry needs
 * to still be present whenever the visitor eventually submits the form
 * that references it, which may be a separate request from the one that
 * created the entity.
 */
class SessionCreatedEntities {

  const SESSION_KEY = 'mukurtu_submissions.session_created_entities';

  public function __construct(
    protected RequestStack $requestStack,
  ) {}

  /**
   * Records that the current session just created $entity_type_id:$id.
   */
  public function record(string $entity_type_id, string $id): void {
    $session = $this->requestStack->getCurrentRequest()->getSession();
    $created = $session->get(self::SESSION_KEY, []);
    $created["$entity_type_id:$id"] = TRUE;
    $session->set(self::SESSION_KEY, $created);
  }

  /**
   * Whether the current session recorded creating $entity_type_id:$id.
   */
  public function wasCreatedThisSession(string $entity_type_id, string $id): bool {
    $session = $this->requestStack->getCurrentRequest()->getSession();
    $created = $session->get(self::SESSION_KEY, []);
    return !empty($created["$entity_type_id:$id"]);
  }

}
