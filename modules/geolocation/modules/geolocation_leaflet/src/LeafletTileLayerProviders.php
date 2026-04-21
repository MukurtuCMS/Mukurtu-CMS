<?php

namespace Drupal\geolocation_leaflet;

/**
 * Provides tile layer providers list.
 */
trait LeafletTileLayerProviders {

  /**
   * List of providers that need registration.
   *
   * @var array
   */
  protected $register = ['Thunderforest', 'MapBox', 'HERE', 'GeoportailFrance'];

  /**
   * Provide a form with the provider options.
   *
   * @param array $settings
   *   An array containing each field value.
   *
   * @return array
   *   An array containing the form with the provider options.
   */
  protected function getProviderOptionsForm(array $settings) {
    $form['Thunderforest']['apikey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#default_value' => isset($settings['Thunderforest']) ? $settings['Thunderforest']['apikey'] : '',
      '#description' => $this->t('Get your @key here <a href="@url">@provider</a>.', [
        '@key' => $this->t('API Key'),
        '@url' => 'https://www.thunderforest.com/',
        '@provider' => 'Thunderforest',
      ]),
    ];
    $form['MapBox']['accessToken'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Token'),
      '#default_value' => isset($settings['MapBox']) ? $settings['MapBox']['accessToken'] : '',
      '#description' => $this->t('Get your @key here <a href="@url">@provider</a>.', [
        '@key' => $this->t('Access Token'),
        '@url' => 'https://www.mapbox.com/',
        '@provider' => 'MapBox',
      ]),
    ];
    $form['HERE'] = [
      'app_id' => [
        '#type' => 'textfield',
        '#title' => $this->t('APP ID'),
        '#default_value' => isset($settings['HERE']) ? $settings['HERE']['app_id'] : '',
      ],
      'app_code' => [
        '#type' => 'textfield',
        '#title' => $this->t('APP Code'),
        '#default_value' => isset($settings['HERE']) ? $settings['HERE']['app_code'] : '',
        '#description' => $this->t('Get your @key here <a href="@url">@provider</a>.', [
          '@key' => $this->t('APP ID and Code'),
          '@url' => 'http://developer.here.com/',
          '@provider' => 'HERE',
        ]),
      ],
    ];
    $form['GeoportailFrance']['apikey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#default_value' => isset($settings['GeoportailFrance']) ? $settings['GeoportailFrance']['apikey'] : '',
      '#description' => $this->t('Get your @key here <a href="@url">@provider</a>.', [
        '@key' => $this->t('API Key'),
        '@url' => 'http://professionnels.ign.fr/ign/contrats',
        '@provider' => 'GeoportailFrance',
      ]),
    ];

    return $form;

  }

