<?php

namespace Drupal\layout_builder_restrictions_by_region\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder_restrictions\Form\RestrictionPluginConfigForm;

/**
 * Supplement form UI to add setting for which blocks & layouts are available.
 */
class RestrictionPluginConfigAlter extends RestrictionPluginConfigForm {

  /**
   * The actual form elements.
   */
  public function alterForm(&$form, FormStateInterface $form_state, $form_id) {
    $config = $this->configFactory()->get('layout_builder_restrictions_by_region.settings');
    $form['retain_restrictions_after_layout_removal'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow restrictions to remain after disabling a layout.'),
      '#description' => $this->t('One method for using Layout Builder is to configure a default display with specific layouts but not allow users to add additional layouts to sections. In this scenario, site managers may want to configure block restrictions for those layouts while simultaneously disallowing the layouts themselves (see <a href="https://www.drupal.org/project/layout_builder_restrictions/issues/3305449">#3305449</a>). To achieve this, check this box, which will cause any previous block restrictions on a layout to remain, even if the layout has been disabled.'),
      '#default_value' => $config->get('retain_restrictions_after_layout_removal') ?? '0',
    ];
    $form['#submit'][] = [$this, 'alterSubmit'];
  }

  /**
   * Extend submit callback.
   */
  public function alterSubmit(&$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('layout_builder_restrictions_by_region.settings');
    $retain_restrictions_after_layout_removal = $form_state->getValue('retain_restrictions_after_layout_removal');
    $config->set('retain_restrictions_after_layout_removal', $retain_restrictions_after_layout_removal);
    $config->save();
  }

}
