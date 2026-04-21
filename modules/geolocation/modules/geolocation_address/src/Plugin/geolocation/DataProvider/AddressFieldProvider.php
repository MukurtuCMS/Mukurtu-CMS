<?php

namespace Drupal\geolocation_address\Plugin\geolocation\DataProvider;

use CommerceGuys\Addressing\AddressFormat\AddressFormatRepositoryInterface;
use CommerceGuys\Addressing\Country\CountryRepositoryInterface;
use Drupal\address\Plugin\Field\FieldType\AddressItem;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\geolocation\DataProviderBase;
use Drupal\geolocation\DataProviderInterface;
use Drupal\geolocation\GeocoderManager;
use Drupal\views\Plugin\views\field\EntityField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides default address field.
 *
 * @DataProvider(
 *   id = "geolocation_address_field_provider",
 *   name = @Translation("Address Field"),
 *   description = @Translation("Address Field."),
 * )
 */
class AddressFieldProvider extends DataProviderBase implements DataProviderInterface {

  /**
   * Geocoder manager.
   *
   * @var \Drupal\geolocation\GeocoderManager
   */
  protected $geocoderManager = NULL;

  /**
   * Geocoder.
   *
   * @var \Drupal\geolocation\GeocoderInterface
   */
  protected $geocoder = NULL;

  /**
   * The address format repository.
   *
   * @var \CommerceGuys\Addressing\AddressFormat\AddressFormatRepositoryInterface
   */
  protected $addressFormatRepository;

  /**
   * The country repository.
   *
   * @var \CommerceGuys\Addressing\Country\CountryRepositoryInterface
   */
  protected $countryRepository;

  /**
   * AddressFieldProvider constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity type manager.
   * @param \Drupal\geolocation\GeocoderManager $geocoder_manager
   *   Geocoder Manager.
   * @param \CommerceGuys\Addressing\AddressFormat\AddressFormatRepositoryInterface $address_format_repository
   *   The address format repository.
   * @param \CommerceGuys\Addressing\Country\CountryRepositoryInterface $country_repository
   *   The country repository.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityFieldManagerInterface $entity_field_manager, GeocoderManager $geocoder_manager, AddressFormatRepositoryInterface $address_format_repository, CountryRepositoryInterface $country_repository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_field_manager);

    $this->geocoderManager = $geocoder_manager;

    if (!empty($configuration['geocoder'])) {
      $this->geocoder = $this->geocoderManager->createInstance($configuration['geocoder']);
    }

    $this->addressFormatRepository = $address_format_repository;
    $this->countryRepository = $country_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.geolocation.geocoder'),
      $container->get('address.address_format_repository'),
      $container->get('address.country_repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isViewsGeoOption(FieldPluginBase $views_field) {
    if ($views_field instanceof EntityField) {

      /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
      $entityFieldManager = \Drupal::service('entity_field.manager');

      $field_map = $entityFieldManager->getFieldMap();

      if (
        !empty($field_map)
        &&!empty($views_field->configuration['entity_type'])
        && !empty($views_field->configuration['field_name'])
        && !empty($field_map[$views_field->configuration['entity_type']])
        && !empty($field_map[$views_field->configuration['entity_type']][$views_field->configuration['field_name']])
      ) {
        if ($field_map[$views_field->configuration['entity_type']][$views_field->configuration['field_name']]['type'] == 'address') {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isFieldGeoOption(FieldDefinitionInterface $fieldDefinition) {
    return ($fieldDefinition->getType() == 'address');
  }

  /**
   * {@inheritdoc}
   */
  public function getPositionsFromItem(FieldItemInterface $item) {
    if (!($item instanceof AddressItem)) {
      return [];
    }

    if (empty($this->geocoder)) {
      return [];
    }

    $address_format = str_replace(["\r", "\n"], ' ', $this->addressFormatRepository->get($item->getCountryCode())->getFormat());

    $formatted_address = new FormattableMarkup(str_replace('%', ':', $address_format), [
      ':givenName' => $item->getGivenName(),
      ':familyName' => $item->getFamilyName(),
      ':organization' => $item->getOrganization(),
      ':addressLine1' => $item->getAddressLine1(),
      ':addressLine2' => $item->getAddressLine2(),
      ':dependentLocality' => $item->getDependentLocality(),
      ':locality' => $item->getLocality(),
      ':administrativeArea' => $item->getAdministrativeArea(),
      ':postalCode' => $item->getPostalCode(),
      ':sortingCode' => $item->getSortingCode(),
    ]);

    $address = (string) $formatted_address;
    $address = trim($address);
    $address = $address . ' ' . $this->countryRepository->get($item->getCountryCode())->getName();

    $coordinates = $this->geocoder->geocode($address);
    return (!empty($coordinates['location'])) ? [$coordinates['location']] : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents = []) {
    $element = parent::getSettingsForm($settings, $parents);

    $geocoder_options = [];
    foreach ($this->geocoderManager->getDefinitions() as $geocoder_id => $geocoder_definition) {
      if (empty($geocoder_definition['locationCapable'])) {
        continue;
      }
      $geocoder_options[$geocoder_id] = $geocoder_definition['name'];
    }

    if (empty($geocoder_options)) {
      return [
        '#markup' => $this->t('No geocoder option found'),
      ];
    }

    $element['geocoder'] = [
      '#type' => 'select',
      '#title' => $this->t('Geocoder'),
      '#options' => $geocoder_options,
      '#default_value' => empty($settings['geocoder']) ? key($geocoder_options) : $settings['geocoder'],
      '#description' => $this->t('Choose plugin to geocode address into coordinates.'),
      '#weight' => -1,
    ];

    return $element;
  }

}
