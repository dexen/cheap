<?php

namespace dexen\Cheap;

class Repository
{
	protected $Config;

	function __construct(string $work_tree = null, string $git_dir = null)
	{
		$this->Config = new Config($work_tree, $git_dir);
	}

	function Config() : Config
	{
		return $this->Config;
	}

	static
	function checkValidRepository(string $pathname)
	{
		if (!is_dir($pathname))
			throw new Exception(sprintf('not a git repository: not a directory: "%s"', $pathname));
		if (!file_exists($pathname .'/HEAD'))
			throw new Exception(sprintf('not a git repository: missing "%s"', 'HEAD'));
		if (!is_dir($pathname .'/objects'))
			throw new Exception(sprintf('not a git repository: missing "%s"', 'objects'));
	}
}
