<?php
namespace Opencart\Catalog\Controller\Extension\Altai\Event;
/**
 * Class AltText
 *
 * Puts the alt text written with Alt Text Generator AI into the images of the storefront.
 * It works on the finished HTML of every view, so it does not depend on the theme.
 *
 * @package Opencart\Catalog\Controller\Extension\Altai\Event
 */
class AltText extends \Opencart\System\Engine\Controller {
	/**
	 * Attributes that can hold the address of the picture. Lazy-loading themes keep the real
	 * address in a data attribute and put a placeholder in src.
	 */
	private const SOURCE_ATTRIBUTES = ['src', 'data-src', 'data-lazy-src', 'data-original', 'data-lazy'];

	/**
	 * @var array<int, array<string, string>>
	 */
	private static array $maps = [];

	/**
	 * Replace
	 *
	 * @param string       $route
	 * @param array<mixed> $args
	 * @param mixed        $output
	 *
	 * @return void
	 */
	public function replace(string &$route, array &$args, mixed &$output): void {
		if (!is_string($output) || stripos($output, '<img') === false || !$this->config->get('module_alt_text_generator_status')) {
			return;
		}

		$map = $this->map((int)$this->config->get('config_language_id'));

		if ($map) {
			$output = self::rewriteHtml($output, $map);
		}
	}

	/**
	 * Rewrite the alt attribute of every image whose picture has alt text
	 *
	 * @param string                $html
	 * @param array<string, string> $map alt text keyed by the image path without its extension, in lower case
	 *
	 * @return string
	 */
	public static function rewriteHtml(string $html, array $map): string {
		$result = preg_replace_callback('/<img\b[^>]*>/i', static fn(array $match): string => self::rewriteTag($match[0], $map), $html);

		return is_string($result) ? $result : $html;
	}

	/**
	 * The key an image path has in the map: no extension, lower case
	 *
	 * @param string $path path relative to the image folder
	 *
	 * @return string
	 */
	public static function key(string $path): string {
		return strtolower((string)preg_replace('/\.[A-Za-z0-9]+$/', '', $path));
	}

	/**
	 * The path of the original picture behind an address used in a page, or an empty string
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	public static function originalPath(string $url): string {
		$path = rawurldecode((string)parse_url(html_entity_decode($url, ENT_QUOTES, 'UTF-8'), PHP_URL_PATH));

		if (!preg_match('#(?:^|/)image/(.+)$#', $path, $found)) {
			return '';
		}

		// A resized copy lives in image/cache and has its size added to the file name.
		return (string)preg_replace('/-\d+x\d+(\.[A-Za-z0-9]+)$/', '$1', (string)preg_replace('#^cache/#', '', $found[1]));
	}

	/**
	 * The alt text of a language, keyed by the image path without its extension
	 *
	 * @param int $language_id
	 *
	 * @return array<string, string>
	 */
	private function map(int $language_id): array {
		if (!isset(self::$maps[$language_id])) {
			self::$maps[$language_id] = [];

			try {
				$query = $this->db->query("SELECT `image`, `alt` FROM `" . DB_PREFIX . "altai_image_alt` WHERE `language_id` = '" . $language_id . "'");

				foreach ($query->rows as $row) {
					self::$maps[$language_id][self::key($row['image'])] = $row['alt'];
				}
			} catch (\Throwable $e) {
				// The storefront must never break because of alt text.
			}
		}

		return self::$maps[$language_id];
	}

	/**
	 * @param string                $tag
	 * @param array<string, string> $map
	 *
	 * @return string
	 */
	private static function rewriteTag(string $tag, array $map): string {
		$alt = null;

		foreach (self::SOURCE_ATTRIBUTES as $attribute) {
			if (!preg_match('/\s' . preg_quote($attribute, '/') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $tag, $source)) {
				continue;
			}

			$path = self::originalPath($source[1] !== '' ? $source[1] : ($source[2] ?? ''));

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
			return (string)preg_replace_callback($pattern, static fn(): string => ' alt="' . $escaped . '"', $tag, 1);
		}

		return (string)preg_replace_callback('/^<img\b/i', static fn(): string => '<img alt="' . $escaped . '"', $tag, 1);
	}
}
