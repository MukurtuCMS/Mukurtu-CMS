<?php

namespace Drupal\entity_browser\Plugin\EntityBrowser\WidgetSelector;

use Drupal\entity_browser\WidgetSelectorBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Displays widgets in a select list.
 *
 * @EntityBrowserWidgetSelector(
 *   id = "drop_down",
 *   label = @Translation("Drop down widget"),
 *   description = @Translation("Displays the widgets in a drop down.")
 * )
 */
class DropDown extends WidgetSelectorBase {

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$form = [], ?FormStateInterface &$form_state = NULL) {
    // Set a wrapper container for us to replace the form on ajax call.
    $form['#prefix'] = '<div id="entity-browser-form">';
    $form['#suffix'] = '</div>';

    /** @var \Drupal\entity_browser\EntityBrowserInterface $browser */
    $browser = $form_state->getFormObject()->getEntityBrowser();

    $widget_ids = [];
    foreach ($this->widget_ids as $widget_id => $widget_name) {
      if ($browser->getWidget($widget_id)->access()->isAllowed()) {
        $widget_ids[$widget_id] = $widget_name;
      }
    }

    $element['widget'] = [
      '#type' => 'select',
      '#options' => $widget_ids,
      '#default_value' => $this->getDefaultWidget(),
      '#executes_submit_callback' => TRUE,
      '#limit_validation_errors' => [['widget']],
      // #limit_validation_errors only takes effect if #submit is present.
      '#submit' => [],
      '#ajax' => [
        'callback' => [$this, 'changeWidgetCallback'],
        'wrapper' => 'entity-browser-form',
      ],
    ];

    $element['change'] = [
      '#type' => 'submit',
      '#name' => 'change',
      '#value' => $this->t('Change'),
      '#attributes' => ['class' => ['js-hide']],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$form, FormStateInterface $form_state) {
    return $form_state->getValue('widget');
  }

  /**
   * AJAX callback to refresh form.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return array
   *   Form element to replace.
   */
  public function changeWidgetCallback(array &$form, FormStateInterface $form_state) {
    return $form;
  }

}
