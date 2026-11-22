<?php

/**
 * @file
 * Seeds test items and accounts for the #400 PWA spike.
 *
 * Run with `drush php:script`. Safe to run again: anything that already
 * exists is reused. Creates its own community, protocols and category rather
 * than using a demo recipe, so Playwright's default content stays the only
 * other content on the site.
 *
 * Creates:
 * - "Offline test community", with "Offline test: open protocol" and the
 *   "Offline test category" category.
 * - "Offline test: small item": open, a photo and 5 MB of audio.
 * - "Offline test: large item 1" to 3: open, 25 MB of audio each, for the
 *   storage tests.
 * - "Offline test: restricted item": a strict protocol, a photo and 5 MB of
 *   audio.
 * - Accounts pwa-member (in the strict protocol) and pwa-outsider (not), with
 *   the password in PWA_SPIKE_PASSWORD, or "pwa-spike" when it isn't set.
 */

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;

$fileSystem = \Drupal::service('file_system');

// Run as the site owner, who manages the test community and protocols.
$owner = User::load(1);
\Drupal::currentUser()->setAccount($owner);

/**
 * Loads one entity by a field value, or NULL.
 */
$findOne = function (string $entityType, string $field, string $value) {
  $ids = \Drupal::entityQuery($entityType)
    ->accessCheck(FALSE)
    ->condition($field, $value)
    ->range(0, 1)
    ->execute();
  return $ids ? \Drupal::entityTypeManager()->getStorage($entityType)->load(reset($ids)) : NULL;
};

$community = $findOne('community', 'name', 'Offline test community');
if (!$community) {
  $community = Community::create([
    'name' => 'Offline test community',
    'user_id' => 1,
    'field_access_mode' => 'public',
  ]);
  $community->save();
}
// Creating a protocol needs the creator to manage its community.
$community->addMember($owner, ['community_manager']);
$communityId = $community->id();

$openProtocol = $findOne('protocol', 'name', 'Offline test: open protocol');
if (!$openProtocol) {
  $openProtocol = Protocol::create([
    'name' => 'Offline test: open protocol',
    'user_id' => 1,
    'field_communities' => [$communityId],
    'field_access_mode' => 'open',
  ]);
  $openProtocol->save();
}

$category = $findOne('taxonomy_term', 'name', 'Offline test category');
if (!$category) {
  $category = Term::create(['vid' => 'category', 'name' => 'Offline test category']);
  $category->save();
}

$password = getenv('PWA_SPIKE_PASSWORD') ?: 'pwa-spike';
$accounts = [];
foreach (['pwa-member', 'pwa-outsider'] as $name) {
  $account = user_load_by_name($name) ?: User::create(['name' => $name, 'status' => 1]);
  $account->setPassword($password);
  $account->save();
  $accounts[$name] = $account;
}

$strictProtocol = $findOne('protocol', 'name', 'Offline test: restricted protocol');
if (!$strictProtocol) {
  $strictProtocol = Protocol::create([
    'name' => 'Offline test: restricted protocol',
    'field_communities' => [$communityId],
    'field_access_mode' => 'strict',
  ]);
  $strictProtocol->save();
}
$strictProtocol->addMember($accounts['pwa-member']);

$directory = 'private://pwa-spike-seed';
$fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

/**
 * Writes a WAV file of a quiet tone, about $megabytes in size.
 */
$makeAudio = function (string $name, int $megabytes) use ($directory, $fileSystem) {
  $uri = "$directory/$name.wav";
  if (!file_exists($uri)) {
    $rate = 22050;
    $samples = intdiv($megabytes * 1024 * 1024, 2);
    $pcm = '';
    for ($i = 0; $i < $rate; $i++) {
      $pcm .= pack('v', (int) (3000 * sin(2 * M_PI * 440 * $i / $rate)));
    }
    // Repeat one second of tone to the requested length.
    $data = substr(str_repeat($pcm, (int) ceil($samples / $rate)), 0, $samples * 2);
    $header = 'RIFF' . pack('V', 36 + strlen($data)) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16) . 'data' . pack('V', strlen($data));
    $fileSystem->saveData($header . $data, $uri, FileExists::Replace);
  }
  return $uri;
};

/**
 * Creates (or reuses) a permanent file entity for a URI.
 */
$makeFile = function (string $uri) use ($findOne) {
  $file = $findOne('file', 'uri', $uri) ?: File::create(['uri' => $uri, 'status' => 1, 'uid' => 1]);
  $file->setPermanent();
  $file->save();
  return $file;
};

$photoUri = "$directory/basket.jpg";
$fileSystem->copy(DRUPAL_ROOT . '/profiles/mukurtu/recipes/accessibility_demo_content/content/file/basket-sample.jpg', $photoUri, FileExists::Replace);

/**
 * Creates (or reuses) a media item under a protocol.
 */
$makeMedia = function (string $bundle, string $name, array $values, Protocol $protocol) use ($findOne) {
  $media = $findOne('media', 'name', $name)
    ?: Media::create(['bundle' => $bundle, 'name' => $name, 'uid' => 1, 'status' => 1] + $values);
  $media->setSharingSetting('any');
  $media->setProtocols([$protocol]);
  $media->save();
  return $media;
};

$items = [
  'Offline test: small item' => [$openProtocol, TRUE, 5],
  'Offline test: large item 1' => [$openProtocol, FALSE, 25],
  'Offline test: large item 2' => [$openProtocol, FALSE, 25],
  'Offline test: large item 3' => [$openProtocol, FALSE, 25],
  'Offline test: restricted item' => [$strictProtocol, TRUE, 5],
];
foreach ($items as $title => [$protocol, $withPhoto, $megabytes]) {
  $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($title));
  $assets = [];
  if ($withPhoto) {
    $assets[] = $makeMedia('image', "$title: photo", [
      'field_media_image' => [
        'target_id' => $makeFile($photoUri)->id(),
        'alt' => 'A generated placeholder photograph of a woven basket, used only for offline testing.',
      ],
    ], $protocol);
  }
  $assets[] = $makeMedia('audio', "$title: audio", [
    'field_media_audio_file' => ['target_id' => $makeFile($makeAudio($slug, $megabytes))->id()],
  ], $protocol);

  $node = $findOne('node', 'title', $title)
    ?: Node::create(['type' => 'digital_heritage', 'title' => $title, 'uid' => 1]);
  $node->set('field_category', [$category]);
  $node->set('field_media_assets', $assets);
  $node->setSharingSetting('any');
  $node->setProtocols([$protocol]);
  $node->setPublished();
  $node->save();
  echo "$title: " . $node->toUrl()->toString() . "\n";
}
