<?php

if (!defined('ABSPATH')) {
	exit;
}

class ASM_Provider_Gemini implements ASM_Provider_Interface {
	private string $api_key;
	private string $model;

	public function __construct(string $api_key, string $model) {
		$this->api_key = $api_key;
		$this->model = $model;
	}

	public function generate_metadata(string $image_path, string $prompt_template): array {
		if ($this->api_key === '') {
			return ['error' => 'Gemini API key is missing.'];
		}
		$image_data = file_get_contents($image_path);
		if ($image_data === false) {
			return ['error' => 'Cannot read image file.'];
		}
		$mime = mime_content_type($image_path) ?: 'image/jpeg';

		$body = [
			'contents' => [[
				'parts' => [
					['text' => $prompt_template . ' Return only JSON.'],
					['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($image_data)]],
				],
			]],
			'generationConfig' => [
				'responseMimeType' => 'application/json',
			],
		];

		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($this->model) . ':generateContent?key=' . rawurlencode($this->api_key);
		$response = wp_remote_post($url, [
			'headers' => ['Content-Type' => 'application/json'],
			'body' => wp_json_encode($body),
			'timeout' => 90,
		]);

		if (is_wp_error($response)) {
			return ['error' => $response->get_error_message()];
		}
		$status = wp_remote_retrieve_response_code($response);
		if ($status >= 300) {
			return ['error' => 'Gemini error: HTTP ' . $status . ' ' . wp_remote_retrieve_body($response)];
		}
		$decoded = json_decode(wp_remote_retrieve_body($response), true);
		$text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
		$parsed = ASM_Json::decode_first_object((string) $text);
		if (isset($parsed['error'])) {
			return ['error' => 'Invalid JSON from Gemini model. ' . $parsed['error']];
		}
		return (array) $parsed['data'];
	}
}
