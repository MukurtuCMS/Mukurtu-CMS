<?php

namespace Drupal\search_api\Plugin\search_api\data_type;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\search_api\Attribute\SearchApiDataType;
use Drupal\search_api\DataType\DataTypePluginBase;
use Drupal\search_api\LoggerTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a date data type.
 */
#[SearchApiDataType(
  id: 'date',
  label: new TranslatableMarkup('Date'),
  description: new TranslatableMarkup('Represents points in time.'),
  default: TRUE,
)]
class DateDataType extends DataTypePluginBase {

  use LoggerTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $plugin->setLogger($container->get('logger.channel.search_api'));

    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    if ((string) $value === '') {
      return NULL;
    }
    if (is_numeric($value)) {
      return (int) $value;
    }

    $timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $date = new DateTimePlus($value, $timezone);
    // Check for invalid datetime strings.
    if ($date->hasErrors()) {
      foreach ($date->getErrors() as $error) {
        $args = [
          '@value' => $value,
          '@error' => $error,
        ];
        $this->getLogger()->warning('Error while parsing date/time value "@value": @error.', $args);
      }
      return NULL;
    }
    // Add in time component if this is a date-only field.
    if (!str_contains($value, ':')) {
      $date->setDefaultDateTime();
    }
    return $date->getTimestamp();
  }

}
