<?php

namespace Drupal\mukurtu_protocol\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\mukurtu_protocol\CulturalProtocols;

/**
 * Provides a field type of CulturalProtocol.
 *
 * @FieldType(
 *   id = "cultural_protocol",
 *   label = @Translation("Cultural Protocols"),
 *   default_formatter = "cultural_protocol_formatter",
 *   default_widget = "cultural_protocol_widget",
 * )
 */
class CulturalProtocolItem extends FieldItemBase {
  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = [
      'columns' => [
        'protocols' => [
          'description' => 'Comma delimited list of protocol IDs.',
          'type' => 'text',
          'size' => 'normal',
        ],
        'sharing_setting' => [
          'description' => 'The sharing setting.',
          'type' => 'varchar',
          'length' => 512,
        ],
        'protocol_set' => [
          'description' => 'Hash key for the protocol set.',
          'type' => 'text',
          'size' => 'normal',
        ],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];
    $properties['protocols'] = DataDefinition::create('string')
    ->setLabel(t('Protocols'))
    ->setRequired(TRUE);

    $properties['sharing_setting'] = DataDefinition::create('string')
    ->setLabel(t('Sharing Setting'))
    ->addConstraint('ValidSharingSettingConstraint')
    ->setRequired(TRUE);

    $properties['protocol_set_id'] = DataDefinition::create('integer')
    ->setLabel(t('Protocol Set ID'))
    ->setRequired(FALSE);

    return $properties;
  }

    /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'protocols';
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();

    // @todo UID 1 handling?
    if ((int) \Drupal::currentUser()->id() !== 1) {
      // For existing entities, bring back in any protocol references that were
      // lost that the user does not have permission to discard.
      $protocol_ids_user_can_apply = $this->getSettableProtocolIds();
      $entity = $this->getEntity();
      $new_protocols = $this->getProtocolIds();

      if (!$entity->isNew()) {
        $original_value = $entity->original->get($this->getFieldDefinition()->getName())->getValue();
        $missing_protocol_ids_to_restore = [];
        $added_protocols_to_remove = [];
        $silently_changed_protocols = FALSE;

        // Big, but likely correct, assumption here that this will never be a multi-value field.
        $orginal_formatted_protocols = $original_value[0]['protocols'] ?? '';
        $original_protocols = $this->unformatProtocols($orginal_formatted_protocols);

        $original_missing_protocols = array_diff($original_protocols, $new_protocols);
        if (!empty($original_missing_protocols)) {
          $missing_protocol_ids_to_restore = array_diff($original_missing_protocols, $protocol_ids_user_can_apply);
        }

        // If the user manages to remove active protocols from an item they do
        // not have apply permission for, we want to restore them to the item.
        // This would generally need to happen outside of the UI, like import,
        // or API calls.
        $new_with_restored_protocol_ids = array_merge($new_protocols, $missing_protocol_ids_to_restore);
        if (!empty($missing_protocol_ids_to_restore)) {
          $silently_changed_protocols = TRUE;
        }

        // If the user tried to add protocols they cannot apply, remove them.
        $added_protocols_to_remove = array_diff($new_protocols, $protocol_ids_user_can_apply);
        if (!empty($added_protocols_to_remove)) {
          $silently_changed_protocols = TRUE;
        }

        $final_protocol_ids = array_diff($new_with_restored_protocol_ids, $added_protocols_to_remove);

        if ($silently_changed_protocols) {
          // Save the changes, if needed.
          $this->set('protocols', $this->formatProtocols($final_protocol_ids));
          $this->logProtocolViolationSideEffect();
        }
      } else {
        // Brand new entity.
        // If the user tried to add protocols they cannot apply, remove them.
        $added_protocols_to_remove = array_diff($new_protocols, $protocol_ids_user_can_apply);
        if (!empty($added_protocols_to_remove)) {
          $final_protocol_ids = array_diff($new_protocols, $added_protocols_to_remove);
          $this->set('protocols', $this->formatProtocols($final_protocol_ids));
          $this->logProtocolViolationSideEffect();
        }
      }
    }

