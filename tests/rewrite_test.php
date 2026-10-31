<?php
/**
 * Checks how the OpenCart 4 storefront rewrites image tags. Needs only PHP:  php tests/rewrite_test.php
 */
namespace Opencart\System\Engine {
	class Controller {
	}
}

namespace {
	require __DIR__ . '/../oc4/catalog/controller/event/alt_text.php';

	use Opencart\Catalog\Controller\Extension\Altai\Event\AltText;

	$alt = 'HTC smartphone with weather app.';
	$map = [];
	foreach (['catalog/demo/htc_touch_hd_1' => $alt, 'catalog/demo/camera' => 'Canon "EOS" camera & lens <new>'] as $path => $text) {
		$map[AltText::key($path)] = $text;
	}

	$failed = 0;
	foreach (require __DIR__ . '/cases.php' as [$name, $html, $expected]) {
		$actual = AltText::rewriteHtml($html, $map);
		if ($actual === $expected) {
			echo "ok    $name\n";
		} else {
			$failed++;
			echo "FAIL  $name\n  expected: $expected\n  actual:   $actual\n";
		}
	}

	echo $failed === 0 ? "\nOpenCart 4: all checks passed.\n" : "\nOpenCart 4: $failed check(s) failed.\n";
	exit($failed === 0 ? 0 : 1);
}
