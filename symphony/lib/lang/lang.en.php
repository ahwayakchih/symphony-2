<?php
	
	// Dictionary
	$dictionary = array();
	
	// Alphabetical:
	$transliterations = array(
		'/À/' => 'A', 		'/Á/' => 'A', 		'/Â/' => 'A', 		'/Ã/' => 'A', 		'/Ä/' => 'Ae', 		
		'/Å/' => 'A', 		'/Ā/' => 'A', 		'/Ą/' => 'A', 		'/Ă/' => 'A', 		'/Æ/' => 'Ae', 		
		'/Ç/' => 'C', 		'/Ć/' => 'C', 		'/Č/' => 'C', 		'/Ĉ/' => 'C', 		'/Ċ/' => 'C', 		
		'/Ď/' => 'D', 		'/Đ/' => 'D', 		'/Ð/' => 'D', 		'/È/' => 'E', 		'/É/' => 'E', 		
		'/Ê/' => 'E', 		'/Ë/' => 'E', 		'/Ē/' => 'E', 		'/Ę/' => 'E', 		'/Ě/' => 'E', 		
		'/Ĕ/' => 'E', 		'/Ė/' => 'E', 		'/Ĝ/' => 'G', 		'/Ğ/' => 'G', 		'/Ġ/' => 'G', 		
		'/Ģ/' => 'G', 		'/Ĥ/' => 'H', 		'/Ħ/' => 'H', 		'/Ì/' => 'I', 		'/Í/' => 'I', 		
		'/Î/' => 'I', 		'/Ï/' => 'I', 		'/Ī/' => 'I', 		'/Ĩ/' => 'I', 		'/Ĭ/' => 'I', 		
		'/Į/' => 'I', 		'/İ/' => 'I', 		'/Ĳ/' => 'Ij', 		'/Ĵ/' => 'J', 		'/Ķ/' => 'K', 		
		'/Ł/' => 'L', 		'/Ľ/' => 'L', 		'/Ĺ/' => 'L', 		'/Ļ/' => 'L', 		'/Ŀ/' => 'L', 		
		'/Ñ/' => 'N', 		'/Ń/' => 'N', 		'/Ň/' => 'N', 		'/Ņ/' => 'N', 		'/Ŋ/' => 'N', 		
		'/Ò/' => 'O', 		'/Ó/' => 'O', 		'/Ô/' => 'O', 		'/Õ/' => 'O', 		'/Ö/' => 'Oe', 		
		'/Ø/' => 'O', 		'/Ō/' => 'O', 		'/Ő/' => 'O', 		'/Ŏ/' => 'O', 		'/Œ/' => 'Oe', 		
		'/Ŕ/' => 'R', 		'/Ř/' => 'R', 		'/Ŗ/' => 'R', 		'/Ś/' => 'S', 		'/Š/' => 'S', 		
		'/Ş/' => 'S', 		'/Ŝ/' => 'S', 		'/Ș/' => 'S', 		'/Ť/' => 'T', 		'/Ţ/' => 'T', 		
		'/Ŧ/' => 'T', 		'/Ț/' => 'T', 		'/Ù/' => 'U', 		'/Ú/' => 'U', 		'/Û/' => 'U', 		
		'/Ü/' => 'Ue', 		'/Ū/' => 'U', 		'/Ů/' => 'U', 		'/Ű/' => 'U', 		'/Ŭ/' => 'U', 		
		'/Ũ/' => 'U', 		'/Ų/' => 'U', 		'/Ŵ/' => 'W', 		'/Ý/' => 'Y', 		'/Ŷ/' => 'Y', 		
		'/Ÿ/' => 'Y', 		'/Y/' => 'Y', 		'/Ź/' => 'Z', 		'/Ž/' => 'Z', 		'/Ż/' => 'Z', 		
		'/Þ/' => 'T', 		'/à/' => 'a', 		'/á/' => 'a', 		'/â/' => 'a', 		'/ã/' => 'a', 		
		'/ä/' => 'ae', 		'/å/' => 'a', 		'/ā/' => 'a', 		'/ą/' => 'a', 		'/ă/' => 'a', 		
		'/æ/' => 'ae', 		'/ç/' => 'c', 		'/ć/' => 'c', 		'/č/' => 'c', 		'/ĉ/' => 'c', 		
		'/ċ/' => 'c', 		'/ď/' => 'd', 		'/đ/' => 'd', 		'/ð/' => 'd', 		'/è/' => 'e', 		
		'/é/' => 'e', 		'/ê/' => 'e', 		'/ë/' => 'e', 		'/ē/' => 'e', 		'/ę/' => 'e', 		
		'/ě/' => 'e', 		'/ĕ/' => 'e', 		'/ė/' => 'e', 		'/ƒ/' => 'f', 		'/ĝ/' => 'g', 		
		'/ğ/' => 'g', 		'/ġ/' => 'g', 		'/ģ/' => 'g', 		'/ĥ/' => 'h', 		'/ħ/' => 'h', 		
		'/ì/' => 'i', 		'/í/' => 'i', 		'/î/' => 'i', 		'/ï/' => 'i', 		'/ī/' => 'i', 		
		'/ĩ/' => 'i', 		'/ĭ/' => 'i', 		'/į/' => 'i', 		'/ı/' => 'i', 		'/ĳ/' => 'ij', 		
		'/ĵ/' => 'j', 		'/ķ/' => 'k', 		'/ĸ/' => 'k', 		'/ł/' => 'l', 		'/ľ/' => 'l', 		
		'/ĺ/' => 'l', 		'/ļ/' => 'l', 		'/ŀ/' => 'l', 		'/ñ/' => 'n', 		'/ń/' => 'n', 		
		'/ň/' => 'n', 		'/ņ/' => 'n', 		'/ŉ/' => 'n', 		'/ŋ/' => 'n', 		'/ò/' => 'o', 		
		'/ó/' => 'o', 		'/ô/' => 'o', 		'/õ/' => 'o', 		'/ö/' => 'oe', 		'/ø/' => 'o', 		
		'/ō/' => 'o', 		'/ő/' => 'o', 		'/ŏ/' => 'o', 		'/œ/' => 'oe', 		'/ŕ/' => 'r', 		
		'/ř/' => 'r', 		'/ŗ/' => 'r', 		'/ú/' => 'u', 		'/û/' => 'u', 		'/ü/' => 'ue', 		
		'/ū/' => 'u', 		'/ů/' => 'u', 		'/ű/' => 'u', 		'/ŭ/' => 'u', 		'/ũ/' => 'u', 		
		'/ų/' => 'u', 		'/ŵ/' => 'w', 		'/ý/' => 'y', 		'/ÿ/' => 'y', 		'/ŷ/' => 'y', 		
		'/y/' => 'y', 		'/ž/' => 'z', 		'/ż/' => 'z', 		'/ź/' => 'z', 		'/þ/' => 't', 		
		'/ß/' => 'ss', 		'/ſ/' => 'ss' 
	);
	
	// Symbolic:
	$transliterations += array(
		'/\(/'	=> null,		'/\)/'	=> null,		'/,/'	=> null,
		'/–/'	=> '-',			'/－/'	=> '-',			'/„/'	=> '"',
		'/“/'	=> '"',			'/”/'	=> '"',			'/—/'	=> '-',
	);
	
	// Ampersands:
	$transliterations += array(
		'/^&(?!&)$/'	=> 'and',
		'/^&(?!&)/'		=> 'and-',
		'/&(?!&)&/'		=> '-and',
		'/&(?!&)/'		=> '-and-'
	);
	
