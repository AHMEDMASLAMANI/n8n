<?php

if (!defined('ABSPATH')) {
	exit;
}

class ASM_Queue {
	private const QUEUE_OPTION = 'asm_job_queue';
	private const LOCK_KEY = 'asm_queue_lock';
	private ASM_Settings $settings;
	private ASM_Validator $validator;
	private ASM_Injector $injector;

	public function __construct(ASM_Settings $settings, ASM_Validator $validator, ASM_Injector $injector) {
		$this->settings = $settings;
		$this->validator = $validator;
		$this->injector = $injector;
	}

	public function get_queue(): array {
		$queue = get_option(self::QUEUE_OPTION, []);
		return is_array($queue) ? $queue : [];
	}

	public function save_queue(array $queue): void {
		update_option(self::QUEUE_OPTION, array_values($queue), false);
	}

	public function enqueue_uploads(array $files, bool $inject_enabled): int {
		$uploadDir = wp_upload_dir();
		$targetDir = trailingslashit($uploadDir['basedir']) . 'asm-input';
		wp_mkdir_p($targetDir);

		$queue = $this->get_queue();
		$count = 0;

		$names = $files['name'] ?? [];
		$maxUploads = min(50, count($names));
		for ($i = 0; $i < $maxUploads; $i++) {
			if (empty($files['tmp_name'][$i]) || intval($files['error'][$i]) !== UPLOAD_ERR_OK) {
				continue;
			}
			$filename = sanitize_file_name((string) $files['name'][$i]);
			$dest = trailingslashit($targetDir) . uniqid('asm_', true) . '_' . $filename;
			if (!move_uploaded_file($files['tmp_name'][$i], $dest)) {
				continue;
			}

			$queue[] = [
				'id' => wp_generate_uuid4(),
				'file_path' => $dest,
				'status' => 'pending',
				'inject_enabled' => $inject_enabled,
				'metadata' => null,
				'errors' => [],
				'created_at' => current_time('mysql'),
				'updated_at' => current_time('mysql'),
			];
			$count++;
		}

		$this->save_queue($queue);
		return $count;
	}

	public function toggle_injection(array $ids, bool $enabled): void {
		$queue = $this->get_queue();
		$idLookup = array_flip($ids);
		foreach ($queue as &$job) {
			if (isset($idLookup[$job['id']])) {
				$job['inject_enabled'] = $enabled;
				$job['updated_at'] = current_time('mysql');
			}
		}
		unset($job);
		$this->save_queue($queue);
	}

	public function clear_terminal_jobs(): int {
		$queue = $this->get_queue();
		$kept = [];
		$removed = 0;
		foreach ($queue as $job) {
			$status = (string) ($job['status'] ?? '');
			if (in_array($status, ['completed', 'completed_skipped', 'failed', 'failed_validation', 'failed_injection'], true)) {
				$removed++;
				continue;
			}
			$kept[] = $job;
		}
		$this->save_queue($kept);
		return $removed;
	}

	public function process_next_job(): void {
		if (get_transient(self::LOCK_KEY)) {
			return;
		}
		set_transient(self::LOCK_KEY, '1', 55);

		$queue = $this->get_queue();
		$index = null;
		for ($i = 0; $i < count($queue); $i++) {
			if ($queue[$i]['status'] === 'pending') {
				$index = $i;
				break;
			}
		}
		if ($index === null) {
			delete_transient(self::LOCK_KEY);
			return;
		}

		$job = $queue[$index];
		$queue[$index]['status'] = 'processing';
		$queue[$index]['updated_at'] = current_time('mysql');
		$this->save_queue($queue);

		$result = $this->run_job($job);

		$queue = $this->get_queue();
		for ($i = 0; $i < count($queue); $i++) {
			if ($queue[$i]['id'] === $job['id']) {
				$queue[$i] = array_merge($queue[$i], $result, [
					'updated_at' => current_time('mysql'),
				]);
				break;
			}
		}
		$this->save_queue($queue);
		delete_transient(self::LOCK_KEY);
	}

	public function process_pending_now(int $max_jobs = 10): int {
		$processed = 0;
		$max_jobs = max(1, min(100, $max_jobs));
		for ($i = 0; $i < $max_jobs; $i++) {
			$before = $this->count_pending_jobs();
			if ($before === 0) {
				break;
			}
			$this->process_next_job();
			$after = $this->count_pending_jobs();
			if ($after >= $before) {
				break;
			}
			$processed++;
		}
		return $processed;
	}

	public function count_pending_jobs(): int {
		$count = 0;
		foreach ($this->get_queue() as $job) {
			if (($job['status'] ?? '') === 'pending') {
				$count++;
			}
		}
		return $count;
	}

	private function run_job(array $job): array {
		$provider = $this->build_provider();
		if (!$provider) {
			return ['status' => 'failed', 'errors' => ['No valid AI provider configured.']];
		}

		$config = $this->settings->get_all();
		$raw = $provider->generate_metadata($job['file_path'], $config['prompt_template']);
		if (isset($raw['error'])) {
			return ['status' => 'failed', 'errors' => [$raw['error']]];
		}

		$profile = $this->extract_profile($raw, !empty($config['enable_profiles']));
		$categories = $this->extract_categories($raw);
		$validation = $this->validator->validate($raw, $profile);
		$metadata = array_merge($validation['metadata'], [
			'profile' => $profile,
			'categories' => $categories,
		]);

		if (!$validation['is_valid']) {
			return [
				'status' => 'failed_validation',
				'metadata' => $metadata,
				'errors' => $validation['errors'],
			];
		}

		if (!$job['inject_enabled']) {
			return [
				'status' => 'completed_skipped',
				'metadata' => $metadata,
				'errors' => ['Injection skipped by admin choice.'],
			];
		}

		$inject = $this->injector->inject($job['file_path'], $validation['metadata']);
		if (!$inject['success']) {
			return [
				'status' => 'failed_injection',
				'metadata' => $metadata,
				'errors' => [$inject['message']],
			];
		}

		return [
			'status' => 'completed',
			'metadata' => $metadata,
			'errors' => [$inject['message']],
		];
	}

	private function extract_profile(array $raw, bool $profilesEnabled): string {
		if (!$profilesEnabled) {
			return 'general';
		}
		return $this->settings->normalize_profile((string) ($raw['profile'] ?? 'general'));
	}

	private function extract_categories(array $raw): array {
		$categories = is_array($raw['categories'] ?? null) ? $raw['categories'] : [];
		$primary = trim((string) ($categories['primary'] ?? ''));
		$secondary = trim((string) ($categories['secondary'] ?? ''));
		$cp = floatval($categories['confidence_primary'] ?? 0);
		$cs = floatval($categories['confidence_secondary'] ?? 0);

		return [
			'primary_category' => $primary,
			'secondary_category' => $secondary,
			'confidence_primary' => max(0.0, min(1.0, $cp)),
			'confidence_secondary' => max(0.0, min(1.0, $cs)),
		];
	}

	private function build_provider(): ?ASM_Provider_Interface {
		$config = $this->settings->get_all();
		switch ($config['provider']) {
			case 'gemini':
				return new ASM_Provider_Gemini($config['gemini_api_key'], $config['gemini_model']);
			case 'openrouter':
				return new ASM_Provider_OpenRouter($config['openrouter_api_key'], $config['openrouter_model']);
			case 'openai':
			default:
				return new ASM_Provider_OpenAI($config['openai_api_key'], $config['openai_model']);
		}
	}
}
