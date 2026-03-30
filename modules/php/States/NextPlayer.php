<?php

declare(strict_types=1);

namespace Bga\Games\WildGardens\States;

use Bga\GameFramework\StateType;
use Bga\Games\WildGardens\Game;

class NextPlayer extends \Bga\GameFramework\States\GameState
{
	function __construct(protected Game $game)
	{
		parent::__construct(
			$game,
			id: 90,
			type: StateType::GAME,
			updateGameProgression: true,
		);
	}

	function onEnteringState(int $activePlayerId)
	{
		$this->game->giveExtraTime($activePlayerId);
		$this->game->activeNextPlayer();

		$gameEnd = false;
		if ($gameEnd) {
			return EndScore::class;
		} else {
			return PlayerMove::class;
		}
	}
}
