<?php

namespace Drupal\search_api_solr\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\search_api_solr\Utility\Utility;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for Solr filed types.
 */
class SolrFieldTypeForm extends EntityForm {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger')
    );
  }

  /**
   * Constructs a SolrFieldTypeForm object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $this->messenger->addWarning($this->t("Using this form you have limited options to edit the SolrFieldType, for example the text files like the stop word list. For editing all features you should use Drupal's configuration management and edit the YAML file of a SolrFieldType unless a full-featured UI exists."));

    $form = parent::form($form, $form_state);

    $solr_field_type = $this->entity;
    $form['label'] = [
      '#type' => 'SolrFieldType',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $solr_field_type->label(),
      '#description' => $this->t("Label for the SolrFieldType."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $solr_field_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\search_api_solr\Entity\SolrFieldType::load',
      ],
      '#disabled' => !$solr_field_type->isNew(),
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('FieldType'),
      '#description' => $this->t("Using this form you have limited options to edit at least the SolrFieldType's analyzers by manipulating the JSON representation. But it is highly recommended to use Drupal's configuration management and edit the YAML file of a SolrFieldType instead. Anyway, if you confirm that you're knowing what you do, you're allowed to do so."),
      '#tree' => FALSE,
    ];

    $form['advanced']['i_know_what_i_do'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("I know what I'm doing!"),
      '#default_value' => FALSE,
    ];

    $form['advanced']['field_type'] = [
      '#type' => 'textarea',
      '#title' => $this->t('FieldType'),
      '#description' => $this->t('The JSON representation is also usable to export a field type from a Solr server and to paste it here (at least partly).'),
      '#default_value' => $solr_field_type->getFieldTypeAsJson(TRUE),
      '#states' => [
        'invisible' => [':input[name="i_know_what_i_do"]' => ['checked' => FALSE]],
      ],
    ];

    $form['text_files'] = [
      '#type' => 'details',
      '#title' => $this->t('Text Files'),
      '#tree' => TRUE,
      '#open' => TRUE,
    ];

    $text_files = $solr_field_type->getTextFiles();
    foreach ($text_files as $text_file_name => $text_file) {
      $form['text_files'][$text_file_name] = [
        '#type' => 'textarea',
        '#title' => $text_file_name,
        '#default_value' => $text_file,
        '#description' => Utility::completeTextFileName($text_file_name, $solr_field_type),
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\search_api_solr\SolrFieldTypeInterface $solr_field_type */
    $solr_field_type = $this->entity;
    $solr_field_type->setFieldTypeAsJson($form_state->getValue('field_type'));
    $solr_field_type->setTextFiles($form_state->getValue('text_files') ?? []);

    $status = $solr_field_type->save();

    if ($status) {
      $this->messenger->addStatus($this->t('Saved the %label Solr Field Type.', [
        '%label' => $solr_field_type->label(),
      ]));
    }
    else {
      $this->messenger->addWarning($this->t('The %label Solr Field Type was not saved.', [
        '%label' => $solr_field_type->label(),
      ]));
    }
    $form_state->setRedirectUrl($solr_field_type->toUrl('collection'));
  }

}
