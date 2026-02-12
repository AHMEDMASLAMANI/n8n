<?php

if (!defined('ABSPATH')) {
	exit;
}

class ASM_Settings {
	private const OPTION_KEY = 'asm_settings';
	private const ADOBE_CATEGORY_WHITELIST = [
		'Abstract',
		'Animals',
		'Backgrounds and Textures',
		'Beauty and Fashion',
		'Buildings and Architecture',
		'Business',
		'Drinks',
		'Education',
		'Food and Drink',
		'Graphic Resources',
		'Healthcare and Medical',
		'Hobbies and Leisure',
		'Industrial',
		'Landscapes',
		'Lifestyle',
		'Nature',
		'Objects',
		'People',
		'Religion and Culture',
		'Science',
		'Social Issues',
		'Sports',
		'Technology',
		'Transportation',
		'Travel',
	];
	private const PROFILE_DEFINITIONS = [
		'medical' => [
			'forbidden_terms' => ['diagnosis', 'diagnoses', 'disease', 'diseases', 'cancer', 'diabetes', 'infection', 'treatment', 'cure', 'recovery', 'outcome'],
		],
		'architecture' => [
			'forbidden_terms' => ['luxury', 'premium', 'for-sale', 'investment', 'landmark'],
		],
		'food' => [
			'forbidden_terms' => ['healthy', 'superfood', 'weight-loss', 'detox', 'healing', 'boost', 'benefit'],
		],
		'general' => [
			'forbidden_terms' => [],
		],
	];
	private const DEFAULT_PROMPT_TEMPLATE = <<<'PROMPT'
Return ONLY strict JSON object with keys: title, description, keywords, profile, categories. profile must be one of medical|architecture|food|general. categories must be an object with primary, secondary, confidence_primary, confidence_secondary. categories.primary MUST be exactly one string from this whitelist: {{ADOBE_CATEGORY_WHITELIST}}. Do not invent categories. title must be <= 70 chars. description must be <= 120 chars. keywords must be 40-49 single-word English tokens, lowercase, unique, ordered by importance. Describe only visible facts, no assumptions. Never include brands, logos, famous people, copyrighted names, marketing words, "photo", "image", or "ai generated". Avoid years and numbers in title unless clearly visible. Profile detection: medical for clinic/hospital/lab equipment/medical devices/healthcare environments; architecture for buildings/interiors/exteriors/urban structures; food for dishes/ingredients/kitchen scenes; otherwise general. Medical profile must not mention diagnosis/disease/treatment outcome. Architecture profile must not claim location unless proven by visible text. Food profile must not include health claims.
PROMPT;


	public function defaults(): array {
		return [
			'provider' => 'openai',
			'openai_api_key' => '',
			'openai_model' => 'gpt-4.1-mini',
			'gemini_api_key' => '',
			'gemini_model' => 'gemini-1.5-flash',
			'openrouter_api_key' => '',
			'openrouter_model' => 'openai/gpt-4o-mini',
			'enable_profiles' => 1,
			'prompt_template' => str_replace('{{ADOBE_CATEGORY_WHITELIST}}', implode(', ', self::ADOBE_CATEGORY_WHITELIST), self::DEFAULT_PROMPT_TEMPLATE),
			'keyword_min' => 40,
			'keyword_max' => 49,
			'exiftool_path' => '',
			'forbidden_terms' => "adobe\ngetty\nshutterstock\niphone\nnike\nlogo\ntrademark\ncopyright\nai generated\nphoto\nimage\nmarketing\npromo",
		];
	}

	public function get_all(): array {
		$current = get_option(self::OPTION_KEY, []);
		if (!is_array($current)) {
			$current = [];
		}
		return array_merge($this->defaults(), $current);
	}

	public function update(array $data): void {
		$current = $this->get_all();
		$allowed = array_keys($this->defaults());
		$next = [];
		foreach ($allowed as $key) {
			$next[$key] = $data[$key] ?? $current[$key];
		}

		$next['keyword_min'] = max(40, min(49, intval($next['keyword_min'])));
		$next['keyword_max'] = max($next['keyword_min'], min(49, intval($next['keyword_max'])));
		$next['enable_profiles'] = !empty($data['enable_profiles']) ? 1 : 0;

		foreach (['openai_api_key', 'gemini_api_key', 'openrouter_api_key', 'exiftool_path', 'provider', 'openai_model', 'gemini_model', 'openrouter_model', 'prompt_template'] as $textKey) {
			$next[$textKey] = sanitize_text_field(strval($next[$textKey]));
		}

		$next['forbidden_terms'] = sanitize_textarea_field(strval($next['forbidden_terms']));
		update_option(self::OPTION_KEY, $next, false);
	}

	public function get_forbidden_terms(): array {
		$settings = $this->get_all();
		$lines = preg_split('/\r\n|\r|\n/', strval($settings['forbidden_terms'])) ?: [];
		$terms = [];
		foreach ($lines as $line) {
			$term = strtolower(trim($line));
			if ($term !== '') {
				$terms[] = $term;
			}
		}
		return array_values(array_unique($terms));
	}

	public function get_profile_rules(string $profile): array {
		$profile = strtolower(trim($profile));
		if (!isset(self::PROFILE_DEFINITIONS[$profile])) {
			$profile = 'general';
		}
		return self::PROFILE_DEFINITIONS[$profile];
	}

	public function normalize_profile(string $profile): string {
		$profile = strtolower(trim($profile));
		if (!in_array($profile, ['medical', 'architecture', 'food', 'general'], true)) {
			return 'general';
		}
		return $profile;
	}

	public function get_category_whitelist(): array {
		return self::ADOBE_CATEGORY_WHITELIST;
	}

	public function is_allowed_category(string $category): bool {
		return in_array(trim($category), self::ADOBE_CATEGORY_WHITELIST, true);
	}
}
