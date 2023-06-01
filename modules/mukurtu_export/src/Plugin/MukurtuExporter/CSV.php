<?php

namespace Drupal\mukurtu_export\Plugin\MukurtuExporter;

use Drupal\mukurtu_export\Plugin\ExporterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of MukurtuExporter for CSV.
 *
 * @MukurtuExporter(
 *   id = "csv",
 *   label = @Translation("CSV"),
 *   description = @Translation("Export to CSV.")
 * )
 */
class CSV extends ExporterBase
{
    protected $exportScope;

    public function settingsForm(array $form, FormStateInterface $form_state)
    {
        $form['binary_files'] = [
            '#type' => 'radios',
            '#title' => $this->t('Files to Export'),
            '#default_value' => 'metadata',
            '#options' => [
                'metadata' => $this->t('Export metadata only'),
                'metadata_and_binary' => $this->t('Include media and other files'),
            ]
        ];
        return $form;
    }

    public function getConfig(array &$form, FormStateInterface $form_state)
    {
        $config = [];
        $config['binary_files'] = $form_state->getValue('binary_files');
        return $config;
    }
}