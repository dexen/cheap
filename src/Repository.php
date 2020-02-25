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

	protected static
	function _checkValidRepository(string $pathname) : ?string
	{
		if (!is_dir($pathname))
			return sprintf('not a git repository: not a directory: "%s"', $pathname);
		if (!file_exists($pathname .'/HEAD'))
			return sprintf('not a git repository: missing "%s"', 'HEAD');
		if (!is_dir($pathname .'/objects'))
			return sprintf('not a git repository: missing "%s"', 'objects');
		return null;
	}

	static
	function checkValidRepository(string $pathname)
	{
		$error = static::_checkValidRepository($pathname);
		if ($error !== null)
			throw new Exception($error);
	}

	static
	function fromCwd()
	{
		$dir = getcwd();
		$a = explode('/', $dir);
		while ($a) {
			$candidate = implode('/', $a);
				# usual scenario
			if (static::_checkValidRepository($candidate .'/.git') === null)
				return new static($candidate);
				# bare repo
			if (static::_checkValidRepository($candidate) === null)
				return new static(null, $candidate);
			array_pop($a); }
		throw new Exception(sprintf('not a git repository (or any of the parent directories): "%s"', $dir));
	}
}
