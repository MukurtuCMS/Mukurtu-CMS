<?php

namespace Drupal\devel\Drush\Commands;

use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

final class DevelCommands extends DrushCommands {

  #[Deprecated('Use constant from command-specific file or use a string')]
  const REINSTALL = 'devel:reinstall';
  #[Deprecated('Use constant from command-specific file or use a string')]
  const HOOK = 'devel:hook';
  #[Deprecated('Use constant from command-specific file or use a string')]
  const EVENT = 'devel:event';
  #[Deprecated('Use constant from command-specific file or use a string')]
  const TOKEN = 'devel:token';
  #[Deprecated('Use constant from command-specific file or use a string')]
  const UUID = 'devel:uuid';
  #[Deprecated('Use constant from command-specific file or use a string')]
  const SERVICES = 'devel:services';

}
