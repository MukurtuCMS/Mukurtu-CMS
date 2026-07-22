<?php

declare(strict_types=1);

namespace Drupal\mukurtu_design\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_design\DesignPalette;

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
    $config->save();

    if ($values['palette'] === 'custom') {
      \Drupal::classResolver(DesignPalette::class)->generateCustomCss($values['colors']);
    }
  }

}
