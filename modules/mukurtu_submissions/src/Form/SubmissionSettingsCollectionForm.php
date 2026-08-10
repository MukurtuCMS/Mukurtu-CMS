<?php

declare(strict_types=1);

namespace Drupal\mukurtu_submissions\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The "Submission Forms" collection page - the notify_uids setting plus
 * the existing per-content-type submission settings list, on one page
 * (entity.mukurtu_submission_settings.collection used to be a plain
 * _entity_list route; this replaces it so the two don't need separate
 * paths).
 */
class SubmissionSettingsCollectionForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    protected EntityTypeManagerInterface $entityTypeManager,
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
    $form['notifications']['notify_uids'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#selection_handler' => 'mukurtu_submissions_notify_reviewer',
      '#tags' => TRUE,
      '#title' => $this->t('Additional reviewers to notify'),
      '#description' => $this->t('These users will be notified in addition to Administrators and Mukurtu Managers whenever a visitor submits new content for review.'),
      '#default_value' => User::loadMultiple($config->get('notify_uids') ?: []),
    ];

    $form['list'] = $this->entityTypeManager->getListBuilder('mukurtu_submission_settings')->render();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uids = array_map('intval', array_column($form_state->getValue('notify_uids') ?: [], 'target_id'));

    $this->config('mukurtu_submissions.settings')
      ->set('notify_uids', array_values($uids))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
