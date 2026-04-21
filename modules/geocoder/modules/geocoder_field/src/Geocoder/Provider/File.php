<?php

declare(strict_types=1);

namespace Drupal\geocoder_field\Geocoder\Provider;

use Geocoder\Collection;
use Geocoder\Exception\LogicException;
use Geocoder\Exception\UnsupportedOperation;
use Geocoder\Model\Address;
use Geocoder\Model\AddressCollection;
use Geocoder\Provider\AbstractProvider;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;

/**
 * Provides a file handler to be used by 'file' plugin.
 */
class File extends AbstractProvider implements Provider {

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'file';
  }

  /**
   * {@inheritdoc}
   */
  public function geocodeQuery(GeocodeQuery $query): Collection {
    $filename = $query->getText();
    // Check file type exists and is a JPG (IMAGETYPE_JPEG) before exif_read.
    if (file_exists($filename) && exif_imagetype($filename) == 2 && $exif = @exif_read_data($filename)) {
      if (isset($exif['GPSLatitude']) && isset($exif['GPSLatitudeRef']) && isset($exif['GPSLongitude']) && $exif['GPSLongitudeRef']) {
        $latitude = $this->getGpsExif($exif['GPSLatitude'], $exif['GPSLatitudeRef']);
        $longitude = $this->getGpsExif($exif['GPSLongitude'], $exif['GPSLongitudeRef']);

        $result = Address::createFromArray([
          'providedBy' => $this->getName(),
          'latitude' => $latitude,
          'longitude' => $longitude,
        ]);
        return new AddressCollection([$result]);
      }
    }
    throw new LogicException(sprintf('Could not find geo data in file: "%s".', basename($filename)));
  }

  /**
   * Retrieves the latitude and longitude from exif data.
   *
   * @param array $coordinate
   *   The coordinate.
   * @param string $hemisphere
   *   The hemisphere.
   *
   * @return float
   *   Return value based on coordinate and Hemisphere.
   */
  protected function getGpsExif(array $coordinate, string $hemisphere) {
    for ($i = 0; $i < 3; $i++) {
      $part = explode('/', $coordinate[$i]);

      if (count($part) == 1) {
        $coordinate[$i] = $part[0];
      }
      elseif (count($part) == 2) {
        $coordinate[$i] = floatval($part[0]) / floatval($part[1]);
      }
      else {
        $coordinate[$i] = 0;
      }
    }

    [$degrees, $minutes, $seconds] = $coordinate;
    $sign = ($hemisphere == 'W' || $hemisphere == 'S') ? -1 : 1;
    return $sign * ($degrees + $minutes / 60 + $seconds / 3600);
  }

  /**
   * {@inheritdoc}
   */
  public function reverseQuery(ReverseQuery $query): Collection {
    throw new UnsupportedOperation('The File plugin is not able to do reverse geocoding.');
  }

}
