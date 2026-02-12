<?php

if (!defined('ABSPATH')) {
	exit;
}

class ASM_Validator {
	private ASM_Settings $settings;

	public function __construct(ASM_Settings $settings) {
		$this->settings = $settings;
	}

	public function validate(array $metadata, string $profile = 'general'): array {
		$config = $this->settings->get_all();
		$errors = [];

		$title = trim((string) ($metadata['title'] ?? ''));
		$description = trim((string) ($metadata['description'] ?? ''));
		$keywords = $this->sanitize_keywords($metadata['keywords'] ?? []);
		$profile = $this->settings->normalize_profile($profile);
		$primaryCategory = trim((string) ($metadata['categories']['primary'] ?? ''));

		if ($title === '') {
			$errors[] = 'Title is required.';
		}
		if (mb_strlen($title) > 70) {
			$errors[] = 'Title must be 70 characters or less.';
		}
		if (mb_strlen($description) > 120) {
			$errors[] = 'Description must be 120 characters or less.';
		}

		$minKeywords = intval($config['keyword_min']);
		$maxKeywords = intval($config['keyword_max']);
		if (count($keywords) < $minKeywords || count($keywords) > $maxKeywords) {
			$errors[] = sprintf('Keywords count must be between %d and %d.', $minKeywords, $maxKeywords);
		}

		if ($primaryCategory === '') {
			$errors[] = 'Primary Adobe category is required.';
		} elseif (!$this->settings->is_allowed_category($primaryCategory)) {
			$errors[] = 'Primary Adobe category is invalid (not in whitelist).';
		}

		$forbidden = $this->settings->get_forbidden_terms();
		$profileRules = $this->settings->get_profile_rules($profile);
		$profileForbidden = $profileRules['forbidden_terms'] ?? [];
		$forbidden = array_values(array_unique(array_merge($forbidden, $profileForbidden)));

		$allText = strtolower($title . ' ' . $description . ' ' . implode(' ', $keywords));
		$hits = [];
		foreach ($forbidden as $term) {
			if ($term !== '' && str_contains($allText, $term)) {
				$hits[] = $term;
			}
		}
		if (!empty($hits)) {
			$errors[] = 'Forbidden terms detected: ' . implode(', ', array_unique($hits));
		}

		$clean = [
			'title' => $title,
			'description' => $description,
			'keywords' => $keywords,
		];

		return [
			'is_valid' => empty($errors),
			'errors' => $errors,
			'metadata' => $clean,
		];
	}

	public function sanitize_keywords($keywords): array {
		if (!is_array($keywords)) {
			$keywords = preg_split('/[,\n]/', (string) $keywords) ?: [];
		}

		$clean = [];
		$seen = [];
		foreach ($keywords as $keyword) {
			$keyword = strtolower(trim((string) $keyword));
			if ($keyword === '') {
				continue;
			}

			$parts = preg_split('/\s+/', $keyword) ?: [];
			foreach ($parts as $part) {
				$token = preg_replace('/[^a-z0-9\-]/i', '', $part);
				if ($token === '' || in_array($token, $seen, true)) {
					continue;
				}
				$seen[] = $token;
				$clean[] = $token;
			}
		}
		return $clean;
	}
}
