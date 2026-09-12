<?php

declare(strict_types=1);

namespace Drupal\mukurtu_design\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\mukurtu_design\DesignPalette;
use Drupal\mukurtu_design\PageBackground;
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

    // Six colour pickers are a lot of form for something most sites never
    // touch, so this collapses out of the way - unless the site is actually on
    // the custom palette, in which case these are the settings it cares about.
    $form['colors'] = [
      '#type' => 'details',
      '#title' => $this->t('Custom palette colors'),
      '#description' => $this->t('These colors are used when the "Custom" palette is selected above.'),
      '#open' => $config->get('palette') === 'custom',
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

    $form['background'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Page background'),
      '#tree' => TRUE,
    ];

    $form['background']['image'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Background image'),
      '#description' => $this->t('Optional. Shown behind your home page. A large image works best, around 2000 pixels wide. Leave empty for a plain background.'),
      '#upload_location' => 'public://mukurtu-design/',
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'png jpg jpeg webp svg'],
      ],
      '#default_value' => ($fid = $config->get('background.image')) ? [$fid] : [],
    ];

    $form['background']['text_treatment'] = [
      '#type' => 'radios',
      '#title' => $this->t('Page text'),
      '#description' => $this->t('Mukurtu dims or lightens the image so text stays readable.'),
      '#options' => [
        'light' => $this->t('Light text on a darkened image'),
        'dark' => $this->t('Dark text on a lightened image'),
      ],
      '#default_value' => $config->get('background.text_treatment') ?: 'light',
    ];

    $form['header'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Header background'),
      '#tree' => TRUE,
    ];

    $form['header']['image'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Header image'),
      '#description' => $this->t('Optional. Shown behind the logo and menu on every page. A wide, short image works best, around 2000 by 300 pixels. If your home page has its own background image, that one is used there instead.'),
      '#upload_location' => 'public://mukurtu-design/',
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'png jpg jpeg webp svg'],
      ],
      '#default_value' => ($header_fid = $config->get('header.image')) ? [$header_fid] : [],
    ];

    $form['header']['text_treatment'] = [
      '#type' => 'radios',
      '#title' => $this->t('Header text'),
      '#description' => $this->t('Mukurtu dims or lightens the header image so the logo and menu stay readable.'),
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

    $old_fid = $config->get('background.image');
    $new_fid = $values['background']['image'][0] ?? NULL;
    $config->set('background.image', $new_fid ? (int) $new_fid : NULL);
    $config->set('background.text_treatment', $values['background']['text_treatment']);
    $config->save();

    $old_header_fid = $config->get('header.image');
    $new_header_fid = $values['header']['image'][0] ?? NULL;
    $config->set('header.image', $new_header_fid ? (int) $new_header_fid : NULL);
    $config->set('header.text_treatment', $values['header']['text_treatment']);
    $config->save();

    $this->updateBackgroundImageUsage($old_fid, $new_fid ? (int) $new_fid : NULL, PageBackground::USAGE_ID);
    $this->updateBackgroundImageUsage($old_header_fid, $new_header_fid ? (int) $new_header_fid : NULL, PageBackground::HEADER_USAGE_ID);

    if ($values['palette'] === 'custom') {
      \Drupal::classResolver(DesignPalette::class)->generateCustomCss($values['colors']);
    }
  }

  /**
   * Keeps the background image out of the temporary-file garbage collector.
   *
   * The managed_file element leaves an upload temporary, so without this the
   * file is deleted by cron a few hours after it is chosen and the header
   * silently loses its background. Releasing the previous file lets cron clean
   * it up once nothing else refers to it.
   */
  protected function updateBackgroundImageUsage(?int $old_fid, ?int $new_fid, string $usage_id): void {
    if ($old_fid === $new_fid) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage('file');

    if ($new_fid && ($file = $storage->load($new_fid))) {
      $file->setPermanent();
      $file->save();
      $this->fileUsage->add($file, 'mukurtu_design', 'config', $usage_id);
    }

    if ($old_fid && ($file = $storage->load($old_fid))) {
      $this->fileUsage->delete($file, 'mukurtu_design', 'config', $usage_id);
    }
  }

}
