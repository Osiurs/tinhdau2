<?php
// Registry mapping report types to header template files.
// Root folder points to the provided absolute/relative path for template files.

return [
	'root' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'template_header',
	'map' => [
		'BC_TH' => 'sample_header_BC TH.xlsx',
		'BCTHANG' => 'sample_header_BCTHANG.xlsx',
		'DAUTON' => 'sample_header_DAUTON.xlsx',
		'IN_TINH_DAU' => 'sample_header_IN TINH DAU.xlsx',
	],
	// Optional default template used when a mapping is missing or file not found
	// Place file at template_header/_default/header.xlsx if you choose to use it
	'default' => '_default' . DIRECTORY_SEPARATOR . 'header.xlsx',
];


