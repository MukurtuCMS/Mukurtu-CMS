<?php

namespace Drupal\mukurtu_media\Form;

use Drupal\media_library\Form\AddFormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\mukurtu_core\Plugin\media\Source\ExternalEmbed;

/**
 * Creates a form to create media entities from external embed code.
 *
 * @internal
 *   Form classes are internal.
 */
class ExternalEmbedForm extends AddFormBase
{
  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return $this->getBaseFormId() . '_external_embed';
  }

  /**
   * {@inheritdoc}
   */
  protected function getMediaType(FormStateInterface $form_state)
  {
    if ($this->mediaType) {
      return $this->mediaType;
    }

    $media_type = parent::getMediaType($form_state);
    $source = $media_type->getSource();
    if (!$media_type->getSource() instanceof ExternalEmbed) {
      throw new \InvalidArgumentException('Can only add media types which use an external embed source plugin.');
    }
    return $media_type;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildInputElement(array $form, FormStateInterface $form_state)
  {
    // Add a container to group the input elements for styling purposes.
    $form['container'] = [
      '#type' => 'container',
    ];

    $form['container']['external_embed_code'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Add External Embed'),
      '#description' => $this->t('Paste your embed code here. Note that externally hosted resources cannot be protected by cultural protocols.'),
      '#required' => TRUE,
    ];

    $form['container']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#button_type' => 'primary',
      '#submit' => ['::addButtonSubmit'],
      '#ajax' => [
        'callback' => '::updateFormCallback',
        'wrapper' => 'media-library-wrapper',
        // Add a fixed URL to post the form since AJAX forms are automatically
        // posted to <current> instead of $form['#action'].
        // @todo Remove when https://www.drupal.org/project/drupal/issues/2504115
        //   is fixed.
        'url' => Url::fromRoute('media_library.ui'),
        'options' => [
          'query' => $this->getMediaLibraryState($form_state)->all() + [
            FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
          ],
        ],
      ],
    ];
    return $form;
  }

  /**
   * Submit handler for the add button.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function addButtonSubmit(array $form, FormStateInterface $form_state)
  {
    $this->processInputValues([$form_state->getValue('external_embed_code')], $form, $form_state);
  }
}
