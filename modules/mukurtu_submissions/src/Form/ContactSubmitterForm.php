<?php

namespace Drupal\mukurtu_submissions\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Url;
use Drupal\mukurtu_submissions\Entity\SubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lets a reviewer email a submission's original submitter directly.
 *
 * The submitter usually has no user account (anonymous submissions are
 * owned by the service account for permission plumbing reasons), so core's
 * per-user "contact" tab doesn't apply here - this reads the real contact
 * address straight off the mukurtu_submission entity instead.
 */
class ContactSubmitterForm extends FormBase {

  public function __construct(protected MailManagerInterface $mailManager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('plugin.manager.mail'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_submissions_contact_submitter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $mukurtu_submission = NULL) {
    /** @var \Drupal\mukurtu_submissions\Entity\SubmissionInterface $submission */
    $submission = $mukurtu_submission;
    if (!$submission instanceof SubmissionInterface) {
      return $form;
    }

    $form['#submission'] = $submission;

    $form['recipient'] = [
      '#type' => 'item',
      '#title' => $this->t('To'),
      '#markup' => $this->t('@name &lt;@email&gt;', [
        '@name' => $submission->getSubmitterName(),
        '@email' => $submission->getSubmitterEmail(),
      ]),
    ];

    $target = $submission->getTargetEntity();
    $default_subject = $target ? $this->t('Re: your submission "@title"', ['@title' => $target->label()]) : $this->t('Re: your submission');

    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#default_value' => $default_subject,
      '#required' => TRUE,
    ];

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#rows' => 8,
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      // Fall back to the queue itself if the submitted entity was deleted,
      // so keyboard users always have a link back rather than being stuck
      // relying on the browser's back button.
      '#url' => $target ? $target->toUrl('edit-form') : Url::fromRoute('view.mukurtu_pending_submissions.page'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\mukurtu_submissions\Entity\SubmissionInterface $submission */
    $submission = $form['#submission'];

    $params = [
      'subject' => $form_state->getValue('subject'),
      'message' => $form_state->getValue('message'),
      'reply_to' => $this->currentUser()->getEmail(),
    ];

    $result = $this->mailManager->mail(
      'mukurtu_submissions',
      'contact_submitter',
      $submission->getSubmitterEmail(),
      \Drupal::languageManager()->getDefaultLanguage()->getId(),
      $params,
    );

    if ($result['result']) {
      $this->messenger()->addStatus($this->t('Your message has been sent to @name.', ['@name' => $submission->getSubmitterName()]));
    }
    else {
      $this->messenger()->addError($this->t('Unable to send message.'));
    }

    $target = $submission->getTargetEntity();
    if ($target) {
      $form_state->setRedirectUrl($target->toUrl('edit-form'));
    }
  }

}
