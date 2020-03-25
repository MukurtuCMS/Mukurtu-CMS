<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;

class MukurtuProtocolManager {

  protected $protocolTable;
  protected $protocolFieldName;

  public function __construct() {
    // TODO: Allow this to be configured.
    $this->protocolFieldName = 'og_audience';
    $this->protocolTable = \Drupal::state()->get('mukurtu_protocol_lookup_table');

    if (!isset($this->protocolTable['new_id'])) {
      $this->protocolTable['new_id'] = 1;
    }
/*     $this->protocolTable = [
      1 => [
        5 => ['id' => 7],
        45 => ['id' => 1],
        1002 => ['id' => 8],
      ],
      2 => [
        ['id' => 2, 'protocols' => [72, 98]],
        ['id' => 3, 'protocols' => [5, 1002]],
      ],
      3 => [
        ['id' => 4, 'protocols' => [19, 1002, 3000]],
        ['id' => 5, 'protocols' => [22, 1003, 3001]],
        ['id' => 6, 'protocols' => [72, 98, 3000]],
      ],
    ]; */
  }

  protected function clearProtocolTable() {
    $this->protocolTable = [];
    $this->protocolTable['new_id'] = 1;
    $this->saveProtocolTable();
  }

  /**
   * Return an array of effective protocols the user belongs to.
   *
   * @param Drupal\Core\Session\AccountInterface $account
   *   The user account.
   */
  public function getMemberProtocols(AccountInterface $account) {
    // TODO: Actually implemement this.
    return [2, 5];
  }

  /**
   * Save the protocol table.
   */
  protected function saveProtocolTable() {
    \Drupal::state()->set('mukurtu_protocol_lookup_table', $this->protocolTable);
  }

  /**
   * Create the Mukurtu ID for an effective protocol.
   *
   * You must NOT call this for protocols that already exist in the table.
   *
   * @param array $protocols
   *   An array containing all the nids of the protocols.
   */
  protected function createProtocolId(array $protocols) {
    $size = count($protocols);

    if ($size == 1 && !isset($this->protocolTable[$size][$protocols[0]])) {
      $this->protocolTable[$size][$protocols[0]] = ['id' => $this->protocolTable['new_id']++];
      $this->saveProtocolTable();
      return $this->protocolTable[$size][$protocols[0]]['id'];
    }

    if ($size > 1) {
      sort($protocols);

      $new_id = $this->protocolTable['new_id']++;
      $this->protocolTable[$size][] = ['id' => $new_id, 'protocols' => $protocols];
      $this->saveProtocolTable();
      return $new_id;
    }

    return NULL;
  }

  /**
   * Return the Mukurtu ID for an effective protocol.
   *
   * @param array $protocols
   *   An array containing all the nids of the protocols.
   */
  public function getProtocolId(array $protocols) {
    // No protocols given resolves to null.
    if (empty($protocols)) {
      return NULL;
    }

    $size = count($protocols);

    // For a single protocol, do a direct lookup.
    if ($size == 1) {
      // Create the ID if it doesn't yet exist.
      if (!isset($this->protocolTable[$size][$protocols[0]]['id'])) {
        return $this->createProtocolId($protocols);
      }

      return $this->protocolTable[$size][$protocols[0]]['id'];
    }

    // For the intersection of protocols, we need to search.
    sort($protocols);
    foreach ($this->protocolTable[$size] as $superProtocol) {
      $i = 0;
      foreach ($superProtocol['protocols'] as $p) {
        // Does member of the super protocol exist in the given protocol list?
        if ($p != $protocols[$i++]) {
          break;
        }

        // All members match.
        if ($i == $size) {
          return $superProtocol['id'];
        }
      }
    }

    // Searched the whole list, didn't find it. Try creating one.
    return $this->createProtocolId($protocols);
  }

  /**
   * Return the array of protocol NIDs a node is using.
   *
   * @param Drupal\node\Entity\Node $node
   *   The node.
   */
  public function getNodeProtocols(Node $node) {
    $protocols = [];

    if ($node->hasField($this->protocolFieldName)) {
      $protocols_og = $node->get($this->protocolFieldName)->getValue();
      $flatten = function ($e) {
        return isset($e['target_id']) ? $e['target_id'] : NULL;
      };
      $protocols = array_map($flatten, $protocols_og);
    }

    return $protocols;
  }

  /**
   * Return the effective protocol ID the node is using.
   *
   * @param Drupal\node\Entity\Node $node
   *   The node.
   */
  public function getNodeProtocolId(Node $node) {
    $protocols = $this->getNodeProtocols($node);
    return $this->getProtocolId($protocols);
  }

}
