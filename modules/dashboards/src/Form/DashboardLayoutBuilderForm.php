<?php

namespace Drupal\dashboards\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\dashboards\Entity\Dashboard;
use Drupal\dashboards\Plugin\SectionStorage\UserDashboardSectionStorage;
use Drupal\layout_builder\Form\PreviewToggleTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for dashboard layout builder.
 */
class DashboardLayoutBuilderForm extends EntityForm {
  use PreviewToggleTrait;

  /**
   * Dashboard settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * LayoutBuilder Tempstore.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * Section storage.
   *
   * @var \Drupal\layout_builder\SectionStorageInterface
   */
  protected $sectionStorage;

  /**
   * User data interface.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository'),
      $container->get('user.data'),
      $container->get('current_user'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository, UserDataInterface $user_data, AccountInterface $account, ConfigFactoryInterface $config_factory) {
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->userData = $user_data;
    $this->account = $account;
    $this->config = $config_factory->get('dashboards.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId() {
    return 'dashboards_layout_builder_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, SectionStorageInterface $section_storage = NULL) {
    $form['layout_builder'] = [
      '#type' => 'layout_builder',
      '#section_storage' => $section_storage,
      '#attached' => [
        'library' => ['dashboards/core'],
        'drupalSettings' => [
          'dashboards' => [
            'colormap' => ($this->config->get('colormap')) ? $this->config->get('colormap') : 'summer',
            'alpha' => ($this->config->get('alpha')) ? ($this->config->get('alpha') / 100) : 80,
            'shades' => ($this->config->get('shades')) ? $this->config->get('shades') : 15,
          ],
        ],
      ],
    ];

    $this->sectionStorage = $section_storage;
    $form = parent::buildForm($form, $form_state);

    if ($section_storage instanceof UserDashboardSectionStorage) {
      $form['actions']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset to default'),
        '#weight' => 10,
        '#submit' => ['::resetToDefault'],
      ];
    }

    return $form;
  }

  /**
   * Reset to default layout.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function resetToDefault(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\dashboards\Entity\Dashboard $dashboard */
    $dashboard = $this->sectionStorage->getContextValue(Dashboard::CONTEXT_TYPE);

    // Delete the userData containing the dashboard override.
    $this->userData->delete(
      'dashboards',
      $this->account->id(),
      $dashboard->id()
    );

    // Delete the tempstore so the override form is reloaded as well.
    $this->layoutTempstoreRepository->delete($this->sectionStorage);

    // Create a new trusted redirect response.
    $response = new TrustedRedirectResponse(Url::fromRoute('entity.dashboard.canonical', [
      'dashboard' => $dashboard->id(),
    ])->toString());

    // Set cacheable metadata.
    $metadata = $response->getCacheableMetadata();
    $metadata->setCacheTags($dashboard->getCacheTags());
    $metadata->setCacheContexts($dashboard->getCacheContexts());
    // Also invalidate the cache.
    Cache::invalidateTags($dashboard->getCacheTags());

    // Set the response so we're redirected back to the dashboard.
    $form_state->setResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    // \Drupal\Core\Entity\EntityForm::buildEntity() clones the entity object.
    // Keep it in sync with the one used by the section storage.
    $entity = $this->sectionStorage->getContextValue(Dashboard::CONTEXT_TYPE);
    $entity->isOverridden = TRUE;
    $this->setEntity($this->sectionStorage->getContextValue(Dashboard::CONTEXT_TYPE));
    $entity = parent::buildEntity($form, $form_state);
    $this->sectionStorage->setContextValue(Dashboard::CONTEXT_TYPE, $entity);
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['#attributes']['role'] = 'region';
    $actions['#attributes']['aria-label'] = $this->t('Layout Builder tools');
    $actions['submit']['#value'] = $this->t('Save layout');
    $actions['#weight'] = -1000;

    $actions['discard_changes'] = [
      '#type' => 'submit',
      '#value' => $this->t('Discard changes'),
      '#submit' => ['::redirectOnSubmit'],
      '#redirect' => 'discard_changes',
    ];
    $actions['preview_toggle'] = $this->buildContentPreviewToggle();
    return $actions;
  }

  /**
   * Form submission handler.
   */
  public function redirectOnSubmit(array $form, FormStateInterface $form_state) {
    $form_state->setRedirectUrl($this->sectionStorage->getLayoutBuilderUrl($form_state->getTriggeringElement()['#redirect']));
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $return = $this->sectionStorage->save();
    $this->layoutTempstoreRepository->delete($this->sectionStorage);
    $this->messenger()->addMessage($this->t('The layout has been saved.'));
    $form_state->setRedirectUrl($this->sectionStorage->getRedirectUrl());
    return $return;
  }

  /**
   * Retrieves the section storage object.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface
   *   The section storage for the current form.
   */
  public function getSectionStorage() {
    return $this->sectionStorage;
  }

}
