<?php
/**
 * Puts the alt text written with Alt Text Generator AI into the images of the storefront.
 * It works on the finished HTML of every view, so it does not depend on the theme.
 */
class ControllerExtensionModuleAltTextGeneratorEvent extends Controller {
	/**
	 * Attributes that can hold the address of the picture. Lazy-loading themes keep the real
	 * address in a data attribute and put a placeholder in src.
	 */
	private static $sourceAttributes = array('src', 'data-src', 'data-lazy-src', 'data-original', 'data-lazy');

	private static $maps = array();

	/**
	 * Called after every view is rendered. It changes the output in place and returns nothing,
	 * because in OpenCart 3 a returned value would stop the other handlers.
	 */
	public function replace(&$route, &$data, &$output) {
		if (!is_string($output) || stripos($output, '<img') === false || !$this->config->get('module_alt_text_generator_status')) {
			return;
		}

		$map = $this->map((int)$this->config->get('config_language_id'));

		if ($map) {
			$output = self::rewriteHtml($output, $map);
		}
	}

	/**
	 * Rewrites the alt attribute of every image whose picture has alt text.
	 *
	 * @param string $html
	 * @param array  $map  alt text keyed by the image path without its extension, in lower case
	 *
	 * @return string
	 */
	public static function rewriteHtml($html, array $map) {
		$result = preg_replace_callback('/<img\b[^>]*>/i', function ($match) use ($map) {
			return ControllerExtensionModuleAltTextGeneratorEvent::rewriteTag($match[0], $map);
		}, $html);

		return is_string($result) ? $result : $html;
	}

	/**
	 * The key an image path has in the map: no extension, lower case.
	 */
	public static function key($path) {
		return strtolower((string)preg_replace('/\.[A-Za-z0-9]+$/', '', (string)$path));
	}

	/**
	 * The path of the original picture behind an address used in a page, or an empty string.
	 */
	public static function originalPath($url) {
		$path = rawurldecode((string)parse_url(html_entity_decode((string)$url, ENT_QUOTES, 'UTF-8'), PHP_URL_PATH));

		if (!preg_match('#(?:^|/)image/(.+)$#', $path, $found)) {
			return '';
		}

		// A resized copy lives in image/cache and has its size added to the file name.
		return (string)preg_replace('/-\d+x\d+(\.[A-Za-z0-9]+)$/', '$1', (string)preg_replace('#^cache/#', '', $found[1]));
	}

	public static function rewriteTag($tag, array $map) {
		$alt = null;

		foreach (self::$sourceAttributes as $attribute) {
			if (!preg_match('/\s' . preg_quote($attribute, '/') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $tag, $source)) {
				continue;
			}

			$path = self::originalPath($source[1] !== '' ? $source[1] : (isset($source[2]) ? $source[2] : ''));

			if ($path !== '' && isset($map[self::key($path)])) {
				$alt = $map[self::key($path)];

				break;
			}
		}

		if ($alt === null) {
			return $tag;
		}

		$escaped = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
		$pattern = '/\salt\s*=\s*(?:"[^"]*"|\'[^\']*\')/i';

		if (preg_match($pattern, $tag)) {
			return (string)preg_replace_callback($pattern, function () use ($escaped) {
				return ' alt="' . $escaped . '"';
			}, $tag, 1);
		}

		return (string)preg_replace_callback('/^<img\b/i', function () use ($escaped) {
			return '<img alt="' . $escaped . '"';
		}, $tag, 1);
	}

	/**
	 * The alt text of a language, keyed by the image path without its extension.
	 */
	private function map($language_id) {
		if (!isset(self::$maps[$language_id])) {
			self::$maps[$language_id] = array();

			try {
				$query = $this->db->query("SELECT `image`, `alt` FROM `" . DB_PREFIX . "altai_image_alt` WHERE `language_id` = '" . (int)$language_id . "'");

				foreach ($query->rows as $row) {
					self::$maps[$language_id][self::key($row['image'])] = $row['alt'];
				}
			} catch (Exception $e) {
				// The storefront must never break because of alt text.
			} catch (Throwable $e) {
				// Same, for errors raised by newer PHP versions.
			}
		}

		return self::$maps[$language_id];
	}
}
