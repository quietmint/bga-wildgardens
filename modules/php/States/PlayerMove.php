<?php

declare(strict_types=1);

namespace Bga\Games\WildGardens\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\WildGardens\Game;


class PlayerMove extends GameState
{
	function __construct(protected Game $game)
	{
		parent::__construct(
			$game,
			id: 10,
			type: StateType::ACTIVE_PLAYER,
			description: clienttranslate('${actplayer} must move'),
			descriptionMyTurn: clienttranslate('${you} must move'),
		);
	}

	public function getArgs(int $activePlayerId): array
	{
		return [
			'possible' => $this->game->path->getPlayerMoves($activePlayerId)
		];
	}

	#[PossibleAction]
	public function actMove(int $space, int $distance, int $activePlayerId, array $args)
	{
		if (!array_key_exists($space, $args['possible']) || !in_array($distance, $args['possible'][$space]['distances'])) {
			throw new UserException('Invalid move');
		}

		// Use action token
		$distanceCounter = "distance$distance";
		if ($this->game->$distanceCounter->get($activePlayerId) == 1) {
			$this->game->$distanceCounter->set($activePlayerId, 0, null);
		} else {
			throw new UserException('Invalid move');
		}

		// Move on path
		$this->game->pathSpace->set($activePlayerId, $space, null);
		$this->bga->notify->all('move', clienttranslate('${player_name} moves using ${distanceName}'), [
			'distance' => $distance,
			'distanceName' => $distance ?: "×",
			'player_id' => $activePlayerId,
			'player_name' => $this->game->getPlayerNameById($activePlayerId),
			'preserve' => ['player_id'],
			'space' => $space,
		]);

		return PlayerSkills::class;
	}

	function zombie(int $playerId)
	{
		return NextPlayer::class;
	}
}
