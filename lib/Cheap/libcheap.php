<?php

namespace dexen\Cheap;

require __DIR__ .'/' .'libcheap_pack.php';

function repo_hash_to_object_pn(string $hash) : string
{
	return 'objects/' .substr($hash, 0, 2) .'/' .substr($hash, 2);
}

function repo_bhash_length() { return 20; }

function repo_hash_length() { return 40; }

	/* [ type, content ] */
function repo_blob_from_loose(string $hash, string $pn) : ?array
{
	$v = file_get_contents('.git/' .$pn);
	if ($v === false) {
		printf('could not read object' ."\n");
		die(1); }
	$vv = zlib_decode($v);
	if (sha1($vv) !== $hash)
		throw new \Exception('malformed object: hash does not match');
	[ $header, $content ] = explode("\x00", $vv, 2);
	[ $type, $len ] = explode(' ', $header);
	$len = (int)$len;
	if ($len < 0)
		throw new \Exception('malformed object: negative length');
	if (strlen($content) !== $len)
		throw new \Exception('malformed object: len <> len');
	else
		return [ $type, $content ];
}

function repo_loose_hash_search(string $short_hash) : array
{
	if (strlen($short_hash) < 2)
		throw new \Exception('unsupported: hash fragment too short');
	$pat = '.git/' .'objects/' .substr($short_hash, 0, 2) .'/' .substr($short_hash, 2) .'*';
	$a = glob($pat, GLOB_MARK | GLOB_NOSORT | GLOB_NOESCAPE | GLOB_ERR);
	if ($a === false)
		return [];
	return array_map(
		fn($str) => substr(str_replace('/', '', $str), -repo_hash_length()),
		$a );
}

	# pathname
function repo_object_in_loose_by_hash(string $hash) : ?string
{
	$rpn = repo_hash_to_object_pn($hash);
	if (file_exists('.git/' .$rpn))
		return $rpn;
	else
		return null;
}

function repo_pn_absolute(string $rpn) : string
{
	return '.git/' .$rpn;
}

function repo_pack_index_list() : array
{
	return glob(repo_pn_absolute('objects/pack/*.idx'), GLOB_NOSORT | GLOB_ERR);
}

function repo_object_in_pack_by_hash(string $hash) : ?array
{
	$pn = null;
	foreach (repo_pack_index_list() as $pn)
		if ($rcd = repo_pack_index_hash_lookup($pn, $hash))
			return $rcd;
	return null;
}

	/* [ type, content ] */
function repo_object_content_by_hash($hash) : array
{
	if ($pn = repo_object_in_loose_by_hash($hash))
		return repo_blob_from_loose($hash, $pn);
	else if ($rcd = repo_object_in_pack_by_hash($hash))
		return repo_pack_object_read($rcd[1], $rcd[2]);
	else
		throw new \Exception('object not found: ' .$hash);
}

function pretty_print_blob(array $rcd)
{
	echo $rcd[1];
}

function repo_object_name_resolution_short_hash(string $short_hash) : array
{
# FIXME search also in loose objects
	$ret = repo_loose_hash_search($short_hash);
	foreach (repo_pack_index_list() as $pn)
		$ret = array_merge(
			$ret,
			repo_pack_index_hash_search($pn, $short_hash) );
	return $ret;
}
