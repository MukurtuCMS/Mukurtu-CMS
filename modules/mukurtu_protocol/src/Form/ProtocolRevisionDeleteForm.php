<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a Protocol revision.
 *
 * @ingroup mukurtu_protocol
 */
class ProtocolRevisionDeleteForm extends ConfirmFormBase {

  /**
   * The Protocol revision.
   *
   * @var \Drupal\mukurtu_protocol\Entity\ProtocolInterface
   */
  protected $revision;

  /**
   * The Protocol storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $protocolStorage;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->protocolStorage = $container->get('entity_type.manager')->getStorage('protocol');
    $instance->connection = $container->get('database');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'protocol_revision_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the revision from %revision-date?', [
      '%revision-date' => \Drupal::service('date.formatter')->format($this->revision->getRevisionCreationTime()),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.protocol.version_history', ['protocol' => $this->revision->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $protocol_revision = NULL) {
    $this->revision = $this->ProtocolStorage->loadRevision($protocol_revision);
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->ProtocolStorage->deleteRevision($this->revision->getRevisionId());

    $this->logger('content')->notice('Protocol: deleted %title revision %revision.', ['%title' => $this->revision->label(), '%revision' => $this->revision->getRevisionId()]);
    $this->messenger()->addMessage(t('Revision from %revision-date of Protocol %title has been deleted.', ['%revision-date' => \Drupal::service('date.formatter')->format($this->revision->getRevisionCreationTime()), '%title' => $this->revision->label()]));
    $form_state->setRedirect(
      'entity.protocol.canonical',
       ['protocol' => $this->revision->id()]
    );
    if ($this->connection->query('SELECT COUNT(DISTINCT vid) FROM {protocol_field_revision} WHERE id = :id', [':id' => $this->revision->id()])->fetchField() > 1) {
      $form_state->setRedirect(
        'entity.protocol.version_history',
         ['protocol' => $this->revision->id()]
      );
    }
  }

}
