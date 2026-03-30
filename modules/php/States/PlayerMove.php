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
		if (!array_key_exists($space, $args['possible']) || !in_array($distance, $args['possible'][$space])) {
			throw new UserException('Invalid move');
		}

		$this->game->pathSpace->set($activePlayerId, $space, null);
		$this->bga->notify->all('move', clienttranslate('${player_name} moves to space ${space}'), [
			'player_id' => $activePlayerId,
			'player_name' => $this->game->getPlayerNameById($activePlayerId),
			'space' => $space
		]);

		return PlayerSkills::class;
	}

	function zombie(int $playerId)
	{
		return NextPlayer::class;
	}
}
