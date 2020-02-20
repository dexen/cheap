<?php

namespace dexen\Cheap\Config;

interface ConfigInterface
{
	function configTextValue(string $key) : ?string;
}
