<?php

declare(strict_types=1);

namespace Drupal\mukurtu_design\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AnnounceCommand;
use Drupal\Core\Ajax\ReplaceCommand;
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
   * HTML id of the live contrast readout, and its key in the form array.
   */
  protected const SUMMARY_ID = 'mukurtu-palette-contrast-summary';
  protected const SUMMARY_KEY = 'contrast_summary';

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
        // Re-check as the author picks, rather than only on save. The
        // callback re-runs the same server-side analyzer the save-time
        // validation uses, so there is one implementation of the contrast
        // maths and the live readout cannot drift from the warnings.
        '#ajax' => [
          'callback' => '::contrastSummaryAjaxCallback',
          'wrapper' => static::SUMMARY_ID,
          'event' => 'change',
        ],
      ];
    }

    // Deliberately a sibling of the fieldset, not a child. $form['colors']
    // is #tree'd and submitForm() saves $values['colors'] wholesale, so a
    // display-only element nested inside it risks being persisted into
    // config as if it were a colour.
    $colors = $form_state->getValue('colors') ?? $config->get('colors') ?? [];
    $form[static::SUMMARY_KEY] = $this->buildContrastSummary(
      $this->contrastAnalyzer->findFailures($colors)
    );
    // Only meaningful for the custom palette; the built-in ones are not the
    // author's to change here.
    $form[static::SUMMARY_KEY]['#states'] = [
      'visible' => [':input[name="palette"]' => ['value' => 'custom']],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Builds the live contrast readout shown under the colour inputs.
   *
   * @param array $failures
   *   Failing pairings from PaletteContrastAnalyzer::findFailures().
   *
   * @return array
   *   A render array, always wrapped in the same element id so the AJAX
   *   replace below has something stable to target.
   */
  protected function buildContrastSummary(array $failures): array {
    $summary = [
      '#type' => 'container',
      '#attributes' => ['id' => static::SUMMARY_ID],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Contrast check'),
      ],
    ];

    if (!$failures) {
      $summary['result'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('All checked colour pairings meet WCAG AA.'),
      ];
      return $summary;
    }

    $summary['result'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->contrastSummaryLine($failures),
    ];

    $summary['pairings'] = [
      '#theme' => 'item_list',
      '#items' => array_map(fn($failure) => $this->contrastPairingLine($failure), $failures),
    ];

    return $summary;
  }

  /**
   * The one-line summary of how many pairings need attention.
   */
  protected function contrastSummaryLine(array $failures): TranslatableMarkup {
    return $this->formatPlural(
      count($failures),
      '@count pairing needs attention.',
      '@count pairings need attention.',
    );
  }

  /**
   * One pairing, as the author needs to read it.
   */
  protected function contrastPairingLine(array $failure): TranslatableMarkup {
    return $this->t('@foreground on @background: @ratio:1, needs @required:1', [
      '@foreground' => $this->colorLabel($failure['foreground'], $failure['foreground_value']),
      '@background' => $this->colorLabel($failure['background'], $failure['background_value']),
      '@ratio' => number_format($failure['ratio'], 2),
      '@required' => number_format($failure['required'], 1),
    ]);
  }

  /**
   * AJAX callback: refreshes the readout when a colour changes.
   */
  public function contrastSummaryAjaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    // Analysed once and reused: findFailures() parses the whole compiled
    // stylesheet, so calling it twice per keystroke-ish interaction would
    // be wasteful for no benefit.
    $failures = $this->contrastAnalyzer->findFailures($form_state->getValue('colors') ?? []);

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#' . static::SUMMARY_ID, $this->buildContrastSummary($failures)));

    // The readout is replaced wholesale, so an aria-live region inside it
    // is destroyed and recreated by the very command meant to announce it,
    // and never fires. Announce separately instead - the same reason PR
    // #2245 used AnnounceCommand for the import wizard.
    $response->addCommand(new AnnounceCommand((string) ($failures
      ? $this->contrastSummaryLine($failures)
      : $this->t('All checked colour pairings meet WCAG AA.'))));

    return $response;
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
        '@foreground' => $this->colorLabel($failure['foreground'], $failure['foreground_value']),
        '@background' => $this->colorLabel($failure['background'], $failure['background_value']),
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
   * just edited. Returns the TranslatableMarkup rather than casting it, so
   * the label stays translatable and the t() placeholder it feeds does not
   * double-escape it.
   *
   * @param string $property
   *   The CSS custom property name.
   * @param string|null $value
   *   The resolved colour, used when the property is not one the author
   *   edits here.
   */
  protected function colorLabel(string $property, ?string $value = NULL): string|TranslatableMarkup {
    foreach (DesignPalette::CSS_VAR_MAPPING as $key => $mapped) {
      if ($mapped === $property) {
        return $this->colorLabels()[$key] ?? $property;
      }
    }

    // Not one of the six the author sets, so it has no label they would
    // recognise. The resolved colour is more use to them than the CSS
    // custom property name: "#ffffff on Brand Primary Accent" says
    // something, "--light-text-color on Brand Primary Accent" does not.
    return $value ?? $property;
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
