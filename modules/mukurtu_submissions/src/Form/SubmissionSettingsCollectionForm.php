<?php

declare(strict_types=1);

namespace Drupal\mukurtu_submissions\Form;

use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The "Submission Forms" collection page - the notify_uids setting plus
 * the existing per-content-type submission settings list, on one page
 * (entity.mukurtu_submission_settings.collection used to be a plain
 * _entity_list route; this replaces it so the two don't need separate
 * paths).
 */
class SubmissionSettingsCollectionForm extends ConfigFormBase {

  /**
   * Role granted to (and revoked from) users as they're added to/removed
   * from notify_uids - see syncNotifyReviewerRoles().
   */
  const REVIEWER_ROLE = 'mukurtu_submission_reviewer';

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EmailValidatorInterface $emailValidator,
    protected EntityRepositoryInterface $entityRepository,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
      $container->get('email.validator'),
      $container->get('entity.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mukurtu_submissions_settings_collection_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['mukurtu_submissions.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('mukurtu_submissions.settings');

    $form['notifications'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Notifications'),
    ];

    // Read-only summary of who currently holds the Submission Reviewer role,
    // so the pre-filled autocomplete below reads as "add another" rather than
    // as a field that failed to clear after saving. notify_uids is the same
    // source syncNotifyReviewerRoles() keeps the role in step with.
    $reviewers = $this->entityTypeManager->getStorage('user')
      ->loadMultiple($config->get('notify_uids') ?: []);
    $names = [];
    foreach ($reviewers as $reviewer) {
      $names[] = $this->entityRepository->getTranslationFromContext($reviewer)->getDisplayName();
    }
    $form['notifications']['current_reviewers'] = [
      '#type' => 'item',
      '#title' => $this->t('Current Submission Reviewers'),
      // #type item renders a <label for> pointing at an id no control owns,
      // so name the block explicitly for assistive tech. The aria-label
      // reuses the visible #title string verbatim.
      '#wrapper_attributes' => [
        'role' => 'group',
        'aria-label' => $this->t('Current Submission Reviewers'),
      ],
      $names ? [
        '#theme' => 'item_list',
        '#items' => $names,
      ] : [
        '#markup' => $this->t('No additional reviewers have been added yet.'),
      ],
    ];

    $form['notifications']['notify_uids'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#selection_handler' => 'mukurtu_submissions_notify_reviewer',
      '#tags' => TRUE,
      '#title' => $this->t('Additional reviewers to notify'),
      '#description' => $this->t('These users will be granted the Submission Reviewer role, and will be notified in addition to Administrators and Mukurtu Managers, whenever a visitor submits new content for review. To publish a submission after assigning it to protocols, a reviewer also needs to be a steward of those protocols.'),
      '#default_value' => $reviewers,
    ];
    $form['notifications']['notify_emails'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additional email addresses to notify'),
      '#description' => $this->t('One email address per line. These addresses will be notified in addition to Administrators, Mukurtu Managers, and any additional reviewers above whenever a visitor submits new content for review.'),
      '#default_value' => implode("\n", $config->get('notify_emails') ?: []),
    ];

    $form['list'] = $this->entityTypeManager->getListBuilder('mukurtu_submission_settings')->render();

    return $form;
  }

  /**
   * Splits the notify_emails textarea into a trimmed, non-empty list of
   * lines - shared by validateForm() (to check validity) and submitForm()
   * (to persist), so the two can never disagree on what counts as an
   * address worth checking.
   */
  protected function extractNotifyEmails(FormStateInterface $form_state): array {
    $lines = preg_split('/[\r\n]+/', (string) $form_state->getValue('notify_emails'), -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_filter(array_map('trim', $lines ?: [])));
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $invalid = array_filter(
      $this->extractNotifyEmails($form_state),
      fn(string $email) => !$this->emailValidator->isValid($email)
    );
    if ($invalid) {
      $form_state->setErrorByName('notify_emails', $this->t('The following are not valid email addresses: @list.', [
        '@list' => implode(', ', $invalid),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('mukurtu_submissions.settings');
    $old_uids = array_map('intval', $config->get('notify_uids') ?: []);
    $new_uids = array_map('intval', array_column($form_state->getValue('notify_uids') ?: [], 'target_id'));

    $config
      ->set('notify_uids', array_values($new_uids))
      ->set('notify_emails', $this->extractNotifyEmails($form_state))
      ->save();

    $this->syncNotifyReviewerRoles($old_uids, $new_uids);

    parent::submitForm($form, $form_state);
  }

  /**
   * Keeps notify_uids membership and the Submission Reviewer role in sync:
   * a user newly added to the list is granted the role, a user removed
   * from it has the role revoked. This never touches any other role a
   * user holds - only this dedicated role is granted/revoked here. If a
   * site admin has also assigned this specific role to a notify_uids
   * member some other way (e.g. directly via /admin/people), removing
   * them from this list will still revoke it.
   */
  protected function syncNotifyReviewerRoles(array $old_uids, array $new_uids): void {
    $user_storage = $this->entityTypeManager->getStorage('user');
    $added = array_diff($new_uids, $old_uids);
    $removed = array_diff($old_uids, $new_uids);

    foreach ($user_storage->loadMultiple($added) as $user) {
      if (!$user->hasRole(static::REVIEWER_ROLE)) {
        $user->addRole(static::REVIEWER_ROLE)->save();
      }
    }
    foreach ($user_storage->loadMultiple($removed) as $user) {
      if ($user->hasRole(static::REVIEWER_ROLE)) {
        $user->removeRole(static::REVIEWER_ROLE)->save();
      }
    }

    if ($added) {
      $this->messenger()->addStatus($this->formatPlural(
        count($added),
        'Granted the Submission Reviewer role to 1 newly added reviewer.',
        'Granted the Submission Reviewer role to @count newly added reviewers.'
      ));
    }
  }

}
