<?php

namespace dexen\Cheap;

class Repository
{
	protected $dir;

	function __construct(string $dir)
	{
		$this->dir = $dir;
	}
}
