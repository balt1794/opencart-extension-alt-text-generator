<?php
/**
 * Checks how the storefront rewrites image tags. Needs only PHP:  php tests/rewrite_test.php
 */
namespace Opencart\System\Engine {
	class Controller {
	}
}

namespace {
	require __DIR__ . '/../catalog/controller/event/alt_text.php';

	use Opencart\Catalog\Controller\Extension\Altai\Event\AltText;

	$map = [];
	foreach (['catalog/demo/htc_touch_hd_1' => 'HTC smartphone with weather app.', 'catalog/demo/camera' => 'Canon "EOS" camera & lens <new>'] as $path => $alt) {
		$map[AltText::key($path)] = $alt;
	}

	$failed = 0;
	$check = static function (string $name, string $html, string $expected) use ($map, &$failed): void {
		$actual = AltText::rewriteHtml($html, $map);
		if ($actual === $expected) {
			echo "ok    $name\n";
		} else {
			$failed++;
			echo "FAIL  $name\n  expected: $expected\n  actual:   $actual\n";
		}
	};

	$alt = 'HTC smartphone with weather app.';

	$check('resized copy in image/cache', '<img src="http://shop.test/image/cache/catalog/demo/htc_touch_hd_1-500x500.jpg" title="HTC" alt="HTC Touch HD" class="img-fluid"/>', '<img src="http://shop.test/image/cache/catalog/demo/htc_touch_hd_1-500x500.jpg" title="HTC" alt="' . $alt . '" class="img-fluid"/>');
	$check('original file', '<img src="/image/catalog/demo/htc_touch_hd_1.jpg" alt="x">', '<img src="/image/catalog/demo/htc_touch_hd_1.jpg" alt="' . $alt . '">');
	$check('relative address', '<img src="image/catalog/demo/htc_touch_hd_1.jpg" alt="x">', '<img src="image/catalog/demo/htc_touch_hd_1.jpg" alt="' . $alt . '">');
	$check('address with a query string', '<img src="/image/catalog/demo/htc_touch_hd_1.jpg?v=3" alt="x">', '<img src="/image/catalog/demo/htc_touch_hd_1.jpg?v=3" alt="' . $alt . '">');
	$check('webp copy of a jpg', '<img src="/image/cache/catalog/demo/htc_touch_hd_1-228x228.webp" alt="x">', '<img src="/image/cache/catalog/demo/htc_touch_hd_1-228x228.webp" alt="' . $alt . '">');
	$check('upper case file name', '<img src="/image/catalog/demo/HTC_Touch_HD_1.JPG" alt="x">', '<img src="/image/catalog/demo/HTC_Touch_HD_1.JPG" alt="' . $alt . '">');
	$check('lazy loading with data-src', '<img src="data:image/gif;base64,R0lGOD" data-src="/image/cache/catalog/demo/htc_touch_hd_1-228x228.jpg" alt="x" class="lazy">', '<img src="data:image/gif;base64,R0lGOD" data-src="/image/cache/catalog/demo/htc_touch_hd_1-228x228.jpg" alt="' . $alt . '" class="lazy">');
	$check('no alt attribute', '<img src="/image/catalog/demo/htc_touch_hd_1.jpg" class="a">', '<img alt="' . $alt . '" src="/image/catalog/demo/htc_touch_hd_1.jpg" class="a">');
	$check('empty alt attribute', '<img src="/image/catalog/demo/htc_touch_hd_1.jpg" alt="">', '<img src="/image/catalog/demo/htc_touch_hd_1.jpg" alt="' . $alt . '">');
	$check('single quotes', "<img src='/image/catalog/demo/htc_touch_hd_1.jpg' alt='x'>", "<img src='/image/catalog/demo/htc_touch_hd_1.jpg' alt=\"$alt\">");
	$check('alt before src', '<img alt="x" src="/image/catalog/demo/htc_touch_hd_1.jpg">', '<img alt="' . $alt . '" src="/image/catalog/demo/htc_touch_hd_1.jpg">');
	$check('data-alt is left alone', '<img data-alt="keep" src="/image/catalog/demo/htc_touch_hd_1.jpg">', '<img alt="' . $alt . '" data-alt="keep" src="/image/catalog/demo/htc_touch_hd_1.jpg">');
	$check('special characters are escaped', '<img src="/image/catalog/demo/camera.jpg" alt="x">', '<img src="/image/catalog/demo/camera.jpg" alt="Canon &quot;EOS&quot; camera &amp; lens &lt;new&gt;">');
	$check('picture without alt text is untouched', '<img src="/image/cache/catalog/demo/other-228x228.jpg" alt="Other">', '<img src="/image/cache/catalog/demo/other-228x228.jpg" alt="Other">');
	$check('logo is untouched', '<img src="/image/catalog/logo.png" alt="Shop">', '<img src="/image/catalog/logo.png" alt="Shop">');
	$check('address outside the image folder is untouched', '<img src="https://cdn.test/photos/htc_touch_hd_1.jpg" alt="x">', '<img src="https://cdn.test/photos/htc_touch_hd_1.jpg" alt="x">');
	$check('text and other tags are untouched', '<p>An image: htc_touch_hd_1.jpg</p><a href="/image/catalog/demo/htc_touch_hd_1.jpg">link</a>', '<p>An image: htc_touch_hd_1.jpg</p><a href="/image/catalog/demo/htc_touch_hd_1.jpg">link</a>');
	$check('several images', '<img src="/image/catalog/demo/htc_touch_hd_1.jpg" alt="a"><img src="/image/catalog/demo/camera.jpg" alt="b">', '<img src="/image/catalog/demo/htc_touch_hd_1.jpg" alt="' . $alt . '"><img src="/image/catalog/demo/camera.jpg" alt="Canon &quot;EOS&quot; camera &amp; lens &lt;new&gt;">');
	$check('encoded ampersand in the address', '<img src="/image/catalog/demo/htc_touch_hd_1.jpg?a=1&amp;b=2" alt="x">', '<img src="/image/catalog/demo/htc_touch_hd_1.jpg?a=1&amp;b=2" alt="' . $alt . '">');
	$check('no images at all', '<div>Hello</div>', '<div>Hello</div>');

	echo $failed === 0 ? "\nAll checks passed.\n" : "\n$failed check(s) failed.\n";
	exit($failed === 0 ? 0 : 1);
}
