<?php
namespace Opencart\Admin\Model\Extension\Altai\Module;
/**
 * Class AltTextGenerator
 *
 * Finds the images used in the shop and stores the alt text written for them. OpenCart has
 * no alt field for images, so the text lives in its own table, keyed by the image path.
 *
 * @package Opencart\Admin\Model\Extension\Altai\Module
 */
class AltTextGenerator extends \Opencart\System\Engine\Model {
	public const MAX_IMAGES = 2000;

	/**
	 * Languages the alt text can be written in (same list as the website and the other integrations).
	 *
	 * @var array<string, string>
	 */
	public const LANGUAGES = [
		'english' => 'English',
		'spanish' => 'Spanish',
		'french' => 'French',
		'german' => 'German',
		'italian' => 'Italian',
		'portuguese' => 'Portuguese',
		'dutch' => 'Dutch',
		'russian' => 'Russian',
		'chinese' => 'Chinese',
		'japanese' => 'Japanese',
		'korean' => 'Korean',
		'arabic' => 'Arabic',
		'swedish' => 'Swedish',
		'norwegian' => 'Norwegian',
		'danish' => 'Danish',
		'finnish' => 'Finnish',
		'polish' => 'Polish',
		'turkish' => 'Turkish',
		'hindi' => 'Hindi',
		'bengali' => 'Bengali',
		'urdu' => 'Urdu',
		'thai' => 'Thai',
		'vietnamese' => 'Vietnamese',
		'indonesian' => 'Indonesian',
		'malay' => 'Malay',
		'filipino' => 'Filipino',
		'greek' => 'Greek',
		'hebrew' => 'Hebrew',
		'hungarian' => 'Hungarian',
		'czech' => 'Czech',
		'romanian' => 'Romanian',
		'bulgarian' => 'Bulgarian',
		'ukrainian' => 'Ukrainian',
		'croatian' => 'Croatian',
		'serbian' => 'Serbian',
		'afrikaans' => 'Afrikaans',
		'albanian' => 'Albanian',
		'amharic' => 'Amharic',
		'armenian' => 'Armenian',
		'assamese' => 'Assamese',
		'aymara' => 'Aymara',
		'azerbaijani' => 'Azerbaijani',
		'bambara' => 'Bambara',
		'basque' => 'Basque',
		'belarusian' => 'Belarusian',
		'bhojpuri' => 'Bhojpuri',
		'bosnian' => 'Bosnian',
		'catalan' => 'Catalan',
		'cebuano' => 'Cebuano',
		'corsican' => 'Corsican',
		'dhivehi' => 'Dhivehi',
		'dogri' => 'Dogri',
		'esperanto' => 'Esperanto',
		'estonian' => 'Estonian',
		'ewe' => 'Ewe',
		'frisian' => 'Frisian',
		'galician' => 'Galician',
		'georgian' => 'Georgian',
		'guarani' => 'Guarani',
		'gujarati' => 'Gujarati',
		'haitiancreole' => 'Haitian Creole',
		'hausa' => 'Hausa',
		'hawaiian' => 'Hawaiian',
		'hmong' => 'Hmong',
		'icelandic' => 'Icelandic',
		'igbo' => 'Igbo',
		'ilocano' => 'Ilocano',
		'irish' => 'Irish',
		'javanese' => 'Javanese',
		'kannada' => 'Kannada',
		'kazakh' => 'Kazakh',
		'khmer' => 'Khmer',
		'kinyarwanda' => 'Kinyarwanda',
		'konkani' => 'Konkani',
		'krio' => 'Krio',
		'kurdish' => 'Kurdish',
		'kyrgyz' => 'Kyrgyz',
		'lao' => 'Lao',
		'latin' => 'Latin',
		'latvian' => 'Latvian',
		'lingala' => 'Lingala',
		'lithuanian' => 'Lithuanian',
		'luganda' => 'Luganda',
		'luxembourgish' => 'Luxembourgish',
		'macedonian' => 'Macedonian',
		'maithili' => 'Maithili',
		'malagasy' => 'Malagasy',
		'malayalam' => 'Malayalam',
		'maltese' => 'Maltese',
		'maori' => 'Maori',
		'marathi' => 'Marathi',
		'meiteilon' => 'Meiteilon (Manipuri)',
		'mizo' => 'Mizo',
		'mongolian' => 'Mongolian',
		'myanmar' => 'Myanmar (Burmese)',
		'nepali' => 'Nepali',
		'nyanja' => 'Nyanja (Chichewa)',
		'odia' => 'Odia (Oriya)',
		'oromo' => 'Oromo',
		'pashto' => 'Pashto',
		'persian' => 'Persian',
		'punjabi' => 'Punjabi',
		'quechua' => 'Quechua',
		'samoan' => 'Samoan',
		'sanskrit' => 'Sanskrit',
		'scotsgaelic' => 'Scots Gaelic',
		'sepedi' => 'Sepedi',
		'sesotho' => 'Sesotho',
		'shona' => 'Shona',
		'sindhi' => 'Sindhi',
		'sinhala' => 'Sinhala (Sinhalese)',
		'slovak' => 'Slovak',
		'slovenian' => 'Slovenian',
		'somali' => 'Somali',
		'sundanese' => 'Sundanese',
		'swahili' => 'Swahili',
		'tajik' => 'Tajik',
		'tamil' => 'Tamil',
		'tatar' => 'Tatar',
		'telugu' => 'Telugu',
		'tigrinya' => 'Tigrinya',
		'tsonga' => 'Tsonga',
		'turkmen' => 'Turkmen',
		'twi' => 'Twi (Akan)',
		'uyghur' => 'Uyghur',
		'uzbek' => 'Uzbek',
		'welsh' => 'Welsh',
		'xhosa' => 'Xhosa',
		'yiddish' => 'Yiddish',
		'yoruba' => 'Yoruba',
		'zulu' => 'Zulu',
	];


