<?php
/**
 * Checks how the OpenCart 3 storefront rewrites image tags. Needs only PHP:  php tests/rewrite_test_oc3.php
 */
class Controller {
}

require __DIR__ . '/../oc3/upload/catalog/controller/extension/module/alt_text_generator_event.php';

$alt = 'HTC smartphone with weather app.';
$map = array();
foreach (array('catalog/demo/htc_touch_hd_1' => $alt, 'catalog/demo/camera' => 'Canon "EOS" camera & lens <new>') as $path => $text) {
	$map[ControllerExtensionModuleAltTextGeneratorEvent::key($path)] = $text;
}

$failed = 0;
foreach (require __DIR__ . '/cases.php' as $case) {
	list($name, $html, $expected) = $case;
	$actual = ControllerExtensionModuleAltTextGeneratorEvent::rewriteHtml($html, $map);
	if ($actual === $expected) {
		echo "ok    $name\n";
	} else {
		$failed++;
		echo "FAIL  $name\n  expected: $expected\n  actual:   $actual\n";
	}
}

echo $failed === 0 ? "\nOpenCart 3: all checks passed.\n" : "\nOpenCart 3: $failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);
