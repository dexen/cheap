<?php

namespace dexen\Cheap;

	# the derived configuration
	# taking into account the environment, the system & user ini files, and the repo's ini file
class Config implements Config\ConfigInterface
{
	protected $ConfigRepo;
	protected $work_tree;
	protected $git_dir;

	function __construct(string $work_tree = null, string $git_dir = null)
	{
		$this->work_tree = $work_tree;
		$this->git_dir = $git_dir;

		if ($this->work_tree !== null)
			if ($this->git_dir === null)
				$this->git_dir = $work_tree .'/.git';

		Repository::checkValidRepository($this->git_dir);

		$this->ConfigRepo = new Config\Repo($this->git_dir);
	}

	protected
	function repoIniFilePathname() : string
	{
	}

	function configTextValue(string $key) : ?string
	{
		if (strpos($key, '.') === false)
			throw new Exception(sprintf('key does not contain a section: "%s"', $key));

		return $this->ConfigRepo->configTextValue($key);
	}

	function configIntValue(string $key) : ?int
	{
		$value = $this->configTextValue($key);
		if ($value === null)
			return null;
		if ((string)((int)$value) === $value)
			return $value;
		throw new Exception(sprintf('malformed integer config: "%s" -> "%s"', $key, $value));
	}
}
