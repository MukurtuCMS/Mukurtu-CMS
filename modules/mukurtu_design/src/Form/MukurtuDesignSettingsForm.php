<?php

declare(strict_types=1);

namespace Drupal\mukurtu_design\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mukurtu_design\DesignPalette;
use Drupal\mukurtu_design\PaletteContrastAnalyzer;
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
   * The custom palette contrast checker.
   *
   * @var \Drupal\mukurtu_design\PaletteContrastAnalyzer
   */
  protected PaletteContrastAnalyzer $contrastAnalyzer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->contrastAnalyzer = $container->get('mukurtu_design.palette_contrast_analyzer');
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
    foreach ($this->colorLabels() as $key => $label) {
      $form['colors'][$key] = [
        '#type' => 'color',
        '#title' => $label,
        '#default_value' => $config->get("colors.$key"),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * The editable custom palette colours, keyed as stored in config.
   *
   * @return array
   *   Labels keyed by config key.
   */
  protected function colorLabels(): array {
    return [
      'brand_primary' => $this->t('Brand primary'),
      'brand_primary_dark' => $this->t('Brand Primary Dark'),
      'brand_primary_accent' => $this->t('Brand Primary Accent'),
      'brand_secondary' => $this->t('Brand Secondary'),
      'brand_secondary_dark' => $this->t('Brand Secondary Dark'),
      'brand_secondary_accent' => $this->t('Brand Secondary Accent'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Only the custom palette can fail this way. The two built-in palettes
    // are checked when they change, not every time someone opens this form.
    if ($form_state->getValue('palette') !== 'custom') {
      return;
    }

    $failures = $this->contrastAnalyzer->findFailures($form_state->getValue('colors') ?? []);

    // Deliberately a warning rather than an error. A community or
    // institution may have a mandated brand palette, and refusing to let
    // them use their own colours is the wrong trade for a tool serving
    // cultural heritage organisations. ATAG 2.0 B.2.2 asks the tool to
    // guide the author and explicitly allows them to proceed.
    foreach ($failures as $failure) {
      $this->messenger()->addWarning($this->t('Contrast warning: @foreground text on @background is @ratio:1. WCAG AA needs @required:1. Affects, for example: @example', [
        '@foreground' => $this->colorLabel($failure['foreground']),
        '@background' => $this->colorLabel($failure['background']),
        '@ratio' => number_format($failure['ratio'], 2),
        '@required' => number_format($failure['required'], 1),
        '@example' => reset($failure['selectors']),
      ]));
    }
  }

  /**
   * Turns a CSS custom property name into something an author recognises.
   *
   * The form's own label where there is one, since that is what the author
   * just edited; otherwise the property name, which at least says where to
   * look. Returns the TranslatableMarkup rather than casting it, so the
   * label stays translatable and the t() placeholder it feeds does not
   * double-escape it.
   */
  protected function colorLabel(string $property): string|TranslatableMarkup {
    foreach (DesignPalette::CSS_VAR_MAPPING as $key => $mapped) {
      if ($mapped === $property) {
        return $this->colorLabels()[$key] ?? $property;
      }
    }
    return $property;
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
