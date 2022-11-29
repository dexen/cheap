<?php

namespace dexen\Cheap;

# broadly based off of https://git-scm.com/docs/pack-format

function pack_string_is_index_v2_p(string $content) : bool
{
	$signature =
		"\377tOc"	# magic number
		."\x00\x00\x00\x02"; # v=2, 32bit, network byte order
	return (strncmp($content, $signature, strlen($signature)) === 0);
}

function pack_string_is_index_p(string $content) : bool
{
	return pack_string_is_index_v2_p($content);
}

function pack_string_is_index_v2_checksum_consistent_p(string $content) : bool
{
	$checksum_bin_len = 20;
	$content_sans_checksum = substr($content, 0, -$checksum_bin_len);
	$computed = sha1($content_sans_checksum);
	$content_checksum = substr($content, -$checksum_bin_len);
	$found = bin2hex($content_checksum);
	return ($computed = $found);
}

function pack_pathname_from_index_pathname(string $pn) : string
{
	$a = pathinfo($pn);
	if ($a['dirname'] === '.')
		return $a['filename'] .'.pack';
	else
		return $a['dirname'] .'/' .$a['filename'] .'.pack';
}

	/* array of hashes */
function repo_pack_index_hash_search(string $pn, string $short_hash) : array
{
	$ret = [];

	$content = file_get_contents($pn);
	if (!pack_string_is_index_p($content))
		throw new \Exception('file format error: not a pack index');
	if (!pack_string_is_index_v2_p($content))
		throw new \Exception('unsupported file format: not a v2 pack index');
	if (!pack_string_is_index_v2_checksum_consistent_p($content))
		throw new \Exception('file formart error: checksum does not match');

	$len_header = 2*4;
	$offset = $len_header;
	$fanout = unpack('N256', $content, $offset);
	if (count($fanout) !== 256)
		throw new \Exception('malformed pack index: wrong fanout table size');
	if (strlen($short_hash) < 2)
		throw new \Exception('unsupported: hash fragment too short');
	$v = hexdec(substr($short_hash, 0, 2));
	$len_fanout_table = count($fanout)*4;
	$offset += $len_fanout_table;

		# note: the unpack() returns base-1 array
	$count_objects = $fanout[count($fanout)];
	if ($count_objects <= 0)
		throw new \Exception('malformed pack index: fanout underflow');

	$count_hash_table = $count_objects;
	$len_hash_table = $count_hash_table*repo_bhash_length();
	$search_start = $offset;
	$search_end = $search_start +$len_hash_table;
	$offset += $len_hash_table;

	for ($n = 0; $n < $count_hash_table; ++$n) {
		$bcandidate = substr($content, $search_start+$n*repo_bhash_length(), repo_bhash_length());
		$candidate = bin2hex($bcandidate);
		if (strncmp($candidate, $short_hash, strlen($short_hash)) === 0)
			$ret[] = $candidate; }

	return $ret;
}

	/* a record */
function repo_pack_index_hash_lookup(string $pn, string $hash) : ?array
{
	$bhash = hex2bin($hash);
	$content = file_get_contents($pn);
	if (!pack_string_is_index_p($content))
		throw new \Exception('file format error: not a pack index');
	if (!pack_string_is_index_v2_p($content))
		throw new \Exception('unsupported file format: not a v2 pack index');
	if (!pack_string_is_index_v2_checksum_consistent_p($content))
		throw new \Exception('file formart error: checksum does not match');

	$len_header = 2*4;
	$offset = $len_header;
	$fanout = unpack('N256', $content, $offset);
	if (count($fanout) !== 256)
		throw new \Exception('malformed pack index: wrong fanout table size');
		# note: the unpack() returns base-1 array
	$v = unpack('C', $bhash)[1];
	$len_fanout_table = count($fanout)*4;
	$offset += $len_fanout_table;

		# note: the unpack() returns base-1 array
	$count_objects = $fanout[count($fanout)];
	if ($count_objects <= 0)
		throw new \Exception('malformed pack index: fanout underflow');

	$count_hash_table = $count_objects;
	$len_hash_table = $count_hash_table*repo_bhash_length();
	$search_start = $offset;
	$search_end = $search_start +$len_hash_table;
	$offset += $len_hash_table;

	$match_num = null;
	for ($n = 0; $n < $count_hash_table; ++$n)
		if (substr($content, $search_start+$n*repo_bhash_length(), repo_bhash_length()) === $bhash)
			$match_num = $n;
	if ($match_num === null)
		return null;

	$count_checksums = $count_objects;
	$len_checksums = $count_checksums * 4;
	$offset += $len_checksums;

	$v = unpack('N', $content, $offset + $match_num*4)[1];
	if ($v >= (1<<31)) {
		$v = $v - (1<<31);
#FIXME
#		$v = /* lookup ~~ */;
		throw new \Exception('not implemented: access beyond 2GB throguh a 64bit offset'); }

	$count_offset_table = $count_objects;
	$len_offset_table = $count_offset_table * 4;
	$offset += $len_offset_table;

	return [ $pn, pack_pathname_from_index_pathname($pn), $v ];
}

function repo_pack_object_read(string $pn, int $object_offset) : array
{
	$offset = $object_offset;
	$content = file_get_contents($pn);
	$vv = unpack('C', $content, $offset)[1];
	$vvh = dechex($vv);
	$v = $vv & 0x7f;
	$vtype = $v >> 4;
	switch ($vtype) {
	case 1:
		$type = 'commit';
		break;
	case 2:
		$type = 'tree';
		break;
	case 3:
		$type = 'blob';
		break;
	case 4:
		$type = 'tag';
		break;
	case 0:
		throw new \Exception('malformed: invalid type ' .$vtype);
	case 5:
		throw new \Exception('unsupported: type ' .$vtype);
	case 6:
		$type = 'ofs_delta';
		throw new \Exception('FIXME: not implemented: type ' .$vtype);
	case 7:
		$type = 'ref_delta';
		throw new \Exception('FIXME: not implemented: type ' .$vtype); }
	$len = $v & 0x0f;
	$offset += 1;
	$shift = 4;
	while ($vv >= 128) {
		$vv = unpack('C', $content, $offset)[1];
		$v = $vv & 0x7f;
		$offset += 1;
		$len += $v << $shift;
		$shift += 7; }
	return [ $type, zlib_decode(substr($content, $offset, $len)) ];
}
