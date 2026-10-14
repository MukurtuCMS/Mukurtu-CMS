<?php

declare(strict_types=1);

namespace Drupal\mukurtu_design\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AnnounceCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\mukurtu_design\DesignPalette;
use Drupal\mukurtu_design\PageBackground;
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
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The file usage service.
   */
  protected FileUsageInterface $fileUsage;

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
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->fileUsage = $container->get('file.usage');
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

    // Plain layout wrapper (no #tree) so the fieldset and readout can sit
    // side by side; it does not affect either child's value storage.
    $form['colors_layout'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['mukurtu-design-colors-layout']],
    ];

    // Six colour pickers are a lot of form for something most sites never
    // touch, so this collapses out of the way - unless the site is actually on
    // the custom palette, in which case these are the settings it cares about.
    $form['colors_layout']['colors'] = [
      '#type' => 'details',
      '#title' => $this->t('Custom palette colors'),
      '#description' => $this->t('These colors are used when the "Custom" palette is selected above.'),
      '#open' => $config->get('palette') === 'custom',
      '#tree' => TRUE,
    ];
    foreach ($this->colorLabels() as $key => $label) {
      $form['colors_layout']['colors'][$key] = [
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
    $form['colors_layout'][static::SUMMARY_KEY] = $this->buildContrastSummary(
      $this->contrastAnalyzer->findFailures($colors)
    );

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
      // Set here, not by the caller, so the AJAX callback's direct call to
      // this method (bypassing buildForm()) still gets it: #states has to
      // travel with every render of this element, or a palette switch after
      // an AJAX-replaced readout leaves it stuck visible.
      '#states' => [
        'visible' => [':input[name="palette"]' => ['value' => 'custom']],
      ],
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
