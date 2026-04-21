<?php

namespace Drupal\search_api_solr_admin\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\Utility\Utility;

/**
 * The upload configset form.
 *
 * @package Drupal\search_api_solr_admin\Form
 */
class SolrUploadConfigsetForm extends SolrAdminFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'solr_upload_configset_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ServerInterface $search_api_server = NULL) {
    $this->searchApiServer = $search_api_server;

    $connector = Utility::getSolrCloudConnector($this->searchApiServer);

    $collection_name = $connector->getCollectionName();
    if (!$collection_name) {
      $this->messenger->addError($this->t("Upload isn't possible! There's no default collection specified for this server. Edit this server and provide the default collection's name."));
    }

    $configset = $connector->getConfigSetName();
    if (!$configset) {
      $this->messenger->addWarning($this->t('No existing configset name could be detected on the Solr server for this collection. That is fine if you are creating a new collection. Otherwise check the logs for errors.'));
    }

    $form['#title'] = $collection_name ? $this->t('Upload Configset for %collection?', ['%collection' => $collection_name]) : $this->t('Upload Configset requires a default collection to be specified for this server.');

    if (!$configset) {
      $form['numShards'] = [
        '#type' => 'number',
        '#title' => 'numShards',
        '#description' => $this->t('The number of shards to be created for the collection.'),
        '#default_value' => 1,
      ];
      $form['maxShardsPerNode'] = [
        '#type' => 'number',
        '#title' => 'maxShardsPerNode',
        '#description' => $this->t('When creating collections, the shards and/or replicas are spread across all available (i.e., live) nodes, and two replicas of the same shard will never be on the same node. If a node is not live when the CREATE action is called, it will not get any parts of the new collection, which could lead to too many replicas being created on a single live node. Defining maxShardsPerNode sets a limit on the number of replicas the CREATE action will spread to each node. If the entire collection can not be fit into the live nodes, no collection will be created at all. The default maxShardsPerNode value is 1. A value of -1 means unlimited. If a policy is also specified then the stricter of maxShardsPerNode and policy rules apply.'),
        '#default_value' => 1,
      ];
      $form['replicationFactor'] = [
        '#type' => 'number',
        '#title' => 'replicationFactor',
        '#description' => $this->t('The number of replicas to be created for each shard. The default is 1. This will create a NRT type of replica. If you want another type of replica, see the tlogReplicas and pullReplica parameters below.'),
        '#default_value' => 1,
      ];
      $form['nrtReplicas'] = [
        '#type' => 'number',
        '#title' => 'nrtReplicas',
        '#description' => $this->t('The number of NRT (Near-Real-Time) replicas to create for this collection. This type of replica maintains a transaction log and updates its index locally. If you want all of your replicas to be of this type, you can simply use replicationFactor instead.'),
        '#default_value' => 0,
      ];
      $form['tlogReplicas'] = [
        '#type' => 'number',
        '#title' => 'tlogReplicas',
        '#description' => $this->t('The number of TLOG replicas to create for this collection. This type of replica maintains a transaction log but only updates its index via replication from a leader.'),
        '#default_value' => 0,
      ];
      $form['pullReplicas'] = [
        '#type' => 'number',
        '#title' => 'pullReplicas',
        '#description' => $this->t('The number of PULL replicas to create for this collection. This type of replica does not maintain a transaction log and only updates its index via replication from a leader. This type is not eligible to become a leader and should not be the only type of replicas in the collection.'),
        '#default_value' => 0,
      ];
      $form['autoAddReplicas'] = [
        '#type' => 'checkbox',
        '#title' => 'autoAddReplicas',
        '#description' => $this->t('When checked, enables automatic addition of replicas when the number of active replicas falls below the value set for replicationFactor. This may occur if a replica goes down, for example. The default is false, which means new replicas will not be added.'),
        '#default_value' => FALSE,
      ];
      $form['alias'] = [
        '#type' => 'textfield',
        '#title' => 'alias',
        '#description' => $this->t('Starting with Solr version 8.1 when a collection is created additionally an alias can be created that points to this collection. This parameter allows specifying the name of this alias, effectively combining this operation with CREATEALIAS.'),
        '#default_value' => '',
      ];
      $form['waitForFinalState'] = [
        '#type' => 'checkbox',
        '#title' => 'waitForFinalState',
        '#description' => $this->t('If checked, the request will complete only when all affected replicas become active. The default is false, which means that the API will return the status of the single action, which may be before the new replica is online and active.'),
        '#default_value' => FALSE,
      ];
      $form['createNodeSet'] = [
        '#type' => 'textfield',
        '#title' => 'createNodeSet',
        '#description' => $this->t('Allows defining the nodes to spread the new collection across. The format is a comma-separated list of node_names, such as localhost:8983_solr,localhost:8984_solr,localhost:8985_solr. If not provided, the CREATE operation will create shard-replicas spread across all live Solr nodes. Alternatively, use the special value of EMPTY to initially create no shard-replica within the new collection and then later use the ADDREPLICA operation to add shard-replicas when and where required.'),
        '#default_value' => '',
      ];

      $form['accept'] = [
        '#type' => 'value',
        '#default_value' => TRUE,
      ];
    }
    else {
      $form['accept'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Upload (and overwrite) configset %configset to Solr Server.', ['%configset' => $configset]),
        '#description' => $configset ? $this->t("The collection will be reloaded using the new configset") : $this->t('A new collection will be created from the configset.'),
        '#default_value' => FALSE,
      ];
    }

    if ($collection_name) {
      $form['actions'] = [
        'submit' => [
          '#type' => 'submit',
          '#value' => $configset ? $this->t('Upload') : $this->t('Upload and create collection'),
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getValue('accept')) {
      $form_state->setError($form['accept'], $this->t('You must accept the action that will be taken after the configset is uploaded.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $this->commandHelper->uploadConfigset($this->searchApiServer->id(), $form_state->getValues(), TRUE);
    }
    catch (\Exception $e) {
      $this->messenger->addError($e->getMessage());
      $this->logException($e);
    }

    $form_state->setRedirect('entity.search_api_server.canonical', ['search_api_server' => $this->searchApiServer->id()]);
  }

}
