<?php
namespace Opencart\Catalog\Controller\Extension\Altai\Event;
/**
 * Class AltText
 *
 * Puts the alt text written with Alt Text Generator AI into the images of the storefront.
 *
 * @package Opencart\Catalog\Controller\Extension\Altai\Event
 */
class AltText extends \Opencart\System\Engine\Controller {
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

		if (!$map) {
			return;
		}

		$result = preg_replace_callback('/<img\b[^>]*>/i', fn(array $match): string => $this->rewrite($match[0], $map), $output);

		if (is_string($result)) {
			$output = $result;
		}
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
	 * Rewrite the alt attribute of one image tag when alt text exists for its picture
	 *
	 * @param string                $tag
	 * @param array<string, string> $map
	 *
	 * @return string
	 */
	private function rewrite(string $tag, array $map): string {
		if (!preg_match('/\bsrc\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $tag, $source)) {
			return $tag;
		}

		$url = html_entity_decode($source[1] !== '' ? $source[1] : ($source[2] ?? ''), ENT_QUOTES, 'UTF-8');
		$path = rawurldecode((string)parse_url($url, PHP_URL_PATH));

		if (!preg_match('#/image/(.+)$#', $path, $found)) {
			return $tag;
		}

		// A resized copy lives in image/cache and has its size added to the file name.
		$relative = preg_replace('/-\d+x\d+(\.[A-Za-z0-9]+)$/', '$1', preg_replace('#^cache/#', '', $found[1]));
		$key = self::key((string)$relative);

		if (!isset($map[$key])) {
			return $tag;
		}

		$alt = htmlspecialchars($map[$key], ENT_QUOTES, 'UTF-8');
		$pattern = '/\balt\s*=\s*(?:"[^"]*"|\'[^\']*\')/i';

		if (preg_match($pattern, $tag)) {
			return (string)preg_replace_callback($pattern, static fn(): string => 'alt="' . $alt . '"', $tag, 1);
		}

		return (string)preg_replace_callback('/^<img\b/i', static fn(): string => '<img alt="' . $alt . '"', $tag, 1);
	}

	private static function key(string $path): string {
		return strtolower((string)preg_replace('/\.[A-Za-z0-9]+$/', '', $path));
	}
}
