<?php

namespace Drupal\dashboards\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

// cspell:ignore bluered cdom cubehelix freesurface viridis colormaps
// Ignore colormaps custom keys and variable names (mostly in 'buildForm').
/**
 * Form for dashboard settings.
 */
class DashboardsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'dashboards.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dashboards_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('dashboards.settings');
    $form['colormap'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose a colormap'),
      '#options' => [
        'jet' => $this->t('jet'),
        'hsv' => $this->t('hsv'),
        'hot' => $this->t('hot'),
        'spring' => $this->t('spring'),
        'summer' => $this->t('summer'),
        'autumn' => $this->t('autumn'),
        'winter' => $this->t('winter'),
        'bone' => $this->t('bone'),
        'copper' => $this->t('copper'),
        'greys' => $this->t('greys'),
        'YlGnBu' => $this->t('YlGnBu'),
        'greens' => $this->t('greens'),
        'YlOrRd' => $this->t('YlOrRd'),
        'bluered' => $this->t('bluered'),
        'RdBu' => $this->t('RdBu'),
        'picnic' => $this->t('picnic'),
        'rainbow' => $this->t('rainbow'),
        'portland' => $this->t('portland'),
        'blackbody' => $this->t('blackbody'),
        'earth' => $this->t('earth'),
        'electric' => $this->t('electric'),
        'viridis' => $this->t('viridis'),
        'inferno' => $this->t('inferno'),
        'magma' => $this->t('magma'),
        'plasma' => $this->t('plasma'),
        'warm' => $this->t('warm'),
        'cool' => $this->t('cool'),
        'rainbow-soft' => $this->t('rainbow-soft'),
        'bathymetry' => $this->t('bathymetry'),
        'cdom' => $this->t('cdom'),
        'chlorophyll' => $this->t('chlorophyll'),
        'density' => $this->t('density'),
        'freesurface-blue' => $this->t('freesurface-blue'),
        'freesurface-red' => $this->t('freesurface-red'),
        'oxygen' => $this->t('oxygen'),
        'par' => $this->t('par'),
        'phase' => $this->t('phase'),
        'salinity' => $this->t('salinity'),
        'turbidity' => $this->t('turbidity'),
        'velocity-blue' => $this->t('velocity-blue'),
        'velocity-green' => $this->t('velocity-green'),
        'cubehelix' => $this->t('cubehelix'),
      ],
      '#size' => 1,
      '#default_value' => $config->get('colormap'),
      '#description' => $this->t('See colormaps <a href="@url">here</a>.', ['@url' => 'https://github.com/bpostlethwaite/colormap#readme']),
    ];
    $form['alpha'] = [
      '#type' => 'number',
      '#title' => $this->t('Transparency'),
      '#default_value' => ($config->get('alpha')) ? $config->get('alpha') : 100,
      '#description' => $this->t('Transparency in percent.'),
      '#min' => 20,
      '#max' => 100,
    ];
    $form['shades'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of colors in map'),
      '#default_value' => ($config->get('shades')) ? $config->get('shades') : 20,
      '#min' => 15,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('dashboards.settings')
      ->set('colormap', $form_state->getValue('colormap'))
      ->set('alpha', $form_state->getValue('alpha'))
      ->set('shades', $form_state->getValue('shades'))
      ->save();
  }

}
