<?php

namespace dexen\Cheap;

class Repository
{
	protected $dir;

	function __construct(string $dir)
	{
		$this->dir = $dir;
		if (!is_dir($this->dir))
			throw new Exception(sprintf('repository pathname not a directory: "%s"', $this->dir));
	}
}
