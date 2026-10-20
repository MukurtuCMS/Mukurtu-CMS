<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mukurtu_core\Service\DbIpDownloadService;
use Drupal\mukurtu_core\Service\VisitorsCountryMap;
use Drupal\mukurtu_core\Service\VisitorsLocationBackfill;

/**
 * Keeps visitor geolocation working: database, backfill, status and settings.
 *
 * @see \Drupal\mukurtu_core\Service\DbIpDownloadService
 * @see \Drupal\mukurtu_core\Service\DbIpFallbackGeoIpService
 * @see \Drupal\mukurtu_core\Service\VisitorsLocationBackfill
 */
class GeoIpFallbackHooks {

  use StringTranslationTrait;

  /**
   * Constructs the hook implementations.
   */
  public function __construct(
    private readonly DbIpDownloadService $downloadService,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly VisitorsLocationBackfill $backfill,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Implements hook_modules_installed().
   *
   * The zero-touch happy path: try the download immediately once
   * visitors_geoip is enabled (a fresh site install, or an existing site
   * updating to a mukurtu_core version carrying this feature), rather than
   * waiting for cron to notice. download() never throws, so this cannot
   * fail the install/update batch that triggered it; cron's own staleness
   * check is what retries if this attempt has no network to work with.
   */
  #[Hook('modules_installed')]
  public function modulesInstalled(array $modules): void {
    if (in_array('visitors_geoip', $modules, TRUE)) {
      $this->downloadService->download();
    }
  }

  /**
   * Implements hook_cron().
   *
   * A monthly-ish safety net: installs the database on any site that
   * enabled visitors_geoip before this feature existed, and refreshes it
   * once DB-IP publishes a new one. Deliberately not queued: DB-IP releases
   * monthly, so the at-most-once-a-month blocking download this can trigger
   * (only when cron runs inline on a page request rather than a real cron
   * job -- see core's own update_cron(), which makes the same trade-off
   * fetching Drupal's update XML feed) is accepted rather than adding a
   * queue worker for something this infrequent.
   *
   * Then geolocates visits saved without a location, once, as soon as there
   * is a database to do it with, a time-limited slice per run.
   */
  #[Hook('cron')]
  public function cron(): void {
    if (!$this->moduleHandler->moduleExists('visitors_geoip')) {
      return;
    }
    if ($this->downloadService->isStale()) {
      $this->downloadService->download();
    }
    $this->backfill->startIfReady();
    $this->backfill->process();
  }

  /**
   * Implements hook_runtime_requirements_alter().
   *
   * Rewords visitors_geoip's status report entry. Its own reads "Type:
   * DBIP-City-Lite build: <date>" with a database, and is blank without one,
   * which says nothing about the consequence: without a database, visitor
   * locations are browser-language guesses.
   */
  #[Hook('runtime_requirements_alter')]
  public function runtimeRequirementsAlter(array &$requirements): void {
    if (!isset($requirements['visitors_geoip'])) {
      return;
    }
    $requirement = &$requirements['visitors_geoip'];
    $requirement['title'] = $this->t('Visitor location database');

    $metadata = $this->backfill->databaseMetadata();
    if ($metadata) {
      $value = [
        $this->t('@provider, updated @date', [
          '@provider' => $this->providerName((string) $metadata->databaseType),
          '@date' => $this->dateFormatter->format((int) $metadata->buildEpoch, 'medium'),
        ]),
      ];
      // visitors_geoip raises a warning, and appends this same sentence,
      // when the optional maxminddb extension that speeds up lookups is
      // missing. Kept, in its own words, so the warning still explains
      // itself. visitors_geoip still implements the legacy hook_requirements(),
      // whose integer severities core only converts to the enum after this
      // alter has run, so either form can arrive here.
      $severity = $requirement['severity'] ?? NULL;
      if ($severity === RequirementSeverity::Warning || $severity === RequirementSeverity::Warning->value) {
        $value[] = $this->t('The @extension_geoip PHP extension is not installed.', ['@extension_geoip' => 'maxminddb']);
      }
    }
    else {
      $value = [
        $this->t('No location database is installed, so visitor reports show only countries, based on browser language. The database downloads automatically during cron.'),
      ];
      $requirement['severity'] = RequirementSeverity::Warning;
    }

    $remaining = $this->backfill->remaining();
    if ($remaining !== NULL) {
      $value[] = $this->t('Updating locations for past visits. @count addresses remaining.', ['@count' => $remaining]);
    }

    $requirement['value'] = implode(' ', array_map('strval', $value));
  }

  /**
   * Implements hook_form_FORM_ID_alter() for visitors_geoip_admin_form.
   *
   * Adds the visitor map's city threshold to the GeoIP settings form, and
   * says what happens without a MaxMind key.
   */
  #[Hook('form_visitors_geoip_admin_form_alter')]
  public function formVisitorsGeoipAdminFormAlter(array &$form, FormStateInterface $form_state): void {
    if (isset($form['license'])) {
      $form['license']['#description'] = [
        '#type' => 'inline_template',
        '#template' => '{{ mukurtu }} {{ contrib }}',
        '#context' => [
          'mukurtu' => $this->t('Optional. Without a key, Mukurtu uses the free DB-IP Lite database, which is less accurate.'),
          'contrib' => $form['license']['#description'] ?? '',
        ],
      ];
    }

    $form['mukurtu_city_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum visitors to show a city on the map'),
      '#description' => $this->t("Cities with fewer unique visitors are combined with their region, so individual visitors can't be located."),
      '#min' => 1,
      '#max' => 1000,
      '#required' => TRUE,
      '#default_value' => $this->configFactory->get(VisitorsCountryMap::CONFIG_NAME)->get('city_threshold') ?? VisitorsCountryMap::DEFAULT_CITY_THRESHOLD,
    ];
    // A form with no #submit of its own falls back to ::submitForm, but only
    // while #submit is empty; adding to it without keeping that would stop
    // visitors_geoip's own settings from saving.
    $form['#submit'] = $form['#submit'] ?? ['::submitForm'];
    $form['#submit'][] = [static::class, 'saveCityThreshold'];
  }

  /**
   * Form submission handler: saves the visitor map's city threshold.
   */
  public static function saveCityThreshold(array &$form, FormStateInterface $form_state): void {
    \Drupal::configFactory()->getEditable(VisitorsCountryMap::CONFIG_NAME)
      ->set('city_threshold', (int) $form_state->getValue('mukurtu_city_threshold'))
      ->save();
  }

  /**
   * A human name for a GeoIP database type.
   */
  private function providerName(string $database_type): string {
    if (str_starts_with($database_type, 'DBIP')) {
      return (string) $this->t('DB-IP Lite');
    }
    if (str_starts_with($database_type, 'GeoLite2')) {
      return (string) $this->t('MaxMind GeoLite2');
    }
    return $database_type;
  }

}
