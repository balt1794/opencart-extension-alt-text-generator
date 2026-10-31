<?php
/**
 * Alt Text Generator AI for OpenCart 3.
 *
 * Writes SEO-friendly alt text for the images used in the shop and saves it for the storefront.
 */
class ControllerExtensionModuleAltTextGenerator extends Controller {
	const ENDPOINT = 'https://alttextgeneratorai.com/api/opencart';
	const ROUTE = 'extension/module/alt_text_generator';
	const MAX_SIDE = 1024;
	const MAX_DATA_URL = 5500000;

	private $error = array();

	public function index() {
		$this->load->language(self::ROUTE);

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$settings = array(
				'module_alt_text_generator_status'   => (int)(isset($this->request->post['module_alt_text_generator_status']) ? $this->request->post['module_alt_text_generator_status'] : 0),
				'module_alt_text_generator_api_key'  => trim((string)(isset($this->request->post['module_alt_text_generator_api_key']) ? $this->request->post['module_alt_text_generator_api_key'] : '')),
				'module_alt_text_generator_language' => $this->validLanguage(isset($this->request->post['module_alt_text_generator_language']) ? $this->request->post['module_alt_text_generator_language'] : '')
			);

			// Keeps a custom address that was set for testing.
			if ($this->config->get('module_alt_text_generator_endpoint')) {
				$settings['module_alt_text_generator_endpoint'] = $this->config->get('module_alt_text_generator_endpoint');
			}

			$this->model_setting_setting->editSetting('module_alt_text_generator', $settings);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

		$token = 'user_token=' . $this->session->data['user_token'];

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', $token, true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', $token . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link(self::ROUTE, $token, true)
		);

