<?php

namespace dexen\Cheap\Config;

	# configuration file specific to this repository
class Repo implements ConfigInterface
{
	use IniParser;

	protected $git_dir;
	protected $data;

	function __construct(string $git_dir)
	{
		$this->git_dir = $git_dir;
		$this->data = $this->interpretIniFile($git_dir .'/config');
	}

	function configTextValue(string $key) : ?string
	{
		return $this->data[$key] ?? null;
	}
}
