<?php
namespace Opencart\Admin\Controller\Extension\Altai\Module;
/**
 * Class AltTextGenerator
 *
 * Writes SEO-friendly alt text for the images used in the shop and saves it for the storefront.
 *
 * @package Opencart\Admin\Controller\Extension\Altai\Module
 */
class AltTextGenerator extends \Opencart\System\Engine\Controller {
	private const ENDPOINT = 'https://alttextgeneratorai.com/api/opencart';
	private const ROUTE = 'extension/altai/module/alt_text_generator';
	private const MAX_SIDE = 1024;
	private const MAX_DATA_URL = 5500000;

	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language(self::ROUTE);

		$this->document->setTitle($this->language->get('heading_title'));

		$token = 'user_token=' . $this->session->data['user_token'];

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', $token)
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', $token . '&type=module')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link(self::ROUTE, $token)
		];

		$data['save'] = $this->url->link(self::ROUTE . '.save', $token);
		$data['url_images'] = $this->ajaxLink('.images', $token);
		$data['url_generate'] = $this->ajaxLink('.generate', $token);
		$data['url_apply'] = $this->ajaxLink('.apply', $token);
		$data['url_credits'] = $this->ajaxLink('.credits', $token);
		$data['back'] = $this->url->link('marketplace/extension', $token . '&type=module');

		$data['module_alt_text_generator_status'] = $this->config->get('module_alt_text_generator_status');
		$data['module_alt_text_generator_api_key'] = $this->config->get('module_alt_text_generator_api_key');
		$data['module_alt_text_generator_language'] = $this->config->get('module_alt_text_generator_language') ?: 'english';

		$this->load->model('extension/altai/module/alt_text_generator');

		$data['alt_languages'] = \Opencart\Admin\Model\Extension\Altai\Module\AltTextGenerator::LANGUAGES;

		$this->load->model('localisation/language');

		$data['store_languages'] = $this->model_localisation_language->getLanguages();
		$data['store_language_id'] = (int)$this->config->get('config_language_id');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view(self::ROUTE, $data));
	}

	/**
	 * Save the settings
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language(self::ROUTE);

		$json = [];

		if (!$this->user->hasPermission('modify', self::ROUTE)) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$settings = [
				'module_alt_text_generator_status'   => (int)($this->request->post['module_alt_text_generator_status'] ?? 0),
				'module_alt_text_generator_api_key'  => trim((string)($this->request->post['module_alt_text_generator_api_key'] ?? '')),
				'module_alt_text_generator_language' => preg_match('/^[a-z][a-z \-()]{1,40}$/i', (string)($this->request->post['module_alt_text_generator_language'] ?? '')) ? (string)$this->request->post['module_alt_text_generator_language'] : 'english'
			];

			// Keeps a custom address that was set for testing.
			if ($this->config->get('module_alt_text_generator_endpoint')) {
				$settings['module_alt_text_generator_endpoint'] = $this->config->get('module_alt_text_generator_endpoint');
			}

			$this->model_setting_setting->editSetting('module_alt_text_generator', $settings);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * The images used in the shop and their alt text
	 *
	 * @return void
	 */
	public function images(): void {
		$this->load->language(self::ROUTE);

		$json = [];

		if (!$this->user->hasPermission('access', self::ROUTE)) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$language_id = $this->languageId();

			$this->load->model('extension/altai/module/alt_text_generator');
			$this->load->model('tool/image');

			$alts = $this->model_extension_altai_module_alt_text_generator->getAlts($language_id);

			$json['images'] = [];
			$missing = 0;

			foreach ($this->model_extension_altai_module_alt_text_generator->getImages($language_id) as $path => $image) {
				$thumb = $this->model_tool_image->resize($path, 72, 72);

				// Files that are gone from the disk cannot be read, so they are left out.
				if (!$thumb) {
					continue;
				}

				$alt = $alts[$path] ?? '';

				if ($alt === '') {
					$missing++;
				}

				$json['images'][] = [
					'image'  => $path,
					'name'   => html_entity_decode($image['names'] ? implode(', ', $image['names']) : basename($path), ENT_QUOTES, 'UTF-8'),
					'source' => $image['source'],
					'thumb'  => $thumb,
					'alt'    => $alt
				];
			}

			$json['total'] = count($json['images']);
			$json['missing'] = $missing;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Write alt text for one image
	 *
	 * @return void
	 */
	public function generate(): void {
		$this->load->language(self::ROUTE);

		$json = [];

		if (!$this->user->hasPermission('modify', self::ROUTE)) {
			$json['error'] = $this->language->get('error_permission');
		}

		$api_key = trim((string)$this->config->get('module_alt_text_generator_api_key'));

		if (!$json && $api_key === '') {
			$json['error'] = $this->language->get('error_api_key');
			$json['fatal'] = true;
		}

		$file = '';

		if (!$json) {
			$file = $this->realImagePath((string)($this->request->post['image'] ?? ''));

			if ($file === '') {
				$json['error'] = $this->language->get('error_image');
			}
		}

		$image = '';

		if (!$json) {
			$image = $this->toDataUrl($file);

			if ($image === '') {
				$json['error'] = $this->language->get('error_unsupported');
			}
		}

		if (!$json) {
			$language = (string)($this->request->post['language'] ?? 'english');

			if (!preg_match('/^[a-z][a-z \-()]{1,40}$/i', $language)) {
				$language = 'english';
			}

			$result = $this->call($this->endpoint(), ['image' => $image, 'language' => $language, 'wpkey' => $api_key]);

			if ($result['status'] === 0) {
				$json['error'] = $this->language->get('error_unreachable');
			} elseif ($result['status'] === 401) {
				$json['error'] = $this->language->get('error_invalid_key');
				$json['fatal'] = true;
			} elseif ($result['status'] === 402) {
				$json['error'] = $this->language->get('error_no_credits');
				$json['fatal'] = true;
			} elseif ($result['status'] >= 400 || $result['body'] === '') {
				$json['error'] = ($result['body'] !== '' && strlen($result['body']) < 160 && $result['body'][0] !== '<') ? $result['body'] : $this->language->get('error_generation');
			} else {
				$json['alt'] = $result['body'];
				$json['credits'] = $result['credits'];
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Save the alt text for the storefront
	 *
	 * @return void
	 */
	public function apply(): void {
		$this->load->language(self::ROUTE);

		$json = [];

		if (!$this->user->hasPermission('modify', self::ROUTE)) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('extension/altai/module/alt_text_generator');

			$language_id = (int)($this->request->post['language_id'] ?? $this->config->get('config_language_id'));
			$items = isset($this->request->post['items']) && is_array($this->request->post['items']) ? $this->request->post['items'] : [];

			$saved = 0;

			foreach ($items as $item) {
				if (!is_array($item) || !isset($item['image'], $item['alt'])) {
					continue;
				}

				// The request is HTML-encoded by OpenCart, the table keeps the plain text.
				$path = html_entity_decode((string)$item['image'], ENT_QUOTES, 'UTF-8');

				if ($this->realImagePath($path) === '') {
					continue;
				}

				$this->model_extension_altai_module_alt_text_generator->saveAlt($path, $language_id, html_entity_decode((string)$item['alt'], ENT_QUOTES, 'UTF-8'));

				$saved++;
			}

			$json['success'] = sprintf($this->language->get('text_saved'), $saved);
			$json['saved'] = $saved;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Credits left on the key. Checking never uses a credit.
	 *
	 * @return void
	 */
	public function credits(): void {
		$json = ['credits' => null];

		$api_key = trim((string)$this->config->get('module_alt_text_generator_api_key'));

		if ($api_key !== '' && $this->user->hasPermission('access', self::ROUTE)) {
			$result = $this->call(preg_replace('#/api/[^/]+/?$#', '/api/verify', $this->endpoint()), ['apiKey' => $api_key], 15);

			if ($result['status'] === 200) {
				$data = json_decode($result['body'], true);

				if (is_array($data) && isset($data['freeRewritesLeft']) && is_numeric($data['freeRewritesLeft'])) {
					$json['credits'] = (int)$data['freeRewritesLeft'];
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Install
	 *
	 * @return void
	 */
	public function install(): void {
		if ($this->user->hasPermission('modify', 'extension/module')) {
			$this->load->model('extension/altai/module/alt_text_generator');

			$this->model_extension_altai_module_alt_text_generator->install();

			// Starts switched on, because there is no alt text to show until some has been written.
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('module_alt_text_generator', [
				'module_alt_text_generator_status'   => 1,
				'module_alt_text_generator_language' => 'english'
			]);

			$this->load->model('setting/event');

			$this->model_setting_event->deleteEventByCode('alt_text_generator');

			// Puts the saved alt text into the images of the storefront.
			$this->model_setting_event->addEvent([
				'code'        => 'alt_text_generator',
				'description' => 'Alt Text Generator AI: replaces the alt text of images in the storefront.',
				'trigger'     => 'catalog/view/*/after',
				'action'      => 'extension/altai/event/alt_text.replace',
				'status'      => 1,
				'sort_order'  => 10
			]);

			// Adds the page to the Catalog menu.
			$this->model_setting_event->addEvent([
				'code'        => 'alt_text_generator',
				'description' => 'Alt Text Generator AI: adds the page to the Catalog menu.',
				'trigger'     => 'admin/view/common/column_left/before',
				'action'      => 'extension/altai/event/menu',
				'status'      => 1,
				'sort_order'  => 10
			]);
		}
	}

	/**
	 * Uninstall
	 *
	 * @return void
	 */
	public function uninstall(): void {
		if ($this->user->hasPermission('modify', 'extension/module')) {
			$this->load->model('extension/altai/module/alt_text_generator');

			$this->model_extension_altai_module_alt_text_generator->uninstall();

			$this->load->model('setting/event');

			$this->model_setting_event->deleteEventByCode('alt_text_generator');
		}
	}

	/**
	 * A link for requests made from JavaScript, without the HTML-encoded ampersands.
	 */
	private function ajaxLink(string $method, string $token): string {
		return html_entity_decode($this->url->link(self::ROUTE . $method, $token), ENT_QUOTES, 'UTF-8');
	}

	private function languageId(): int {
		$language_id = (int)($this->request->get['language_id'] ?? 0);

		return $language_id ?: (int)$this->config->get('config_language_id');
	}

	private function endpoint(): string {
		return (string)($this->config->get('module_alt_text_generator_endpoint') ?: self::ENDPOINT);
	}

	/**
	 * The absolute path of an image inside the image folder, or an empty string
	 *
	 * @param string $path path relative to the image folder
	 *
	 * @return string
	 */
	private function realImagePath(string $path): string {
		$path = html_entity_decode($path, ENT_QUOTES, 'UTF-8');

		if ($path === '' || str_contains($path, '..') || !preg_match('/\.(jpe?g|png|gif|webp)$/i', $path)) {
			return '';
		}

		$real = realpath(DIR_IMAGE . $path);
		$root = realpath(DIR_IMAGE);

		if ($real === false || $root === false || !is_file($real) || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
			return '';
		}

		return $real;
	}

	/**
	 * A JPEG data URL with the longest side at most 1024 px, so a big original is never sent.
	 *
	 * @param string $file
	 *
	 * @return string
	 */
	private function toDataUrl(string $file): string {
		$bytes = (string)file_get_contents($file);

		if ($bytes === '') {
			return '';
		}

		$data_url = '';

		if (function_exists('imagecreatefromstring') && ($source = @imagecreatefromstring($bytes)) !== false) {
			$width = imagesx($source);
			$height = imagesy($source);
			$scale = min(1.0, self::MAX_SIDE / max($width, $height));
			$new_width = max(1, (int)round($width * $scale));
			$new_height = max(1, (int)round($height * $scale));

			$canvas = imagecreatetruecolor($new_width, $new_height);

			// JPEG has no transparency, so transparent areas become white.
			imagefill($canvas, 0, 0, (int)imagecolorallocate($canvas, 255, 255, 255));
			imagecopyresampled($canvas, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

			ob_start();
			imagejpeg($canvas, null, 80);
			$jpeg = (string)ob_get_clean();

			if ($jpeg !== '') {
				$data_url = 'data:image/jpeg;base64,' . base64_encode($jpeg);
			}
		}

		if ($data_url === '') {
			$mime = (string)(getimagesizefromstring($bytes)['mime'] ?? '');

			if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
				$data_url = 'data:' . $mime . ';base64,' . base64_encode($bytes);
			}
		}

		return strlen($data_url) > self::MAX_DATA_URL ? '' : $data_url;
	}

	/**
	 * Calls the Alt Text Generator AI service from the server, so the key never reaches the browser.
	 *
	 * @param string       $url
	 * @param array<mixed> $body
	 * @param int          $timeout
	 *
	 * @return array{status: int, body: string, credits: int|null}
	 */
	private function call(string $url, array $body, int $timeout = 60): array {
		$credits = null;

		$curl = curl_init($url);

		curl_setopt_array($curl, [
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => json_encode($body),
			CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => $timeout,
			CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$credits): int {
				if (stripos($header, 'x-credits-left:') === 0 && is_numeric(trim(substr($header, 15)))) {
					$credits = (int)trim(substr($header, 15));
				}

				return strlen($header);
			}
		]);

		$response = curl_exec($curl);
		$status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

		curl_close($curl);

		return ['status' => $response === false ? 0 : $status, 'body' => $response === false ? '' : trim((string)$response), 'credits' => $credits];
	}
}
