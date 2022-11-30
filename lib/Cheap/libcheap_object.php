<?php

namespace dexen\Cheap;

function object_commit_parents(string $commit) : array
{
	$pos = strpos($commit, "\n\n");
	if ($pos === false)
		throw new \Exception('malformed commit: no message part found');
	else
		$str = substr($commit, 0, $pos);
	return array_values(
		array_map(
			fn($v)=>substr($v, 7),
			array_filter(
				explode("\n", $str),
				fn($v)=>!strncmp($v, 'parent ', 7)
			) ) );
}

function object_commit_author(string $commit) : ?string
{
	$pos = strpos($commit, "\n\n");
	if ($pos === false)
		throw new \Exception('malformed commit: no message part found');
	else
		$str = substr($commit, 0, $pos);
	$a = array_map(
		fn($v)=>substr($v, 7),
		array_filter(
			explode("\n", $str),
			fn($v)=>!strncmp($v, 'author ', 7)
		) );
	switch (count($a)) {
	case 0:
		throw new \Exception('malformed commit: no author name');
		return null;
	case 1:
		return array_shift($a);
	default:
		throw new \Exception('malformed commit: multiple author names'); }
}

function object_commit_author_date(string $commit) : ?string
{
	$pos = strpos($commit, "\n\n");
	if ($pos === false)
		throw new \Exception('malformed commit: no message part found');
	else
		$str = substr($commit, 0, $pos);
	$a = array_map(
		fn($v)=>substr($v, 7),
		array_filter(
			explode("\n", $str),
			fn($v)=>!strncmp($v, 'author ', 7)
		) );
	switch (count($a)) {
	case 0:
		throw new \Exception('malformed commit: no author name');
		return null;
	case 1:
		return array_shift($a);
	default:
		throw new \Exception('malformed commit: multiple author names'); }
}

function object_commit_message(string $commit) : ?string
{
	$pos = strpos($commit, "\n\n");
	if ($pos === false)
		throw new \Exception('malformed commit: no message part found');
	else
		return substr($commit, $pos+2);
}

function object_commit_message_abbreviated(string $commit) : ?string
{
	$pos = strpos($commit, "\n\n");
	if ($pos === false)
		throw new \Exception('malformed commit: no message part found');
	else
		$msg = substr($commit, $pos+2);
	return explode("\n", $msg)[0];
}