		$data['action'] = $this->url->link(self::ROUTE, $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=module', true);

		$data['url_images'] = $this->ajaxLink('/images', $token);
		$data['url_generate'] = $this->ajaxLink('/generate', $token);
		$data['url_apply'] = $this->ajaxLink('/apply', $token);
		$data['url_credits'] = $this->ajaxLink('/credits', $token);

		$data['module_alt_text_generator_status'] = isset($this->request->post['module_alt_text_generator_status']) ? $this->request->post['module_alt_text_generator_status'] : $this->config->get('module_alt_text_generator_status');
		$data['module_alt_text_generator_api_key'] = isset($this->request->post['module_alt_text_generator_api_key']) ? $this->request->post['module_alt_text_generator_api_key'] : $this->config->get('module_alt_text_generator_api_key');
		$data['module_alt_text_generator_language'] = isset($this->request->post['module_alt_text_generator_language']) ? $this->request->post['module_alt_text_generator_language'] : ($this->config->get('module_alt_text_generator_language') ? $this->config->get('module_alt_text_generator_language') : 'english');

		$this->load->model('extension/module/alt_text_generator');

		$data['alt_languages'] = ModelExtensionModuleAltTextGenerator::languages();

		$this->load->model('localisation/language');

		$data['store_languages'] = $this->model_localisation_language->getLanguages();
		$data['store_language_id'] = (int)$this->config->get('config_language_id');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view(self::ROUTE, $data));
	}

	/**
	 * The images used in the shop and their alt text.
	 */
	public function images() {
		$this->load->language(self::ROUTE);

		$json = array();

		if (!$this->user->hasPermission('access', self::ROUTE)) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$language_id = $this->languageId();

			$this->load->model('extension/module/alt_text_generator');
			$this->load->model('tool/image');

			$alts = $this->model_extension_module_alt_text_generator->getAlts($language_id);

			$json['images'] = array();
			$missing = 0;

			foreach ($this->model_extension_module_alt_text_generator->getImages($language_id) as $path => $image) {
				$thumb = $this->model_tool_image->resize($path, 72, 72);

				// Files that are gone from the disk cannot be read, so they are left out.
				if (!$thumb) {
					continue;
				}

				$alt = isset($alts[$path]) ? $alts[$path] : '';

				if ($alt === '') {
					$missing++;
				}

				$json['images'][] = array(
					'image'  => $path,
					'name'   => html_entity_decode($image['names'] ? implode(', ', $image['names']) : basename($path), ENT_QUOTES, 'UTF-8'),
					'source' => $image['source'],
					'thumb'  => $thumb,
					'alt'    => $alt
				);
			}

			$json['total'] = count($json['images']);
			$json['missing'] = $missing;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Writes alt text for one image.
	 */
	public function generate() {
		$this->load->language(self::ROUTE);

		$json = array();

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
			$file = $this->realImagePath(isset($this->request->post['image']) ? (string)$this->request->post['image'] : '');

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
			$language = $this->validLanguage(isset($this->request->post['language']) ? $this->request->post['language'] : '');

			$result = $this->call($this->endpoint(), array('image' => $image, 'language' => $language, 'wpkey' => $api_key));

			if ($result['status'] === 0) {
				$json['error'] = $this->language->get('error_unreachable');
			} elseif ($result['status'] === 401) {
				$json['error'] = $this->language->get('error_invalid_key');
				$json['fatal'] = true;
			} elseif ($result['status'] === 402) {
				$json['error'] = $this->language->get('error_no_credits');
				$json['fatal'] = true;
			} elseif ($result['status'] >= 400 || $result['body'] === '') {
				$json['error'] = ($result['body'] !== '' && strlen($result['body']) < 160 && $result['body'][0] !== '<') ? $result['body'] : sprintf($this->language->get('error_generation_status'), $result['status']);
			} else {
				$json['alt'] = $result['body'];
				$json['credits'] = $result['credits'];
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Saves the alt text for the storefront.
	 */
	public function apply() {
		$this->load->language(self::ROUTE);

		$json = array();

		if (!$this->user->hasPermission('modify', self::ROUTE)) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('extension/module/alt_text_generator');

			$language_id = isset($this->request->post['language_id']) ? (int)$this->request->post['language_id'] : (int)$this->config->get('config_language_id');
			$items = isset($this->request->post['items']) && is_array($this->request->post['items']) ? $this->request->post['items'] : array();

			$saved = 0;

			foreach ($items as $item) {
				if (!is_array($item) || !isset($item['image']) || !isset($item['alt'])) {
					continue;
				}

				// The request is HTML-encoded by OpenCart, the table keeps the plain text.
				$path = html_entity_decode((string)$item['image'], ENT_QUOTES, 'UTF-8');

				if ($this->realImagePath($path) === '') {
					continue;
				}

				$this->model_extension_module_alt_text_generator->saveAlt($path, $language_id, html_entity_decode((string)$item['alt'], ENT_QUOTES, 'UTF-8'));

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
	 */
	public function credits() {
		$json = array('credits' => null);

		$api_key = trim((string)$this->config->get('module_alt_text_generator_api_key'));

		if ($api_key !== '' && $this->user->hasPermission('access', self::ROUTE)) {
			$result = $this->call(preg_replace('#/api/[^/]+/?$#', '/api/verify', $this->endpoint()), array('apiKey' => $api_key), 15);

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

	public function install() {
		if ($this->user->hasPermission('modify', 'extension/extension/module') || $this->user->hasPermission('modify', 'marketplace/extension')) {
			$this->load->model('extension/module/alt_text_generator');

			$this->model_extension_module_alt_text_generator->install();

			// Starts switched on, because there is no alt text to show until some has been written.
			$this->load->model('setting/setting');

			$current = $this->model_setting_setting->getSetting('module_alt_text_generator');

			$this->model_setting_setting->editSetting('module_alt_text_generator', $current + array(
				'module_alt_text_generator_status'   => 1,
				'module_alt_text_generator_language' => 'english'
			));

			$this->load->model('setting/event');

			$this->removeEvents();

			// Puts the saved alt text into the images of the storefront.
			$this->model_setting_event->addEvent('alt_text_generator', 'catalog/view/*/after', 'extension/module/alt_text_generator_event/replace', 1, 10);

			// Adds the page to the Catalog menu.
			$this->model_setting_event->addEvent('alt_text_generator', 'admin/view/common/column_left/before', 'extension/module/alt_text_generator_menu', 1, 10);
		}
	}

	public function uninstall() {
		if ($this->user->hasPermission('modify', 'extension/extension/module') || $this->user->hasPermission('modify', 'marketplace/extension')) {
			$this->load->model('extension/module/alt_text_generator');

			$this->model_extension_module_alt_text_generator->uninstall();

			$this->load->model('setting/event');

			$this->removeEvents();
		}
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', self::ROUTE)) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	/**
	 * Models are loaded through a proxy in OpenCart 3, so method_exists() cannot be used on them.
	 * deleteEventByCode() was added in 3.0.2.0, before that deleteEvent() took the code.
	 */
	private function removeEvents() {
		if (defined('VERSION') && version_compare(VERSION, '3.0.2.0', '>=')) {
			$this->model_setting_event->deleteEventByCode('alt_text_generator');
		} else {
			$this->model_setting_event->deleteEvent('alt_text_generator');
		}
	}

	private function validLanguage($language) {
		return preg_match('/^[a-z][a-z \-()]{1,40}$/i', (string)$language) ? (string)$language : 'english';
	}

	/**
	 * A link for requests made from JavaScript, without the HTML-encoded ampersands.
	 */
	private function ajaxLink($method, $token) {
		return html_entity_decode($this->url->link(self::ROUTE . $method, $token, true), ENT_QUOTES, 'UTF-8');
	}

	private function languageId() {
		$language_id = isset($this->request->get['language_id']) ? (int)$this->request->get['language_id'] : 0;

		return $language_id ? $language_id : (int)$this->config->get('config_language_id');
	}

	private function endpoint() {
		$endpoint = $this->config->get('module_alt_text_generator_endpoint');

		return $endpoint ? (string)$endpoint : self::ENDPOINT;
	}

	/**
	 * The absolute path of an image inside the image folder, or an empty string.
	 */
	private function realImagePath($path) {
		$path = html_entity_decode((string)$path, ENT_QUOTES, 'UTF-8');

		if ($path === '' || strpos($path, '..') !== false || !preg_match('/\.(jpe?g|png|gif|webp)$/i', $path)) {
			return '';
		}

		$real = realpath(DIR_IMAGE . $path);
		$root = realpath(DIR_IMAGE);

		if ($real === false || $root === false || !is_file($real) || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0) {
			return '';
		}

		return $real;
	}

	/**
	 * A JPEG data URL with the longest side at most 1024 px, so a big original is never sent.
	 */
	private function toDataUrl($file) {
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
			$info = @getimagesizefromstring($bytes);
			$mime = is_array($info) && isset($info['mime']) ? (string)$info['mime'] : '';

			if (in_array($mime, array('image/jpeg', 'image/png', 'image/gif', 'image/webp'), true)) {
				$data_url = 'data:' . $mime . ';base64,' . base64_encode($bytes);
			}
		}

		return strlen($data_url) > self::MAX_DATA_URL ? '' : $data_url;
	}

	/**
	 * Calls the Alt Text Generator AI service from the server, so the key never reaches the browser.
	 */
	private function call($url, array $body, $timeout = 60) {
		$credits = null;

		$curl = curl_init($url);

		curl_setopt_array($curl, array(
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => json_encode($body),
			CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => $timeout,
			CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$credits) {
				if (stripos($header, 'x-credits-left:') === 0 && is_numeric(trim(substr($header, 15)))) {
					$credits = (int)trim(substr($header, 15));
				}

				return strlen($header);
			}
		));

		$response = curl_exec($curl);
		$status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		curl_close($curl);

		return array('status' => $response === false ? 0 : $status, 'body' => $response === false ? '' : trim((string)$response), 'credits' => $credits);
	}
}
