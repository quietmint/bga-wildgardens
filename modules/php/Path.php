<?php

declare(strict_types=1);

namespace Bga\Games\WildGardens;

class Path implements \JsonSerializable
{
	private Game $game;
	private array $forage = [];
	private array $spaces = [];
	private ?string $type = null;

	public function __construct(Game $game)
	{
		$this->game = $game;
		$this->load();
	}

	public function jsonSerialize(): array
	{
		return [
			'forage' => $this->forage,
			'spaces' => count($this->spaces),
			'type' => $this->type
		];
	}

	private function save()
	{
		$this->game->bga->globals->set('path', [
			'forage' => $this->forage,
			'type' => $this->type,
		]);
	}

	private function load()
	{
		$path = $this->game->bga->globals->get('path');
		if ($path) {
			$this->forage = $path['forage'];
			$this->type = $path['type'];
			$this->setupSpaces();
		}
	}

	public function setup()
	{
		$this->setupForage();
		$this->setupSpaces();
		$this->save();
	}

	private function setupForage()
	{
		$ingredients = [
			'aquatic' => 2,
			'bark' => 2,
			'flower' => 3,
			'fruit' => 3,
			'fungus' => 4,
			'leaf' => 3,
			'nut' => 4,
			'root' => 3
		];
		$this->forage = [];
		foreach ($ingredients as $ingredient => $count) {
			$this->forage = array_merge($this->forage, array_fill(0, $count, $ingredient));
		}
		shuffle($this->forage);
	}

	private function setupSpaces()
	{
		$optionPath = $this->game->getGameStateValue('optionPath');
		if ($this->type == null) {
			if ($optionPath > 1) {
				$optionPath = \bga_rand(0, 1);
			}
			$this->type = $optionPath == 0 ? "spring" : "fall";
		} else if ($optionPath == 3) {
			$this->type = $this->type == "spring" ? "fall" : "spring";
		}

		if ($this->type == "spring") {
			$this->setupSpace(1, false, [2, 11], [5, 6, 19]);
			$this->setupSpace(2, true, [3], [6, 7, 24]);
			$this->setupSpace(3, false, [4], [7, 22, 24]);
			$this->setupSpace(4, true, [5], [8, 20, 22]);
			$this->setupSpace(5, false, [6], [9, 10, 20]);
			$this->setupSpace(6, true, [7], [10, 11, 14]);
			$this->setupSpace(7, false, [8], [1, 11, 12]);
			$this->setupSpace(8, true, [9], [2, 12, 15]);
			$this->setupSpace(9, false, [10], [3, 15, 17]);
			$this->setupSpace(10, true, [1], [4, 17, 19]);
			$this->setupSpace(11, false, [12], [19, 23, 24]);
			$this->setupSpace(12, true, [13], [16, 18, 21]);
			$this->setupSpace(13, false, [6, 8], [13, 16, 20]);
		} else {
			$this->setupSpace(1, false, [2, 11], [3, 18, 19]);
			$this->setupSpace(2, true, [3], [4, 5, 19]);
			$this->setupSpace(3, false, [4, 8], [5, 6, 20]);
			$this->setupSpace(4, true, [5, 8], [7, 9, 21]);
			$this->setupSpace(5, false, [6, 9], [9, 10, 21]);
			$this->setupSpace(6, true, [7], [1, 16, 17]);
			$this->setupSpace(7, false, [1], [2, 3, 18]);
			$this->setupSpace(8, false, [9], [6, 7, 8]);
			$this->setupSpace(9, false, [10], [15, 16, 17]);
			$this->setupSpace(10, true, [1, 11], [14, 15, 18]);
			$this->setupSpace(11, false, [12], [13, 23, 24]);
			$this->setupSpace(12, true, [13], [12, 13, 23]);
			$this->setupSpace(13, false, [6, 14], [11, 12, 22]);
			$this->setupSpace(14, true, [5], [10, 11, 22]);
		}
	}

	private function setupSpace(int $space, bool $picnic, array $connections, array $forages): void
	{
		$this->spaces[$space] = new PathSpace($space, $picnic, $connections, $forages);
	}

	public function getForage(int $forage): string
	{
		return $this->forage[$forage - 1];
	}

	public function getSpace(int $space): PathSpace
	{
		return $this->spaces[$space];
	}

	public function getPlayerSpace(int $playerId): PathSpace
	{
		$space = $this->game->pathSpace->get($playerId);
		return $this->getSpace($space);
	}

	public function getPlayerMoves(int $playerId): array
	{
		// Compute possible moves keyed by distance
		$possibleDistance = [
			1 => [],
			2 => [],
			3 => [],
			4 => []
		];
		$occupied = array_values($this->game->pathSpace->getAll());
		// Start with the player's current space
		$queue = [$this->game->pathSpace->get($playerId) => 1];
		while (!empty($queue)) {
			$nextQueue = [];
			foreach ($queue as $qSpace => $qDistance) {
				foreach ($this->getSpace($qSpace)->connections as $connection) {
					if (in_array($connection, $occupied)) {
						$nextQueue[$connection] = $qDistance;
					} else {
						array_push($possibleDistance[$qDistance], $connection);
						if ($qDistance < 4) {
							$nextQueue[$connection] = $qDistance + 1;
						}
					}
				}
			}
			$queue = $nextQueue;
		}

		// Remove impossible distances (based on the player's remaining tokens)
		if (!$this->game->distanceWild->get($playerId)) {
			if (!$this->game->distance1->get($playerId)) {
				unset($possibleDistance[1]);
			}
			if (!$this->game->distance2->get($playerId)) {
				unset($possibleDistance[2]);
			}
			if (!$this->game->distance3->get($playerId)) {
				unset($possibleDistance[3]);
			}
			if (!$this->game->distance4->get($playerId)) {
				unset($possibleDistance[4]);
			}
		}

		// Flip the array so it's keyed by space
		$possibleMoves = [];
		foreach ($possibleDistance as $distance => $moves) {
			foreach ($moves as $move) {
				if (!array_key_exists($move, $possibleMoves)) {
					$possibleMoves[$move] = [];
				}
				if (!in_array($distance, $possibleMoves[$move])) {
					array_push($possibleMoves[$move], $distance);
				}
			}
		}
		return $possibleMoves;
	}
}
