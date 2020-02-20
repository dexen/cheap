<?php

namespace dexen\Cheap;

class Textual
{
	protected $Repo;

	function __construct(Repository $Repo)
	{
		$this->Repo = $Repo;
	}

	function status() : string
	{
		$ret = '';
		if (false)
			$ret .= sprintf('On branch %s');
		else
			$ret .= sprintf('HEAD detached at %s', $this->Repo->buildAbbreviatedHash('foobarbaz'));
		return $ret;
	}

	function config(string $key) : string
	{
		return $this->Repo->Config()->configTextValue($key) ?? '';
	}
}
