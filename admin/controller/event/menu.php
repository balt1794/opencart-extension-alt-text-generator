<?php
namespace Opencart\Admin\Controller\Extension\Altai\Event;
/**
 * Class Menu
 *
 * Adds Alt Text Generator AI to the Catalog menu.
 *
 * @package Opencart\Admin\Controller\Extension\Altai\Event
 */
class Menu extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @param string       $route
	 * @param array<mixed> $data
	 *
	 * @return void
	 */
	public function index(string &$route, array &$data): void {
		if (!isset($data['menus']) || !is_array($data['menus']) || !$this->user->hasPermission('access', 'extension/altai/module/alt_text_generator')) {
			return;
		}

		$this->load->language('extension/altai/module/alt_text_generator', 'altai');

		foreach ($data['menus'] as &$menu) {
			if (($menu['id'] ?? '') === 'menu-catalog') {
				$menu['children'][] = [
					'name'     => $this->language->get('altai_text_menu'),
					'href'     => $this->url->link('extension/altai/module/alt_text_generator', 'user_token=' . $this->session->data['user_token']),
					'children' => []
				];

				break;
			}
		}
	}
}
