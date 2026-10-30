<?php

namespace Drupal\mukurtu_core\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_core\Hook\RelatedContentPreviewHooks;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Mukurtu core settings for this site.
 */
class MukurtuSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'mukurtu.settings';

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
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $defaultRelatedContentOption = $config->get('mukurtu_related_content_display') ?? 'computed';

    $form['mukurtu_related_content_display'] = [
      '#title' => $this->t('Related Content Display'),
      '#description' => $this->t('Select what content should be displayed for the related content field.'),
      '#type' => 'radios',
      '#default_value' => $defaultRelatedContentOption,
      '#options' => [
        'localonly' => $this->t('Display value of the item\'s related content field as is.'),
        'computed' => $this->t('Display value of the item\'s related content field, but also include content that includes this item as related content.'),
      ],
    ];

    $form['related_content_preview'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Sidebar preview'),
      '#description' => $this->t('Show related content near the top of the sidebar on item pages. The full related content section still appears in the main content.'),
      '#tree' => TRUE,
    ];
    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple(array_keys(RelatedContentPreviewHooks::PLACEMENT));
    foreach ($node_types as $bundle => $node_type) {
      $form['related_content_preview'][$bundle] = [
        '#type' => 'select',
        '#title' => $node_type->label(),
        '#default_value' => RelatedContentPreviewHooks::getMode($config, $bundle),
        '#options' => [
          RelatedContentPreviewHooks::MODE_OFF => $this->t('Off'),
          RelatedContentPreviewHooks::MODE_JUMP_LINK => $this->t('Link to related content'),
          RelatedContentPreviewHooks::MODE_THUMBNAILS => $this->t('Thumbnails in a collapsible list'),
        ],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable(static::SETTINGS);

    // Related content.
    $config->set('mukurtu_related_content_display', $form_state->getValue('mukurtu_related_content_display'));
    $config->set('related_content_preview', $form_state->getValue('related_content_preview') ?? []);

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
