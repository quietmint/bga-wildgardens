<?php

/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Wild Gardens implementation : © quietmint
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * Game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 */

declare(strict_types=1);

namespace Bga\Games\WildGardens;

use Bga\Games\WildGardens\States\PlayerMove;
use Bga\GameFramework\Components\Counters\PlayerCounter;
use Bga\Games\WildGardens\Path;

class Game extends \Bga\GameFramework\Table
{
  public Path $path;
  public PlayerCounter $distance0; // wild
  public PlayerCounter $distance1;
  public PlayerCounter $distance2;
  public PlayerCounter $distance3;
  public PlayerCounter $distance4;
  public PlayerCounter $jar;
  public PlayerCounter $jarScore;
  public PlayerCounter $pathSpace;
  public PlayerCounter $aquatic;
  public PlayerCounter $bark;
  public PlayerCounter $flower;
  public PlayerCounter $fruit;
  public PlayerCounter $fungus;
  public PlayerCounter $leaf;
  public PlayerCounter $nut;
  public PlayerCounter $root;

  public array $jarboard = [0, 2, 3, 5, 6, 10, 12, 16, 18, 22, 26, 30, 35, 38, 40, 42, 44, 46, 48, 50, 51, 52, 53, 54];

  private array $playerCounters = [
    'distance0' => 1,
    'distance1' => 1,
    'distance2' => 1,
    'distance3' => 1,
    'distance4' => 1,
    'jar' => 1,
    'jarScore' => 0,
    'pathSpace' => 0,
    'aquatic' => 0,
    'bark' => 0,
    'flower' => 0,
    'fruit' => 0,
    'fungus' => 0,
    'leaf' => 0,
    'nut' => 0,
    'root' => 0,
  ];

  public function __construct()
  {
    parent::__construct();

    // Game options
    $this->initGameStateLabels([
      'optionPath' => 100
    ]);

    // Player counters
    foreach ($this->playerCounters as $counterName => $initialValue) {
      $this->$counterName = $this->bga->counterFactory->createPlayerCounter($counterName);
    }

    // Path
    $this->path = new Path($this);
  }

  public function upgradeTableDb($from_version) {}

  public function getGameProgression()
  {
    return 0;
  }

  protected function getAllDatas(int $currentPlayerId): array
  {
    $result = [
      'jarboard' => $this->jarboard,
      'path' => $this->path,
    ];
    $result['players'] = $this->getCollectionFromDb('SELECT `player_id` AS `id`, `player_score` AS `score` FROM `player`');
    foreach ($this->playerCounters as $counterName => $initialValue) {
      $this->$counterName->fillResult($result);
    }
    return $result;
  }

  function getSpecificColorPairings(): array
  {
    return [
      '72c3b1' /* Cyan */        => '2cafcb',
      '0000ff' /* Blue */        => '2cafcb',
      '982fff' /* Purple */      => '746db0',
      'e94190' /* Pink */        => 'ec647a',
      'ff0000' /* Red */         => 'ec647a',
      'ffa500' /* Yellow */      => 'fad965',
      'f07f16' /* Orange */      => 'fad965',
    ];
  }

  protected function setupNewGame($players, $options = [])
  {
    // Player counters
    foreach ($this->playerCounters as $counterName => $initialValue) {
      $this->$counterName->initDb(array_keys($players), $initialValue);
    }

    // Players
    $gameinfos = $this->getGameinfos();
    $colors = $gameinfos['player_colors'];
    $startSpaces = [1, 2, 11, 10];
    foreach ($players as $playerId => $player) {
      $query_values[] = vsprintf("(%s, '%s', '%s')", [
        $playerId,
        array_shift($colors),
        addslashes($player['player_name']),
      ]);
      $space = array_shift($startSpaces);
      $this->pathSpace->set($playerId, $space, null);
    }
    static::DbQuery(sprintf('INSERT INTO `player` (`player_id`, `player_color`, `player_name`) VALUES %s', implode(', ', $query_values)));
    $this->reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
    $this->reloadPlayersBasicInfos();

    // Path
    $this->path->setup();

    // Init global values with their initial values.

    // Init game statistics.
    //
    // NOTE: statistics used in this file must be defined in your `stats.inc.php` file.

    // Dummy content.
    // $this->tableStats->init('table_teststat1', 0);
    // $this->playerStats->init('player_teststat1', 0);

    // Activate first player once everything has been initialized and ready.
    $this->activeNextPlayer();
    return PlayerMove::class;
  }

  public function getIngredientIcon(string $ingredient): ?string
  {
    switch ($ingredient) {
      case 'aquatic':
        return '💧';
      case 'bark':
        return '🪵';
      case 'flower':
        return '🌸';
      case 'fruit':
        return '🍓';
      case 'fungus':
        return '🍄';
      case 'leaf':
        return '🥬';
      case 'nut':
        return '🥜';
      case 'root':
        return '🧄';
      default:
        return null;
    }
  }

  public function getIngredientName(string $ingredient): ?string
  {
    switch ($ingredient) {
      case 'aquatic':
        return clienttranslate('Aquatic');
      case 'bark':
        return clienttranslate('Bark/Stem');
      case 'flower':
        return clienttranslate('Flower');
      case 'fruit':
        return clienttranslate('Fruit');
      case 'fungus':
        return clienttranslate('Fungus');
      case 'leaf':
        return clienttranslate('Leaf');
      case 'nut':
        return clienttranslate('Nut/Seed');
      case 'root':
        return clienttranslate('Root');
      default:
        return null;
    }
  }


  public function debug_setupPath()
  {
    $this->path->setup();
    $this->bga->notify->all('path', clienttranslate('New path'), ['path' => $this->path]);
  }

  /**
   * Example of debug function.
   * Here, jump to a state you want to test (by default, jump to next player state)
   * You can trigger it on Studio using the Debug button on the right of the top bar.
   */
  public function debug_goToState(int $state = 3)
  {
    $this->gamestate->jumpToState($state);
  }

  /**
   * Another example of debug function, to easily test the zombie code.
   */
  public function debug_playOneMove()
  {
    $this->bga->debug->playUntil(fn(int $count) => $count == 1);
  }

  /*
    Another example of debug function, to easily create situations you want to test.
    Here, put a card you want to test in your hand (assuming you use the Deck component).

    public function debug_setCardInHand(int $cardType, int $playerId) {
        $card = array_values($this->cards->getCardsOfType($cardType))[0];
        $this->cards->moveCard($card['id'], 'hand', $playerId);
    }
    */
}