    // Build the protocol set ID.
    $protocols = explode(',', $this->protocols);
    $this->protocol_set_id = CulturalProtocols::getProtocolSetId($protocols);
  }

  /**
   * Write a watchdog entry about a protocol violation and restoration.
   */
  protected function logProtocolViolationSideEffect() {
    // Write a notice to the log so there's at least some sort of record
    // on what is going on. I really dislike silent side effects but this
    // is the route that we have chosen, specifically.
    $entity = $this->getEntity();
    $current_user = \Drupal::currentUser();
    $uid = $current_user->id();
    $logger = \Drupal::logger('mukurtu_protocol');
    $logger->info(
      'User @uid attempted to add or remove protocols for which they did not have sufficient permissions from entity @type:@id. Those protocols were automatically restored.',
      [
        '@uid' => $uid,
        '@type' => $entity->getEntityTypeId(),
        '@id' => $entity->id(),
      ]
    );
  }

  /**
   * Take an array of protocol IDs and flatten to a string suitable for storage.
   */
  public static function formatProtocols($value) {
    if (is_array($value)) {
      sort($value, SORT_NUMERIC);
      return implode(',', array_map(fn($p) => "|$p|", array_map('trim', array_filter($value))));
    }
    return $value;
  }

  /**
   * Take the formatted protocol string and return an array of protocol IDs.
   */
  public static function unformatProtocols($value) {
    // Need to handle empty string specifically, otherwise thanks to the
    // behavior of explode we'll end up with an array like [0 => ''] which
    // isn't a valid protocol id state.
    $explodedArray = ($value == NULL || trim($value) == "") ? [] : explode(',', $value);

    if ($protocolIds = str_replace('|', '', $explodedArray)) {
      return array_map(fn($p) => (int) $p, $protocolIds);
    }
    return [];
  }


  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE) {
    $new_value['sharing_setting'] = $value['sharing_setting'] ?? ($this->get('sharing_setting')->getValue() ?? NULL);

    if (isset($value['protocols'])) {
      $new_value['protocols'] = is_array($value['protocols']) ? $this->formatProtocols($value['protocols']) : $value['protocols'];
      return parent::setValue($new_value, $notify);
    }

    // Protocol string format, e.g.,: "all(1,2,3)".
    if (is_string($value)) {
      preg_match('/^(\w+)\s*\((.+)\)\s*/', $value, $matches, PREG_OFFSET_CAPTURE);
      if (isset($matches[1]) && isset($matches[2])) {
        $new_value['sharing_setting'] = strtolower($matches[1][0]);
        $new_value['protocols'] = $this->formatProtocols(explode(',', $matches[2][0]));
        return parent::setValue($new_value, $notify);
      }
    }

    // At this point, we've either got only a sharing setting property coming in
    // or invalid arguments.
    // Try using the old protocols value.
    $new_value['protocols'] = $this->get('protocols')->getValue() ?? NULL;
    if ($new_value['sharing_setting'] && $new_value['protocols']) {
      return parent::setValue($new_value, $notify);
    }

    // Rather than throwing InvalidArgumentException for invalid arguments,
    // we'll set the protocol field to NULLs. This isn't actually valid but
    // this restricts access to UID 1 or the owner only. This is better than
    // throwing the exception because it doesn't crash the site all the time.
    return parent::setValue(['protocols' => NULL, 'sharing_setting' => NULL], $notify);
  }

  /**
   * Deserialize the protocols property into an array of protocol IDs.
   *
   * @return array
   */
  public function getProtocolIds() {
    return $this->unformatProtocols($this->protocols);
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return empty($this->protocols) || empty($this->sharing_setting);
  }

  /**
   * Get the settable protocol ID options.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *  The account to be used to fetch protocols. Note that unlike
   *  OptionsProviderInterface, providing NULL will use the current user.
   *
   * @return array
   *  An array of protocol IDs.
   */
  public function getSettableProtocolIds(AccountInterface $account = NULL) {
    $protocolIds = CulturalProtocols::getProtocolsByUserPermission(['apply protocol'], $account);
    return $protocolIds;
  }

  /**
   * Return the options array for the sharing settings.
   */
  public function getSettableSharingOptions(AccountInterface $account = NULL) {
    return CulturalProtocols::getItemSharingSettingOptions();
  }

}
