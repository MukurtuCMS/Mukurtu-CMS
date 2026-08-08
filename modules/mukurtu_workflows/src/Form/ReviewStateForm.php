<?php

namespace Drupal\mukurtu_workflows\Form;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Validation\Plugin\Validation\Constraint\NotNullConstraint;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Form for changing a node's moderation state and leaving a review note.
 *
 * Rendered as an embedded form inside the review panel on node view pages.
 * Each allowed transition gets its own submit button labeled with the
 * transition name, so the UI reads as a set of actions rather than a
 * select-then-submit two-step.
 */
class ReviewStateForm extends FormBase {

  public function __construct(
    protected readonly ModerationInformationInterface $moderationInfo,
    protected readonly Connection $database,
    protected readonly DateFormatterInterface $dateFormatter,
    protected readonly StateTransitionValidationInterface $stateTransitionValidation,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('content_moderation.moderation_information'),
      $container->get('database'),
      $container->get('date.formatter'),
      $container->get('content_moderation.state_transition_validation'),
    );
  }

  public function getFormId(): string {
    return 'mukurtu_workflows_review_state_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if ($node === NULL) {
      return $form;
    }

    $workflow = $this->moderationInfo->getWorkflowForEntity($node);
    if ($workflow === NULL) {
      return $form;
    }

    $current_state = $node->get('moderation_state')->value;
    $account = $this->currentUser();

    // Use ProtocolAwareStateTransitionValidation (Mukurtu's override of the
    // content_moderation service) so that OG protocol-level permissions are
    // checked alongside site-level permissions. Direct hasPermission() calls
    // would only see site-level permissions, hiding steward transitions.
    $allowed = [];
    foreach ($this->stateTransitionValidation->getValidTransitions($node, $account) as $transition) {
      $allowed[$transition->to()->id()] = $transition->label();
    }

    if (empty($allowed)) {
      return $form;
    }

    $form['node_id'] = [
      '#type' => 'hidden',
      '#value' => $node->id(),
    ];

    // Store the original state so submitForm() can record from_state without
    // needing to reload the pre-save node.
    $form['from_state'] = [
      '#type' => 'hidden',
      '#value' => $current_state,
    ];

    $form['note'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Review note'),
      '#required' => FALSE,
      '#rows' => 3,
    ];

    // One submit button per allowed transition, labeled with the transition
    // name (e.g. "Publish", "Request revisions", "Submit for review").
    $form['actions'] = ['#type' => 'actions'];
    foreach ($allowed as $to_state_id => $transition_label) {
      $form['actions'][$to_state_id] = [
        '#type' => 'submit',
        '#value' => $transition_label,
        '#mukurtu_to_state' => $to_state_id,
      ];
    }

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $nid = (int) $form_state->getValue('node_id');
    $triggering = $form_state->getTriggeringElement();
    $to_state = $triggering['#mukurtu_to_state'] ?? NULL;
    if ($to_state === NULL) {
      return;
    }

    /** @var \Drupal\node\NodeInterface $node */
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    if ($node === NULL) {
      return;
    }

    // A plain $node->save() never validates on its own - entity-level
    // required-field constraints (e.g. Cultural Protocol) are only enforced
    // by ContentEntityForm::validateForm(), which this quick-publish path
    // never goes through. Validate explicitly, against the state this
    // transition would produce, so it can't silently skip the same
    // requirements the full node edit form already enforces. This has to
    // live in validateForm() rather than submitForm() - setErrorByName()
    // puts Drupal on its normal failed-validation path (form redisplayed
    // with the note text intact, no redirect), whereas failing inside
    // submitForm() would look like success to Form API and silently
    // discard whatever the reviewer had typed into the note field.
    $node->set('moderation_state', $to_state);
    $violations = $node->validate();
    if ($violations->count() > 0) {
      $messages = [];
      foreach ($violations as $violation) {
        $messages[] = (string) $this->describeViolation($node, $violation);
      }
      $form_state->setErrorByName('actions', $this->t('Status not updated: @messages', [
        '@messages' => implode(' ', $messages),
      ]));
    }
  }

  /**
   * Builds a reviewer-facing description of a validation violation.
   *
   * $node->validate()'s own messages are written for developers, not
   * reviewers - "This value should not be null." (the constraint most
   * required-but-empty fields use, including Cultural Protocols on a
   * pending submission) never names which field is actually missing, so
   * a reviewer trying to publish has no way to tell what's wrong. Named
   * per field instead: a plain "@label is/are required." for the common
   * empty-required-field case, or "@label: @message" for anything else,
   * so the underlying message (already reasonably descriptive on its
   * own, e.g. an invalid-reference rejection) still comes through with
   * the field it belongs to.
   */
  protected function describeViolation(NodeInterface $node, ConstraintViolationInterface $violation): \Stringable|string {
    $field_name = explode('.', (string) $violation->getPropertyPath())[0];
    $label = $node->hasField($field_name) ? (string) $node->getFieldDefinition($field_name)->getLabel() : $field_name;

    if ($violation->getConstraint() instanceof NotNullConstraint) {
      $verb = str_ends_with(strtolower($label), 's') ? $this->t('are') : $this->t('is');
      return $this->t('@label @verb required.', ['@label' => $label, '@verb' => $verb]);
    }

    return $this->t('@label: @message', ['@label' => $label, '@message' => $violation->getMessage()]);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $nid = (int) $form_state->getValue('node_id');
    $from_state = $form_state->getValue('from_state');
    $note_text = trim((string) ($form_state->getValue('note') ?? ''));

    // Determine which transition button was clicked.
    $triggering = $form_state->getTriggeringElement();
    $to_state = $triggering['#mukurtu_to_state'] ?? NULL;
    if ($to_state === NULL) {
      return;
    }

    /** @var \Drupal\node\NodeInterface $node */
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    if ($node === NULL) {
      return;
    }

    // Create a minimal new revision that only changes the moderation state.
    // This fires all normal workflow hooks and keeps the revision log clean.
    $node->setNewRevision(TRUE);
    $node->set('moderation_state', $to_state);
    $node->setRevisionLogMessage($this->t('Moderation state changed to @state.', ['@state' => $to_state]));
    $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $node->setRevisionUserId($this->currentUser()->id());
    $node->save();

    // Record the review note regardless of whether text was supplied so the
    // state transition always appears in the review history.
    $this->database->insert('mukurtu_review_note')
      ->fields([
        'nid' => $nid,
        'uid' => (int) $this->currentUser()->id(),
        'created' => \Drupal::time()->getRequestTime(),
        'from_state' => $from_state,
        'to_state' => $to_state,
        'note' => $note_text !== '' ? $note_text : NULL,
      ])
      ->execute();

    $this->messenger()->addStatus($this->t('Status updated.'));
    $form_state->setRedirectUrl($node->toUrl());
  }

}
