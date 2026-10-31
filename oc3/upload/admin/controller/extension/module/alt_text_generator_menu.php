<?php
/**
 * Adds Alt Text Generator AI to the Catalog menu.
 */
class ControllerExtensionModuleAltTextGeneratorMenu extends Controller {
	public function index(&$route, &$data, &$code) {
		if (!isset($data['menus']) || !is_array($data['menus']) || !$this->user->hasPermission('access', 'extension/module/alt_text_generator')) {
			return;
		}

		foreach ($data['menus'] as &$menu) {
			if (isset($menu['id']) && $menu['id'] === 'menu-catalog') {
				$menu['children'][] = array(
					'name'     => 'Alt Text Generator AI',
					'href'     => $this->url->link('extension/module/alt_text_generator', 'user_token=' . $this->session->data['user_token'], true),
					'children' => array()
				);

				break;
			}
		}
	}
}
