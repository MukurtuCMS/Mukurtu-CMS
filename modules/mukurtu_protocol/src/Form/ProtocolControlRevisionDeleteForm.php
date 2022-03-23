<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a Protocol control revision.
 *
 * @ingroup mukurtu_protocol
 */
class ProtocolControlRevisionDeleteForm extends ConfirmFormBase {

  /**
   * The Protocol control revision.
   *
   * @var \Drupal\mukurtu_protocol\Entity\ProtocolControlInterface
   */
  protected $revision;

  /**
   * The Protocol control storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $protocolControlStorage;

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
    $instance->protocolControlStorage = $container->get('entity_type.manager')->getStorage('protocol_control');
    $instance->connection = $container->get('database');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'protocol_control_revision_delete_confirm';
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
    return new Url('entity.protocol_control.version_history', ['protocol_control' => $this->revision->id()]);
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
  public function buildForm(array $form, FormStateInterface $form_state, $protocol_control_revision = NULL) {
    $this->revision = $this->ProtocolControlStorage->loadRevision($protocol_control_revision);
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->ProtocolControlStorage->deleteRevision($this->revision->getRevisionId());

    $this->logger('content')->notice('Protocol control: deleted %title revision %revision.', ['%title' => $this->revision->label(), '%revision' => $this->revision->getRevisionId()]);
    $this->messenger()->addMessage(t('Revision from %revision-date of Protocol control %title has been deleted.', ['%revision-date' => \Drupal::service('date.formatter')->format($this->revision->getRevisionCreationTime()), '%title' => $this->revision->label()]));
    $form_state->setRedirect(
      'entity.protocol_control.canonical',
       ['protocol_control' => $this->revision->id()]
    );
    if ($this->connection->query('SELECT COUNT(DISTINCT vid) FROM {protocol_control_field_revision} WHERE id = :id', [':id' => $this->revision->id()])->fetchField() > 1) {
      $form_state->setRedirect(
        'entity.protocol_control.version_history',
         ['protocol_control' => $this->revision->id()]
      );
    }
  }

}
