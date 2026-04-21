<?php

namespace Drupal\geocoder;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\geocoder\Annotation\GeocoderDumper;

/**
 * Provides a plugin manager for geocoder dumpers.
 */
class DumperPluginManager extends GeocoderPluginManagerBase {

  use StringTranslationTrait;

  /**
   * List of fields having a max length.
   *
   * @var string[]
   */
  private $maxLengthFieldTypes = [
    'text',
    'string',
  ];

  /**
   * The country manager service.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  protected $countryManager;

  /**
   * The Drupal messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, CountryManagerInterface $country_manager, MessengerInterface $messenger) {
    parent::__construct('Plugin/Geocoder/Dumper', $namespaces, $module_handler, DumperInterface::class, GeocoderDumper::class);
    $this->alterInfo('geocoder_dumper_info');
    $this->setCacheBackend($cache_backend, 'geocoder_dumper_plugins');
    $this->countryManager = $country_manager;
    $this->messenger = $messenger;
  }

  /**
   * Define an Address field value from a Geojson string.
   *
   * @param string $geojson
   *   The GeoJson place string.
   *
   * @return array
   *   An array of the Address field value.
   */
  public function setAddressFieldFromGeojson($geojson): array {
    $geojson_array = Json::decode($geojson);

    $geojson_array['properties'] += [
      'adminLevels' => '',
      'streetName' => '',
      'streetNumber' => '',
      'postalCode' => '',
      'locality' => '',
    ];

    // Define an administrative_area line1 from adminLevels code or name,
    // if existing.
    // @see https://www.drupal.org/project/geocoder/issues/3510705.
    $administrative_area = '';
    if (!empty($geojson_array['properties']['adminLevels'])) {
      $administrative_area_array = array_shift($geojson_array['properties']['adminLevels']);
      $administrative_area = !empty($administrative_area_array['code']) ? $administrative_area_array['code'] : ($administrative_area_array['name'] ?? NULL);
    }

    // Define the address line1, adding a street number to it, if existing.
    $address_line1 = $geojson_array['properties']['streetName'];
    if (!empty($geojson_array['properties']['streetNumber'])) {
      $address_line1 .= ' ' . $geojson_array['properties']['streetNumber'];
    }

    return [
      'country_code' => $this->setCountryFromGeojson($geojson),
      'address_line1' => $address_line1,
      'postal_code' => $geojson_array['properties']['postalCode'],
      'locality' => $geojson_array['properties']['locality'],
      'administrative_area' => $administrative_area,
    ];
  }

  /**
   * Define a Country value from a Geojson string.
   *
   * @param string $geojson
   *   The GeoJson place string.
   *
   * @return string
   *   A country code.
   */
  public function setCountryFromGeojson($geojson): string {
    $geojson_array = Json::decode($geojson);

    $country_code = isset($geojson_array['properties']['countryCode']) ? strtoupper(substr($geojson_array['properties']['countryCode'], 0, 2)) : '';

    // Some provider (like MapQuest) might not return the 2 digits countryCode
    // but just the country name or a 3 digits code,
    // so try to convert it into countryCode,
    // as it seems to be mandatory in Address Field Entity API.
    if (!array_key_exists($country_code, $this->countryManager->getList()) && isset($geojson_array['properties']['country'])) {
      $country_code = strtoupper(substr($geojson_array['properties']['country'], 0, 2));
    }

    // Allow others modules to adjust the country_code at the end.
    $this->moduleHandler->alter('geocode_country_code', $country_code, $geojson_array);

    return $country_code;
  }

  /**
   * Check|Fix some incompatibility between Dumper output and Field Config.
   *
   * @param string $dumper_result
   *   The Dumper result string.
   * @param \Drupal\geocoder\DumperInterface|\Drupal\Component\Plugin\PluginInspectionInterface $dumper
   *   The Dumper.
   * @param \Drupal\Core\Field\FieldConfigInterface $field_config
   *   The Field Configuration.
   */
  public function fixDumperFieldIncompatibility(&$dumper_result, $dumper, FieldConfigInterface $field_config): void {
    // Fix not UTF-8 encoded result strings.
    // https://stackoverflow.com/questions/6723562/how-to-detect-malformed-utf-8-string-in-php
    if (\is_string($dumper_result)) {
      if (!preg_match('//u', $dumper_result)) {
        $dumper_result = mb_convert_encoding($dumper_result, 'UTF-8', 'ISO-8859-1');
      }
    }

    // If the field is a string|text type check if the result length is
    // compatible with its max_length definition, otherwise truncate it and
    // set | log a warning message.
    if (\in_array($field_config->getType(), $this->maxLengthFieldTypes, TRUE) &&
      \strlen($dumper_result) > $field_config->getFieldStorageDefinition()->getSetting('max_length')) {

      $incompatibility_warning_message = $this->t("The '@field_name' field 'max length' property is not compatible with the chosen '@dumper' dumper.<br>Hence, <b>be aware</b> <u>the dumper output result has been truncated to @max_length chars (max length)</u>.<br> You are advised to change the '@field_name' field definition or chose another compatible dumper.", [
        '@field_name' => $field_config->getLabel(),
        '@dumper' => $dumper->getPluginId(),
        '@max_length' => $field_config->getFieldStorageDefinition()->getSetting('max_length'),
      ]);

      $dumper_result = substr($dumper_result, 0, $field_config->getFieldStorageDefinition()->getSetting('max_length'));

      // Display a max-length incompatibility warning message.
      $this->messenger->addWarning($incompatibility_warning_message);

      // Log the max-length incompatibility.
      $this->getLogger('geocoder')->warning($incompatibility_warning_message);
    }
  }

}
