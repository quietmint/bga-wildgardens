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
  public PlayerCounter $distance1;
  public PlayerCounter $distance2;
  public PlayerCounter $distance3;
  public PlayerCounter $distance4;
  public PlayerCounter $distanceWild;
  public PlayerCounter $jar;
  public PlayerCounter $pathSpace;
  public PlayerCounter $aquatic;
  public PlayerCounter $bark;
  public PlayerCounter $flower;
  public PlayerCounter $fruit;
  public PlayerCounter $fungus;
  public PlayerCounter $leaf;
  public PlayerCounter $nut;
  public PlayerCounter $root;

  private array $playerCounters = [
    'distance1',
    'distance2',
    'distance3',
    'distance4',
    'distanceWild',
    'jar',
    'pathSpace',
    'aquatic',
    'bark',
    'flower',
    'fruit',
    'fungus',
    'leaf',
    'nut',
    'root'
  ];

  public function __construct()
  {
    parent::__construct();

    // Game options
    $this->initGameStateLabels([
      'optionPath' => 100
    ]);

    // Player counters
    foreach ($this->playerCounters as $counterName) {
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
    $result = [];
    $result['players'] = $this->getCollectionFromDb(
      'SELECT `player_id` AS `id`, `player_score` AS `score` FROM `player`'
    );
    foreach ($this->playerCounters as $counterName) {
      $this->$counterName->fillResult($result);
    }

    $result['path'] = $this->path;

    return $result;
  }

  function getSpecificColorPairings(): array
  {
    return [
      "72c3b1" /* Cyan */        => "2cafcb",
      "982fff" /* Purple */      => "746db0",
      "e94190" /* Pink */        => "ec647a",
      "ffa500" /* Yellow */      => "fad965",
    ];
  }

  protected function setupNewGame($players, $options = [])
  {
    // Player counters
    foreach ($this->playerCounters as $counterName) {
      $initialValue = str_starts_with($counterName, "distance") ? 1 : 0;
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