  /**
   * Provide some available tile providers.
   *
   * @return array
   *   An array containing tile provider IDs.
   */
  protected function getBaseMaps() {
    return [
      'OpenStreetMap' => [
        'OpenStreetMap Mapnik' => 'OpenStreetMap Mapnik',
        'OpenStreetMap BlackAndWhite' => 'OpenStreetMap BlackAndWhite',
        'OpenStreetMap DE' => 'OpenStreetMap DE',
        'OpenStreetMap CH' => 'OpenStreetMap CH',
        'OpenStreetMap France' => 'OpenStreetMap France',
        'OpenStreetMap HOT' => 'OpenStreetMap HOT',
        'OpenStreetMap BZH' => 'OpenStreetMap BZH',
      ],
      'OpenTopoMap' => [
        'OpenTopoMap' => 'OpenTopoMap',
      ],
      'Thunderforest' => [
        'Thunderforest OpenCycleMap' => 'Thunderforest OpenCycleMap',
        'Thunderforest Transport' => 'Thunderforest Transport',
        'Thunderforest TransportDark' => 'Thunderforest TransportDark',
        'Thunderforest SpinalMap' => 'Thunderforest SpinalMap',
        'Thunderforest Landscape' => 'Thunderforest Landscape',
        'Thunderforest Outdoors' => 'Thunderforest Outdoors',
        'Thunderforest Pioneer' => 'Thunderforest Pioneer',
      ],
      'OpenMapSurfer' => [
        'OpenMapSurfer Roads' => 'OpenMapSurfer Roads',
        'OpenMapSurfer Grayscale' => 'OpenMapSurfer Grayscale',
      ],
      'Hydda' => [
        'Hydda Full' => 'Hydda Full',
        'Hydda Base' => 'Hydda Base',
      ],
      'MapBox' => [
        'MapBox' => 'MapBox',
      ],
      'Stamen' => [
        'Stamen Toner' => 'Stamen Toner',
        'Stamen TonerBackground' => 'Stamen TonerBackground',
        'Stamen TonerLite' => 'Stamen TonerLite',
        'Stamen Watercolor' => 'Stamen Watercolor',
        'Stamen Terrain' => 'Stamen Terrain',
        'Stamen TerrainBackground' => 'Stamen TerrainBackground',
        'Stamen TopOSMRelief' => 'Stamen TopOSMRelief',
      ],
      'Esri' => [
        'Esri WorldStreetMap' => 'Esri WorldStreetMap',
        'Esri DeLorme' => 'Esri DeLorme',
        'Esri WorldTopoMap' => 'Esri WorldTopoMap',
        'Esri WorldImagery' => 'Esri WorldImagery',
        'Esri WorldTerrain' => 'Esri WorldTerrain',
        'Esri WorldShadedRelief' => 'Esri WorldShadedRelief',
        'Esri WorldPhysical' => 'Esri WorldPhysical',
        'Esri OceanBasemap' => 'Esri OceanBasemap',
        'Esri NatGeoWorldMap' => 'Esri NatGeoWorldMap',
        'Esri WorldGrayCanvas' => 'Esri WorldGrayCanvas',
      ],
      'HERE' => [
        'HERE normalDay' => 'HERE normalDay',
        'HERE normalDayCustom' => 'HERE normalDayCustom',
        'HERE normalDayGrey' => 'HERE normalDayGrey',
        'HERE normalDayMobile' => 'HERE normalDayMobile',
        'HERE normalDayGreyMobile' => 'HERE normalDayGreyMobile',
        'HERE normalDayTransit' => 'HERE normalDayTransit',
        'HERE normalDayTransitMobile' => 'HERE normalDayTransitMobile',
        'HERE normalNight' => 'HERE normalNight',
        'HERE normalNightMobile' => 'HERE normalNightMobile',
        'HERE normalNightGrey' => 'HERE normalNightGrey',
        'HERE normalNightGreyMobile' => 'HERE normalNightGreyMobile',
        'HERE normalNightTransit' => 'HERE normalNightTransit',
        'HERE normalNightTransitMobile' => 'HERE normalNightTransitMobile',
        'HERE redcuedDay' => 'HERE redcuedDay',
        'HERE redcuedNight' => 'HERE redcuedNight',
        'HERE basicMap' => 'HERE basicMap',
        'HERE mapLabels' => 'HERE mapLabels',
        'HERE trafficFlow' => 'HERE trafficFlow',
        'HERE carnavDayGrey' => 'HERE carnavDayGrey',
        'HERE hybridDayMobile' => 'HERE hybridDayMobile',
        'HERE hybridDayTransit' => 'HERE hybridDayTransit',
        'HERE hybridDayGrey' => 'HERE hybridDayGrey',
        'HERE pedestrianDay' => 'HERE pedestrianDay',
        'HERE pedestrianNight' => 'HERE pedestrianNight',
        'HERE satelliteDay' => 'HERE satelliteDay',
        'HERE terrainDay' => 'HERE terrainDay',
        'HERE terrainDayMobile' => 'HERE terrainDayMobile',
      ],
      'FreeMapSK' => [
        'FreeMapSK' => 'FreeMapSK',
      ],
      'MtbMap' => [
        'MtbMap' => 'MtbMap',
      ],
      'CartoDB' => [
        'CartoDB Positron' => 'CartoDB Positron',
        'CartoDB PositronNoLabels' => 'CartoDB PositronNoLabels',
        'CartoDB PositronOnlyLabels' => 'CartoDB PositronOnlyLabels',
        'CartoDB DarkMatter' => 'CartoDB DarkMatter',
        'CartoDB DarkMatterNoLabels' => 'CartoDB DarkMatterNoLabels',
        'CartoDB DarkMatterOnlyLabels' => 'CartoDB DarkMatterOnlyLabels',
        'CartoDB Voyager' => 'CartoDB Voyager',
        'CartoDB VoyagerNoLabels' => 'CartoDB VoyagerNoLabels',
        'CartoDB VoyagerOnlyLabels' => 'CartoDB VoyagerOnlyLabels',
        'CartoDB VoyagerLabelsUnder' => 'CartoDB VoyagerLabelsUnder',
      ],
      'HikeBike' => [
        'HikeBike' => 'HikeBike',
        'HikeBike HillShading' => 'HikeBike HillShading',
      ],
      'BasemapAT' => [
        'BasemapAT basemap' => 'BasemapAT basemap',
        'BasemapAT grau' => 'BasemapAT grau',
        'BasemapAT overlay' => 'BasemapAT overlay',
        'BasemapAT highdpi' => 'BasemapAT highdpi',
        'BasemapAT orthofoto' => 'BasemapAT orthofoto',
      ],
      'NLS' => [
        'NLS' => 'NLS',
      ],
      'GeoportailFrance' => [
        'GeoportailFrance parcels' => 'GeoportailFrance parcels',
        'GeoportailFrance ignMaps' => 'GeoportailFrance ignMaps',
        'GeoportailFrance maps' => 'GeoportailFrance maps',
        'GeoportailFrance orthos' => 'GeoportailFrance orthos',
      ],
    ];
  }

}
