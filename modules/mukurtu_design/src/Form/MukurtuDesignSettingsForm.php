<?php

declare(strict_types=1);

namespace Drupal\mukurtu_design\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUsage\FileUsageInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_design\DesignPalette;
use Drupal\mukurtu_design\HeaderBackground;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Mukurtu design settings for this site.
 */
class MukurtuDesignSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = DesignPalette::SETTINGS;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The file usage service.
   */
  protected FileUsageInterface $fileUsage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->fileUsage = $container->get('file.usage');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_design_settings';
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

    $options = [
      'blue-gold' => $this->t('Blue and gold'),
      'red-bone' => $this->t('Red and bone'),
      'custom' => $this->t('Custom'),
    ];

    $form['palette'] = [
      '#type' => 'mukurtu_palette_radios',
      '#title' => $this->t('Palette'),
      '#options' => $options,
      '#default_value' => $config->get('palette'),
      '#attached' => [
        'library' => ['mukurtu_v4/palettes_demo'],
      ],
    ];

    $form['colors'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Custom palette colors'),
      '#description' => $this->t('These colors are used when the "Custom" palette is selected above.'),
      '#tree' => TRUE,
    ];
    $color_labels = [
      'brand_primary' => $this->t('Brand primary'),
      'brand_primary_dark' => $this->t('Brand Primary Dark'),
      'brand_primary_accent' => $this->t('Brand Primary Accent'),
      'brand_secondary' => $this->t('Brand Secondary'),
      'brand_secondary_dark' => $this->t('Brand Secondary Dark'),
      'brand_secondary_accent' => $this->t('Brand Secondary Accent'),
    ];
    foreach ($color_labels as $key => $label) {
      $form['colors'][$key] = [
        '#type' => 'color',
        '#title' => $label,
        '#default_value' => $config->get("colors.$key"),
      ];
    }

    $form['header'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Header background'),
      '#tree' => TRUE,
    ];

    $form['header']['image'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Background image'),
      '#description' => $this->t('Optional. Shown behind the site logo and main menu. A wide, low image works best, around 2000 pixels across. Leave empty for a plain background.'),
      '#upload_location' => 'public://mukurtu-design/',
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'png jpg jpeg webp svg'],
      ],
      '#default_value' => ($fid = $config->get('header.image')) ? [$fid] : [],
    ];

    $form['header']['show_on_front'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show on the home page'),
      '#description' => $this->t('Turn this off if your home page already uses a full-width hero image.'),
      '#default_value' => $config->get('header.show_on_front'),
    ];

    $form['header']['text_treatment'] = [
      '#type' => 'radios',
      '#title' => $this->t('Header text'),
      '#description' => $this->t('Mukurtu dims or lightens the image behind the header so the logo and menu stay readable.'),
      '#options' => [
        'light' => $this->t('Light text on a darkened image'),
        'dark' => $this->t('Dark text on a lightened image'),
      ],
      '#default_value' => $config->get('header.text_treatment') ?: 'light',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->config(static::SETTINGS);
    $values = $form_state->getValues();

    $config->set('palette', $values['palette']);
    $config->set('colors', $values['colors']);

    $old_fid = $config->get('header.image');
    $new_fid = $values['header']['image'][0] ?? NULL;
    $config->set('header.image', $new_fid ? (int) $new_fid : NULL);
    $config->set('header.show_on_front', (bool) $values['header']['show_on_front']);
    $config->set('header.text_treatment', $values['header']['text_treatment']);
    $config->save();

    $this->updateHeaderImageUsage($old_fid, $new_fid ? (int) $new_fid : NULL);

    if ($values['palette'] === 'custom') {
      \Drupal::classResolver(DesignPalette::class)->generateCustomCss($values['colors']);
    }
  }

  /**
   * Keeps the header image out of the temporary-file garbage collector.
   *
   * The managed_file element leaves an upload temporary, so without this the
   * file is deleted by cron a few hours after it is chosen and the header
   * silently loses its background. Releasing the previous file lets cron clean
   * it up once nothing else refers to it.
   */
  protected function updateHeaderImageUsage(?int $old_fid, ?int $new_fid): void {
    if ($old_fid === $new_fid) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage('file');

    if ($new_fid && ($file = $storage->load($new_fid))) {
      $file->setPermanent();
      $file->save();
      $this->fileUsage->add($file, 'mukurtu_design', 'config', (string) HeaderBackground::USAGE_ID);
    }

    if ($old_fid && ($file = $storage->load($old_fid))) {
      $this->fileUsage->delete($file, 'mukurtu_design', 'config', (string) HeaderBackground::USAGE_ID);
    }
  }

}
