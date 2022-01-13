<?php

function repo_hash_to_object_pn(string $hash) : string
{
	return 'objects/' .substr($hash, 0, 2) .'/' .substr($hash, 2);
}

function repo_blob_by_hash(string $hash) : string
{
	$rpn = repo_hash_to_object_pn($hash);
	$v = file_get_contents('.git/' .$rpn);
	if ($v === false) {
		printf('could not read object' ."\n");
		die(1); }
	return $v;
}

function pretty_print_blob(string $blob)
{
	echo zlib_decode($blob);
}
