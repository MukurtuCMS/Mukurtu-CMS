<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Session\AccountInterface;

class MukurtuProtocolManager {

  protected $protocolTable;
  protected $protocolGrantTable;

  public function __construct() {
    $this->protocolTable = [
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
    ];
  }

  /**
   * Return an array of effective protocols the user belongs to.
   *
   * @param Drupal\Core\Session\AccountInterface $account
   *   The user account.
   */
  public function getMemberProtocols(AccountInterface $account) {
    // TODO: Actually implemement this.
    return [2, 6];
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

    return NULL;
  }

  public function getGrantId($protocols) {
    return "my dude";
  }
}
