<?php

declare(strict_types=1);

namespace Drupal\gin_lb\HookHandler;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\gin_lb\GinLayoutBuilderUtility;
use Drupal\gin_lb\Service\ContextValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hook implementation.
 */
class FormAlter implements ContainerInjectionInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The context validator.
   *
   * @var \Drupal\gin_lb\Service\ContextValidatorInterface
   */
  protected ContextValidatorInterface $contextValidator;

  /**
   * The list of form element keys provided by UI Styles.
   *
   * @var array|string[]
   */
  protected array $uiStylesKeys = [
    'ui_styles_wrapper',
    'ui_styles_title',
    'ui_styles',
  ];

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\gin_lb\Service\ContextValidatorInterface $contextValidator
   *   The context validator.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    ContextValidatorInterface $contextValidator,
  ) {
    $this->configFactory = $configFactory;
    $this->contextValidator = $contextValidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    // @phpstan-ignore-next-line
    return new static(
      $container->get('config.factory'),
      $container->get('gin_lb.context_validator')
    );
  }

  /**
   * Alter forms.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   * @param string $form_id
   *   The form ID.
   */
  public function alter(array &$form, FormStateInterface $formState, string $form_id): void {
    if (!$this->contextValidator->isValidTheme()) {
      return;
    }

    $config = $this->configFactory->get('gin_lb.settings');
    $formState->set('gin_lb_settings', $config);

    if ($this->contextValidator->isLayoutBuilderFormId($form_id, $form)) {
      $form['#after_build'][] = [static::class, 'afterBuildAttachGinLbForm'];
      $form['#gin_lb_form'] = TRUE;
      $form['#attributes']['class'][] = 'glb-form';

      if ($config->get('hide_discard_button')) {
        $form['actions']['discard_changes']['#access'] = FALSE;
      }
      if ($config->get('hide_revert_button')) {
        $form['actions']['revert']['#access'] = FALSE;
      }
    }

    if (\str_contains($form_id, 'layout_builder_form')) {
      $form['advanced']['#type'] = 'container';
      if (isset($form['actions']['submit']['#submit'])) {
        $form['actions']['submit']['#submit'][] = [static::class, 'redirectSubmit'];
      }

      // Ensure JS may target only this button.
      if (isset($form['actions']['submit'])) {
        $form['actions']['submit'] = NestedArray::mergeDeepArray([
          $form['actions']['submit'],
          [
            '#attributes' => [
              'class' => [
                'js-glb-button--primary',
              ],
            ],
          ],
        ]);
      }
    }
    if (\in_array($form_id, [
      'layout_builder_add_block',
      'layout_builder_configure_section',
      'layout_builder_remove_section',
      'layout_builder_remove_block',
      'layout_builder_update_block',
    ], TRUE)) {
      $form['#attributes']['class'][] = 'canvas-form';
      if (isset($form['settings'])) {
        $form['settings']['#type'] = 'container';
        $form['settings']['#attributes']['class'][] = 'canvas-form__settings';
      }

      if (isset($form['layout_settings'])) {
        $form['layout_settings']['#type'] = 'container';
        $form['layout_settings']['#attributes']['class'][] = 'canvas-form__settings';
      }

      if (\in_array($form_id, [
        'layout_builder_remove_block',
        'layout_builder_remove_section',
      ], TRUE)) {
        $form['description']['#type'] = 'container';
        $form['description']['#attributes']['class'][] = 'canvas-form__settings';
      }

      $form['actions']['#type'] = 'container';
      $form['actions']['#attributes']['class'][] = 'canvas-form__actions';

      // Layout Builder Lock.
      if (isset($form['layout_builder_lock_wrapper'])) {
        $form['layout_builder_lock_wrapper'] = NestedArray::mergeDeepArray([
          $form['layout_builder_lock_wrapper'],
          [
            '#attributes' => [
              'class' => [
                'canvas-form__actions',
              ],
            ],
          ],
        ]);
      }
      if (isset($form['layout_builder_lock_info'])) {
        $form['layout_builder_lock_info'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => [
              'canvas-form__actions',
            ],
          ],
          'message' => $form['layout_builder_lock_info'],
        ];
      }

      // UI Styles Layout Builder.
      foreach ($this->uiStylesKeys as $key) {
        if (isset($form[$key])) {
          $form[$key] = NestedArray::mergeDeepArray([
            $form[$key],
            [
              '#attributes' => [
                'class' => [
                  'canvas-form__actions',
                ],
              ],
            ],
          ]);
        }
      }
    }
  }

  /**
   * Form element #after_build callback.
   *
   * Layout Builder forms are attached later to setting form.
   *
   * To add suggestion to the attached fields we have to attach lb_form
   * after build.
   */
  public static function afterBuildAttachGinLbForm(array $element, FormStateInterface $form_state): array {
    GinLayoutBuilderUtility::attachGinLbForm($element);
    return $element;
  }

  /**
   * Form submission handler redirect submit.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function redirectSubmit(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Config\ImmutableConfig|null $config */
    $config = $form_state->get('gin_lb_settings');
    if ($config && $config->get('save_behavior') === 'stay') {
      $form_state->setRedirectUrl(Url::fromRoute('<current>'));
    }
  }

}
