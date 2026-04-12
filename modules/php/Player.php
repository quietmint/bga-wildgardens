<?php

declare(strict_types=1);

namespace Bga\Games\WildGardens;

class Player
{
	public int $playerId;

	public function __construct(int $playerId)
	{
		$this->playerId = $playerId;
	}
}
