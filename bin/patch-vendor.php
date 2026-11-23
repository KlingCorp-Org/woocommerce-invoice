<?php
/**
 * Fixes class names that php-font-lib builds from strings, which Strauss cannot rewrite.
 * Run after Strauss: php bin/patch-vendor.php
 */
$file = __DIR__ . '/../vendor-prefixed/dompdf/php-font-lib/src/FontLib/TrueType/File.php';
$code = file_get_contents( $file );
$code = str_replace(
	array(
		'$class_parts = explode("\\\\", get_class($this));' . "\n" . '    return $class_parts[1];',
		'$class = "FontLib\\\\$type\\\\TableDirectoryEntry";',
	),
	array(
		'$class_parts = explode("\\\\", get_class($this));' . "\n" . '    return $class_parts[count($class_parts) - 2];',
		'$class = "OrderDocs\\\\Vendor\\\\FontLib\\\\$type\\\\TableDirectoryEntry";',
	),
	$code,
	$count
);
file_put_contents( $file, $code );
echo "patch-vendor: {$count} replacement(s)\n";
exit( 2 === $count ? 0 : 1 );
