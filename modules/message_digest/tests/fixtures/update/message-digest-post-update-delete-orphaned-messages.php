<?php

/**
 * @file
 * Upgrade data for message_digest_post_update_delete_orphaned_messages().
 *
 * Contains database additions to drupal-8.bare.standard.php.gz for testing the
 * upgrade path of message_digest_post_update_delete_orphaned_messages().
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

// User data.
$connection->insert('users')
  ->fields([
    'uid',
    'uuid',
    'langcode',
  ])
  ->values([
    'uid' => '3',
    'uuid' => 'be49deb0-4b51-47b4-b5f3-355b473370a5',
    'langcode' => 'en',
  ])
  ->execute();

$connection->insert('users_field_data')
  ->fields([
    'uid',
    'langcode',
    'preferred_langcode',
    'preferred_admin_langcode',
    'name',
    'pass',
    'mail',
    'timezone',
    'status',
    'created',
    'changed',
    'access',
    'login',
    'init',
    'default_langcode',
  ])
  ->values([
    'uid' => '3',
    'langcode' => 'en',
    'preferred_langcode' => 'en',
    'preferred_admin_langcode' => NULL,
    'name' => 'user-3',
    'pass' => '$S$E4a4O.NdAXjqlYYciUSWTPJTsa2qODJPlosxyPHa4zG0BmcIPH.U',
    'mail' => 'user-3@example.com',
    'timezone' => 'Europe/Sofia',
    'status' => '1',
    'created' => '1456053421',
    'changed' => '1457342212',
    'access' => '1457552303',
    'login' => '1457342212',
    'init' => 'user-3@example.com',
    'default_langcode' => '1',
  ])
  ->execute();

// Messages.
$connection->insert('message')
  ->fields([
    'mid',
    'template',
    'uuid',
    'langcode',
  ])
  ->values([
    'mid' => '2',
    'template' => 'test_template',
    'uuid' => '98660bfa-4d7c-4361-8880-5d40e74ce419',
    'langcode' => 'en',
  ])
  ->values([
    'mid' => '3',
    'template' => 'test_template',
    'uuid' => '46c4c879-08c0-4e23-b83b-aac56f6f4647',
    'langcode' => 'en',
  ])
  ->execute();

$connection->insert('message_field_data')
  ->fields([
    'mid',
    'template',
    'langcode',
    'uid',
    'created',
    'arguments',
    'default_langcode',
  ])
  ->values([
    'mid' => '2',
    'template' => 'test_template',
    'langcode' => 'en',
    'uid' => '700005',
    'created' => '1516794322',
    'arguments' => '',
    'default_langcode' => '1',
  ])
  ->values([
    'mid' => '3',
    'template' => 'test_template',
    'langcode' => 'en',
    'uid' => '700006',
    'created' => '1516794353',
    'arguments' => '',
    'default_langcode' => '1',
  ])
  ->execute();

// Message digest data.
$connection->insert('message_digest')
  ->fields([
    'id',
    'mid',
    'entity_type',
    'entity_id',
    'receiver',
    'notifier',
    'sent',
    'timestamp',
  ])
  // An entry referencing an orphaned message.
  ->values([
    'id' => '1',
    'mid' => '1',
    'entity_type' => 'node',
    'entity_id' => '1',
    'receiver' => '1',
    'notifier' => 'message_digest:daily',
    'sent' => '0',
    'timestamp' => '1516794322',
  ])
  // An entry referencing an orphaned user account.
  ->values([
    'id' => '2',
    'mid' => '2',
    'entity_type' => 'node',
    'entity_id' => '2',
    'receiver' => '2',
    'notifier' => 'message_digest:daily',
    'sent' => '0',
    'timestamp' => '1516794322',
  ])
  // An entry referencing an existing message and user account.
  ->values([
    'id' => '3',
    'mid' => '3',
    'entity_type' => 'node',
    'entity_id' => '3',
    'receiver' => '3',
    'notifier' => 'message_digest:daily',
    'sent' => '0',
    'timestamp' => '1516794322',
  ])
  ->execute();
