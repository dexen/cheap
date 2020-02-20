<?php

namespace dexen\Cheap\Config;

trait IniParser
{

	protected
	function interpretIniFile(string $pathname) : array
	{
		return $this->flattenConfigData($this->parseIniFile($pathname));
	}

	private
	function flattenConfigData(array $data) : array
	{
		$ret = [];

		$FS = function($section_header, $section_data) use(&$ret)
		{
			if (strpos($section_header, ' "') === false)
				$prefix = strtolower($section = $section_header) .'.';
			else {
				[ $section, $remainder ] = explode(' "', $section_header);
				$subsection = str_replace(
					'\\\\', '\\',
					str_replace(
						'\\"', '"',
						trim($remainder, '"') ) );
				$prefix = strtolower($section) .'.' .$subsection .'.'; }

			foreach ($section_data as $key => $value)
				$ret[$prefix .$key] = $value;
		};

		foreach ($data as $section => $section_data)
			$FS($section, $section_data);

		return $ret;
	}

	private
	function parseIniFile(string $pathname) : array
	{
		if (file_exists($pathname))
			return $this->parseIniString(file_get_contents($pathname));
		else
			throw new Exception(sprintf('config file not found: "%s"', $pathname));
	}

	private
	function parseIniString(string $str) : array
	{
		return parse_ini_string($str, $process_sections = true, INI_SCANNER_RAW);
	}
}
