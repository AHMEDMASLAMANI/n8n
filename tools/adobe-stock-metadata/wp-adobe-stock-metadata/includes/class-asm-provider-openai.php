<?php

if (!defined('ABSPATH')) {
	exit;
}

class ASM_Provider_OpenAI implements ASM_Provider_Interface {
	private string $api_key;
	private string $model;

	public function __construct(string $api_key, string $model) {
		$this->api_key = $api_key;
		$this->model = $model;
	}

	public function generate_metadata(string $image_path, string $prompt_template): array {
		if ($this->api_key === '') {
			return ['error' => 'OpenAI API key is missing.'];
		}
		$image_data = file_get_contents($image_path);
		if ($image_data === false) {
			return ['error' => 'Cannot read image file.'];
		}
		$mime = mime_content_type($image_path) ?: 'image/jpeg';
		$base64 = base64_encode($image_data);

		$body = [
			'model' => $this->model,
			'response_format' => ['type' => 'json_object'],
			'messages' => [[
				'role' => 'user',
				'content' => [
					['type' => 'text', 'text' => $prompt_template],
					['type' => 'image_url', 'image_url' => ['url' => 'data:' . $mime . ';base64,' . $base64]],
				],
			]],
		];

		$response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
			'headers' => [
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type' => 'application/json',
			],
			'body' => wp_json_encode($body),
			'timeout' => 90,
		]);

		if (is_wp_error($response)) {
			return ['error' => $response->get_error_message()];
		}
		$status = wp_remote_retrieve_response_code($response);
		if ($status >= 300) {
			return ['error' => 'OpenAI error: HTTP ' . $status . ' ' . wp_remote_retrieve_body($response)];
		}
		$decoded = json_decode(wp_remote_retrieve_body($response), true);
		$content = $decoded['choices'][0]['message']['content'] ?? '';
		$parsed = ASM_Json::decode_first_object((string) $content);
		if (isset($parsed['error'])) {
			return ['error' => 'Invalid JSON from OpenAI model. ' . $parsed['error']];
		}
		return (array) $parsed['data'];
	}
}
