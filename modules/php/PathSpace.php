<?php

declare(strict_types=1);

namespace Bga\Games\WildGardens;

class PathSpace
{
	public int $space;
	public bool $picnic;
	public array $connections;
	public array $forages;

	public function __construct(int $space, bool $picnic, array $connections, array $forages)
	{
		$this->space = $space;
		$this->picnic = $picnic;
		$this->connections = $connections;
		$this->forages = $forages;
	}
}
