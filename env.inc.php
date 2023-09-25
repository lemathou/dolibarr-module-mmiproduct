<?php

$modulename = 'MMIProduct';

if (!function_exists('price_format')) {
	function num_format($number, $round=2)
	{
		return number_format(round($number, $round), $round, ',', ' ');
	}
	function price_format($price, $round=2)
	{
		return num_format($price, $round).' €';
	}
	function percent_format($percent, $round=2)
	{
		return num_format($percent, $round).' %';
	}
}


function Median($Array) {
	return Quartile_50($Array);
}

function Quartile_25($Array) {
	return Quartile($Array, 0.25);
}

function Quartile_50($Array) {
	return Quartile($Array, 0.5);
}

function Quartile_75($Array) {
	return Quartile($Array, 0.75);
}

function Quartile($Array, $Quartile) {
	sort($Array);
	$pos = (count($Array) - 1) * $Quartile;

	$base = floor($pos);
	$rest = $pos - $base;

	if( isset($Array[$base+1]) ) {
		return $Array[$base] + $rest * ($Array[$base+1] - $Array[$base]);
	} else {
		return $Array[$base];
	}
}

function Average($Array) {
	return array_sum($Array) / count($Array);
}

function StdDev($Array) {
	if( count($Array) < 2 ) {
		return;
	}

	$avg = Average($Array);

	$sum = 0;
	foreach($Array as $value) {
		$sum += pow($value - $avg, 2);
	}

	return sqrt((1 / (count($Array) - 1)) * $sum);
}