	/**
	 * Install
	 *
	 * @return void
	 */
	public function install(): void {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "altai_image_alt` (
		  `image` varchar(255) NOT NULL,
		  `language_id` int(11) NOT NULL,
		  `alt` varchar(512) NOT NULL,
		  `date_modified` datetime NOT NULL,
		  PRIMARY KEY (`image`, `language_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	}

	/**
	 * Uninstall
	 *
	 * Keeps the table, so alt text that was written is not lost when the extension is switched off.
	 *
	 * @return void
	 */
	public function uninstall(): void {
	}

	/**
	 * Get the images used by products, categories, brands and banners.
	 *
	 * @param int $language_id language the labels are shown in
	 *
	 * @return array<string, array<string, mixed>> keyed by image path
	 */
	public function getImages(int $language_id): array {
		$images = [];

		$sources = [
			['text' => "SELECT p.`image` AS `image`, pd.`name` AS `name`, 'product' AS `source` FROM `" . DB_PREFIX . "product` p LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.`product_id` = pd.`product_id` AND pd.`language_id` = '" . (int)$language_id . "') WHERE p.`image` <> ''"],
			['text' => "SELECT pi.`image` AS `image`, pd.`name` AS `name`, 'product' AS `source` FROM `" . DB_PREFIX . "product_image` pi LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (pi.`product_id` = pd.`product_id` AND pd.`language_id` = '" . (int)$language_id . "') WHERE pi.`image` <> ''"],
			['text' => "SELECT c.`image` AS `image`, cd.`name` AS `name`, 'category' AS `source` FROM `" . DB_PREFIX . "category` c LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (c.`category_id` = cd.`category_id` AND cd.`language_id` = '" . (int)$language_id . "') WHERE c.`image` <> ''"],
			['text' => "SELECT m.`image` AS `image`, m.`name` AS `name`, 'manufacturer' AS `source` FROM `" . DB_PREFIX . "manufacturer` m WHERE m.`image` <> ''"],
			['text' => "SELECT bi.`image` AS `image`, bi.`title` AS `name`, 'banner' AS `source` FROM `" . DB_PREFIX . "banner_image` bi WHERE bi.`image` <> '' AND bi.`language_id` = '" . (int)$language_id . "'"]
		];

		foreach ($sources as $source) {
			foreach ($this->db->query($source['text'])->rows as $row) {
				$path = (string)$row['image'];

				if (!isset($images[$path])) {
					$images[$path] = ['image' => $path, 'names' => [], 'source' => $row['source']];
				}

				if ($row['name'] && !in_array($row['name'], $images[$path]['names'], true) && count($images[$path]['names']) < 3) {
					$images[$path]['names'][] = $row['name'];
				}

				if (count($images) >= self::MAX_IMAGES) {
					break 2;
				}
			}
		}

		return $images;
	}

	/**
	 * Get the alt text written for a language
	 *
	 * @param int $language_id
	 *
	 * @return array<string, string> keyed by image path
	 */
	public function getAlts(int $language_id): array {
		$alts = [];

		$query = $this->db->query("SELECT `image`, `alt` FROM `" . DB_PREFIX . "altai_image_alt` WHERE `language_id` = '" . (int)$language_id . "'");

		foreach ($query->rows as $row) {
			$alts[$row['image']] = $row['alt'];
		}

		return $alts;
	}

	/**
	 * Save the alt text for one image. An empty text removes it, so the shop falls back to its own alt text.
	 *
	 * @param string $image
	 * @param int    $language_id
	 * @param string $alt
	 *
	 * @return void
	 */
	public function saveAlt(string $image, int $language_id, string $alt): void {
		$alt = trim($alt);

		if ($alt === '') {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "altai_image_alt` WHERE `image` = '" . $this->db->escape($image) . "' AND `language_id` = '" . (int)$language_id . "'");

			return;
		}

		$this->db->query("REPLACE INTO `" . DB_PREFIX . "altai_image_alt` SET `image` = '" . $this->db->escape($image) . "', `language_id` = '" . (int)$language_id . "', `alt` = '" . $this->db->escape(oc_substr($alt, 0, 512)) . "', `date_modified` = NOW()");
	}
}
