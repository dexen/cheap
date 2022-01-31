<?php

function repo_hash_to_object_pn(string $hash) : string
{
	return 'objects/' .substr($hash, 0, 2) .'/' .substr($hash, 2);
}

function repo_blob_from_loose(string $hash, string $pn) : ?array
{
	$v = file_get_contents('.git/' .$pn);
	if ($v === false) {
		printf('could not read object' ."\n");
		die(1); }
	return [ zlib_decode($v) ];
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

function repo_object_in_pack_by_hash(string $hash) : ?string
{
	$pn = null;
	foreach (repo_pack_index_list() as $pn) {
		td(repo_pack_index_has_hash_p($pn, $hash));
	}
	return $pn;
}

function repo_object_content_by_hash($hash) : string
{
	if ($pn = repo_object_in_loose_by_hash($hash))
		return repo_blob_from_loose($hash, $pn)[0];
	else if ($pn = repo_object_in_pack_by_hash($hash))
		return repo_object_from_pack($hash, $pn)[0];
	else
		throw new \Exception('object not found: ' .$hash);
}

function pretty_print_blob(string $blob)
{
	echo $blob;
}
