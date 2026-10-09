<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirms, then installs Mukurtu Multilingual from the dashboard (#2374).
 */
class EnableMultilingualForm extends ConfirmFormBase {

  /**
   * The module that turns on Mukurtu's multilingual features.
   */
  public const MODULE = 'mukurtu_multilingual';

  public function __construct(
    protected ModuleInstallerInterface $moduleInstaller,
    protected ModuleHandlerInterface $moduleHandler,
    protected LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_installer'),
      $container->get('module_handler'),
      $container->get('logger.factory')->get('mukurtu_core'),
    );
  }

  /**
   * Denies access once multilingual is already on.
   *
   * The route's 'administer modules' requirement decides who may use it.
   */
  public function access(): AccessResultInterface {
    return AccessResult::allowedIf(!$this->moduleHandler->moduleExists(static::MODULE))
      ->addCacheTags(['config:core.extension']);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_core_enable_multilingual';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Enable multilingual features?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This turns on the Mukurtu Multilingual module and the Drupal translation modules it needs. You can then add languages and translate content, configuration, and the site interface. Mukurtu Managers can manage languages and translations, and all signed-in users can translate content they can edit.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Enable multilingual');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('entity.dashboard.canonical', ['dashboard' => 'mukurtu_dashboard']);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $this->moduleInstaller->install([static::MODULE]);
    }
    catch (\Throwable $e) {
      $this->logger->error('Could not enable multilingual features: @message', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Multilingual features could not be enabled. Check the site log for details.'));
      $form_state->setRedirectUrl($this->getCancelUrl());
      return;
    }

    $this->messenger()->addStatus($this->t('Multilingual features are enabled. Add a language to start translating.'));
    $form_state->setRedirect('entity.configurable_language.collection');
  }

}
