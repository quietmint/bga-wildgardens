<?php

declare(strict_types=1);

namespace Bga\Games\WildGardens\States;

use Bga\GameFramework\NotificationMessage;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\GameFramework\UserException;
use Bga\Games\WildGardens\Game;

class PlayerSkills extends GameState
{
	function __construct(protected Game $game)
	{
		parent::__construct(
			$game,
			id: 11,
			type: StateType::ACTIVE_PLAYER,
			description: clienttranslate('${actplayer} may activate skills'),
			descriptionMyTurn: clienttranslate('${you} may activate skills'),
		);
	}

	public function getArgs(int $activePlayerId): array
	{
		$space = $this->game->path->getPlayerSpace($activePlayerId);
		return [
			'forages' => $space->forages
		];
	}

	#[PossibleAction]
	public function actForage(int $forage, int $activePlayerId, array $args)
	{
		if (!in_array($forage, $args['forages'])) {
			throw new UserException('Invalid move');
		}

		$ingredient = $this->game->path->getForage($forage);
		$msg = new NotificationMessage(clienttranslate('${player_name} collects ${icon} ${ingredient}'), [
			'i18n' => ['ingredient'],
			'icon' => $this->game->getIngredientIcon($ingredient),
			'ingredient' => $this->game->getIngredientName($ingredient),
		]);
		$this->game->$ingredient->inc($activePlayerId, 1, $msg);
	}

	#[PossibleAction]
	public function actEndTurn(int $activePlayerId)
	{
		$this->bga->notify->all('endTurn', clienttranslate('${player_name} ends their turn'), [
			'player_id' => $activePlayerId,
			'player_name' => $this->game->getPlayerNameById($activePlayerId)
		]);
		return NextPlayer::class;
	}

	function zombie(int $playerId)
	{
		return $this->actEndTurn($playerId);
	}
}
