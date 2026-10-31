<?php
/**
 * The cases both versions of the extension must pass: name, html in, html out.
 * Needs $alt and the map the runner builds.
 */
return [
	['resized copy in image/cache', '<img src="http://shop.test/image/cache/catalog/demo/htc_touch_hd_1-500x500.jpg" title="HTC" alt="HTC Touch HD" class="img-fluid"/>', '<img src="http://shop.test/image/cache/catalog/demo/htc_touch_hd_1-500x500.jpg" title="HTC" alt="' . $alt . '" class="img-fluid"/>'],
	['original file', '<img src="/image/catalog/demo/htc_touch_hd_1.jpg" alt="x">', '<img src="/image/catalog/demo/htc_touch_hd_1.jpg" alt="' . $alt . '">'],
	['relative address', '<img src="image/catalog/demo/htc_touch_hd_1.jpg" alt="x">', '<img src="image/catalog/demo/htc_touch_hd_1.jpg" alt="' . $alt . '">'],
	['address with a query string', '<img src="/image/catalog/demo/htc_touch_hd_1.jpg?v=3" alt="x">', '<img src="/image/catalog/demo/htc_touch_hd_1.jpg?v=3" alt="' . $alt . '">'],
	['webp copy of a jpg', '<img src="/image/cache/catalog/demo/htc_touch_hd_1-228x228.webp" alt="x">', '<img src="/image/cache/catalog/demo/htc_touch_hd_1-228x228.webp" alt="' . $alt . '">'],
	['upper case file name', '<img src="/image/catalog/demo/HTC_Touch_HD_1.JPG" alt="x">', '<img src="/image/catalog/demo/HTC_Touch_HD_1.JPG" alt="' . $alt . '">'],
	['lazy loading with data-src', '<img src="data:image/gif;base64,R0lGOD" data-src="/image/cache/catalog/demo/htc_touch_hd_1-228x228.jpg" alt="x" class="lazy">', '<img src="data:image/gif;base64,R0lGOD" data-src="/image/cache/catalog/demo/htc_touch_hd_1-228x228.jpg" alt="' . $alt . '" class="lazy">'],
	['no alt attribute', '<img src="/image/catalog/demo/htc_touch_hd_1.jpg" class="a">', '<img alt="' . $alt . '" src="/image/catalog/demo/htc_touch_hd_1.jpg" class="a">'],
	['empty alt attribute', '<img src="/image/catalog/demo/htc_touch_hd_1.jpg" alt="">', '<img src="/image/catalog/demo/htc_touch_hd_1.jpg" alt="' . $alt . '">'],
	['single quotes', "<img src='/image/catalog/demo/htc_touch_hd_1.jpg' alt='x'>", "<img src='/image/catalog/demo/htc_touch_hd_1.jpg' alt=\"$alt\">"],
	['alt before src', '<img alt="x" src="/image/catalog/demo/htc_touch_hd_1.jpg">', '<img alt="' . $alt . '" src="/image/catalog/demo/htc_touch_hd_1.jpg">'],
	['data-alt is left alone', '<img data-alt="keep" src="/image/catalog/demo/htc_touch_hd_1.jpg">', '<img alt="' . $alt . '" data-alt="keep" src="/image/catalog/demo/htc_touch_hd_1.jpg">'],
	['special characters are escaped', '<img src="/image/catalog/demo/camera.jpg" alt="x">', '<img src="/image/catalog/demo/camera.jpg" alt="Canon &quot;EOS&quot; camera &amp; lens &lt;new&gt;">'],
	['picture without alt text is untouched', '<img src="/image/cache/catalog/demo/other-228x228.jpg" alt="Other">', '<img src="/image/cache/catalog/demo/other-228x228.jpg" alt="Other">'],
	['logo is untouched', '<img src="/image/catalog/logo.png" alt="Shop">', '<img src="/image/catalog/logo.png" alt="Shop">'],
	['address outside the image folder is untouched', '<img src="https://cdn.test/photos/htc_touch_hd_1.jpg" alt="x">', '<img src="https://cdn.test/photos/htc_touch_hd_1.jpg" alt="x">'],
	['text and other tags are untouched', '<p>An image: htc_touch_hd_1.jpg</p><a href="/image/catalog/demo/htc_touch_hd_1.jpg">link</a>', '<p>An image: htc_touch_hd_1.jpg</p><a href="/image/catalog/demo/htc_touch_hd_1.jpg">link</a>'],
	['several images', '<img src="/image/catalog/demo/htc_touch_hd_1.jpg" alt="a"><img src="/image/catalog/demo/camera.jpg" alt="b">', '<img src="/image/catalog/demo/htc_touch_hd_1.jpg" alt="' . $alt . '"><img src="/image/catalog/demo/camera.jpg" alt="Canon &quot;EOS&quot; camera &amp; lens &lt;new&gt;">'],
	['encoded ampersand in the address', '<img src="/image/catalog/demo/htc_touch_hd_1.jpg?a=1&amp;b=2" alt="x">', '<img src="/image/catalog/demo/htc_touch_hd_1.jpg?a=1&amp;b=2" alt="' . $alt . '">'],
	['no images at all', '<div>Hello</div>', '<div>Hello</div>'],
];
