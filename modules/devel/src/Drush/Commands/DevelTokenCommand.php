<?php

declare(strict_types=1);

namespace Drupal\devel\Drush\Commands;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Utility\Token;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: self::NAME,
  description: 'List available tokens.',
  aliases: ['token', 'devel-token']
)]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
#[CLI\FieldLabels(labels: ['group' => 'Group', 'token' => 'Token', 'name' => 'Name'])]
#[CLI\DefaultTableFields(fields: ['group', 'token', 'name'])]
final class DevelTokenCommand extends Command {

  use AutowireTrait;
  use FormatterTrait;

  public const NAME = 'devel:token';

  public function __construct(
    private readonly Token $token,
    protected readonly FormatterManager $formatterManager,
  ) {
    parent::__construct();
  }

  public function execute(InputInterface $input, OutputInterface $output): int {
    $data = $this->doExecute($input, $output);
    $this->writeFormattedOutput($input, $output, $data);
    return Command::SUCCESS;
  }

  protected function doExecute(InputInterface $input, OutputInterface $output): RowsOfFields {
    $rows = [];
    $all = $this->token->getInfo();
    foreach ($all['tokens'] as $group => $tokens) {
      foreach ($tokens as $key => $token) {
        $rows[] = [
          'group' => $group,
          'token' => $key,
          'name' => $token['name'],
        ];
      }
    }
    return new RowsOfFields($rows);
  }

}
