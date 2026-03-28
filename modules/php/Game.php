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

use Bga\Games\WildGardens\States\PlayerTurn;
use Bga\GameFramework\Components\Counters\PlayerCounter;

class Game extends \Bga\GameFramework\Table
{
  const INGREDIENTS = [
    'aquatic' => [
      'count' => 2
    ],
    'bark' => [
      'count' => 2
    ],
    'flower' => [
      'count' => 3
    ],
    'fruit' => [
      'count' => 3
    ],
    'fungus' => [
      'count' => 4
    ],
    'leaf' => [
      'count' => 3
    ],
    'nut' => [
      'count' => 4
    ],
    'root' => [
      'count' => 3
    ]
  ];

  public static array $CARD_TYPES;

  public PlayerCounter $playerEnergy;

  public function __construct()
  {
    parent::__construct();

    $this->playerEnergy = $this->bga->counterFactory->createPlayerCounter('energy');

    self::$CARD_TYPES = [
      1 => [
        'card_name' => clienttranslate('Troll'), // ...
      ],
      2 => [
        'card_name' => clienttranslate('Goblin'), // ...
      ],
      // ...
    ];

    /* example of notification decorator.
    // automatically complete notification args when needed
    $this->bga->notify->addDecorator(function(string $message, array $args) {
        if (isset($args['player_id']) && !isset($args['player_name']) && str_contains($message, '${player_name}')) {
            $args['player_name'] = $this->getPlayerNameById($args['player_id']);
        }
    
        if (isset($args['card_id']) && !isset($args['card_name']) && str_contains($message, '${card_name}')) {
            $args['card_name'] = self::$CARD_TYPES[$args['card_id']]['card_name'];
            $args['i18n'][] = ['card_name'];
        }
        
        return $args;
    });*/
  }

  public function getGameProgression()
  {
    // TODO: compute and return the game progression
    return 0;
  }

  public function upgradeTableDb($from_version) {}

  protected function getAllDatas(int $currentPlayerId): array
  {
    $result = [];
    $result['players'] = $this->getCollectionFromDb(
      'SELECT `player_id` AS `id`, `player_score` AS `score` FROM `player`'
    );
    // $this->playerEnergy->fillResult($result);

    $result['path'] = $this->bga->globals->get('path');

    return $result;
  }

  /**
   * This method is called only once, when a new game is launched. In this method, you must setup the game
   *  according to the game rules, so that the game is ready to be played.
   */
  protected function setupNewGame($players, $options = [])
  {
    $this->playerEnergy->initDb(array_keys($players), initialValue: 2);

    // Set the colors of the players with HTML color code. The default below is red/green/blue/orange/brown. The
    // number of colors defined here must correspond to the maximum number of players allowed for the gams.
    $gameinfos = $this->getGameinfos();
    $default_colors = $gameinfos['player_colors'];

    foreach ($players as $player_id => $player) {
      // Now you can access both $player_id and $player array
      $query_values[] = vsprintf("(%s, '%s', '%s')", [
        $player_id,
        array_shift($default_colors),
        addslashes($player['player_name']),
      ]);
    }

    // Create players based on generic information.
    //
    // NOTE: You can add extra field on player table in the database (see dbmodel.sql) and initialize
    // additional fields directly here.
    static::DbQuery(
      sprintf(
        'INSERT INTO `player` (`player_id`, `player_color`, `player_name`) VALUES %s',
        implode(',', $query_values)
      )
    );

    $this->reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
    $this->reloadPlayersBasicInfos();

    // Init global values with their initial values.

    // Init game statistics.
    //
    // NOTE: statistics used in this file must be defined in your `stats.inc.php` file.

    // Dummy content.
    // $this->tableStats->init('table_teststat1', 0);
    // $this->playerStats->init('player_teststat1', 0);

    // TODO: Setup the initial game situation here.
    $this->setupPath();

    // Activate first player once everything has been initialized and ready.
    $this->activeNextPlayer();

    return PlayerTurn::class;
  }

  public function setupPath()
  {
    $forage = [];
    foreach (self::INGREDIENTS as $k => $v) {
      $forage = array_merge($forage, array_fill(0, $v['count'], $k));
    }
    shuffle($forage);
    $path = [
      'type' => 'spring',
      'forage' => $forage
    ];
    $this->bga->globals->set('path', $path);
  }

  public function debug_setupPath()
  {
    $this->setupPath();
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
