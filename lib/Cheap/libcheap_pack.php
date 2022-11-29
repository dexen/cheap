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

	# little-endian "size encoding"
function repo_pack_size_parse(string $content, int $offset)
{
	$len = 0;
	$shift = 0;
	do {
		$vv = unpack('C', $content, $offset)[1];
		$v = $vv & 0x7f;
		$offset += 1;
		$len += $v << $shift;
		$shift += 7;
	} while ($vv >= 128);
	return [ $offset, $len ];
}

	# big-endian "offset encoding", 7 bit value, upper bit "value continues"
	# seems to be undocumented in https://git-scm.com/docs/pack-format
	# idea taken from write_no_reuse_object() in git/builtin/pack-objects.c
#private
function pack_offset_parse(string $content, int $offset)
{
	$len = 0;
	$shift = 7;
	do {
		$len = $len << $shift;
		$vv = unpack('C', $content, $offset)[1];
		$add = ($vv >= 128);	# couldn't find this part in the docs
		$v = ($vv & 0x7f) + $add;
		$len += $v;
		++$offset;
	} while ($vv >= 128);
	return [ $offset, $len ];
}

#private
function pack_object_ofs_delta_instructions_decode(string $instructions, string $base_content) : array
{
	$ii = unpack('C', $instructions)[1];
	if ($ii === 0)
		throw new \Exception('unsupported: reserved instruction');
		# add new data
	if ($ii < 128) {
		$size = $ii;
		$fragment = substr($instructions, 1, $size);
		$remainder_instructions = substr($instructions, $size + 1); }
	else {
		$fields = $ii & 0x7f;
		$bs = 0;
		$offset = $size = 0;
		$a = unpack('C*', substr($instructions, 1, 7));
		if ($fields & (1<<0)) {
			++$bs;
			$offset += array_shift($a) << 0; }
		if ($fields & (1<<1)) {
			++$bs;
			$offset += array_shift($a) << 8; }
		if ($fields & (1<<2)) {
			++$bs;
			$offset += array_shift($a) << 16; }
		if ($fields & (1<<3)) {
			++$bs;
			$offset += array_shift($a) << 24; }
		if ($fields & (1<<4)) {
			++$bs;
			$size += array_shift($a) << 0; }
		if ($fields & (1<<5)) {
			++$bs;
			$size += array_shift($a) << 8; }
		if ($fields & (1<<6)) {
			++$bs;
			$size += array_shift($a) << 16; }
		$remainder_instructions = substr($instructions, $bs + 1);
		$fragment = substr($base_content, $offset, $size); }
	return [ $remainder_instructions, $fragment ];
}

function pack_object_ofs_delta_decode(string $pn, string $content, int $object_offset, int $data_offset, int $decompressed_size)
{
	$decoded = -1;
	$a = [];
	$parent_type = -1;
	$base_object_size = -1;
	$decoded_size = -1;

	[ $data_offset, $base_object_relative_offset ] = pack_offset_parse($content, $data_offset);
	$base_object_offset = $object_offset - $base_object_relative_offset;

	$ddata = zlib_decode(substr($content, $data_offset));
	if (strlen($ddata) !== $decompressed_size)
		throw new \Exception('encoded data length mismatch');

	$ddata_offset = 0;
	[ $ddata_offset, $base_object_size ] = repo_pack_size_parse($ddata, $ddata_offset);
	[ $ddata_offset, $decoded_size ] = repo_pack_size_parse($ddata, $ddata_offset);

	$instructions = substr($ddata, $ddata_offset);

	[ $parent_type, $parent_content ] = repo_pack_object_read($pn, $base_object_offset);

	while ($instructions !== '')
		[ $instructions, $a[] ] = pack_object_ofs_delta_instructions_decode($instructions, $parent_content);

	$decoded = implode($a);

	if (strlen($decoded) !== $decoded_size)
		throw new \Exception(sprintf('size mismatch (%d, %d)', strlen($decoded), $decoded_size));

	return [ $parent_type, $decoded ];
}

function repo_pack_object_read(string $pn, int $object_offset) : array
{
	$offset = $object_offset;
	$content = file_get_contents($pn);
	[ $offset, $size_and_type ] = repo_pack_size_parse($content, $offset);
	$vtype = ($size_and_type >> 4) & 0x07;
	$decompressed_size = (($size_and_type >> 7) << 4) + ($size_and_type & 0x0f);

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
		break;
	case 7:
		$type = 'ref_delta';
		break; }

	switch ($type) {
	case 'ofs_delta':
		[ $type, $decoded_content ] = pack_object_ofs_delta_decode($pn, $content, $object_offset, $offset, $decompressed_size);
		break;
	case 'ref_delta':
		throw new \Exception('unsupported: type ' .$type);
	case 'commit':
	case 'tree':
	case 'blob':
	case 'tag':
		$decoded_content = zlib_decode(substr($content, $offset));
		break;
	default:
		throw new \Exception(sprintf('unsupported type "%s"')); }
	return [ $type, $decoded_content ];
}
