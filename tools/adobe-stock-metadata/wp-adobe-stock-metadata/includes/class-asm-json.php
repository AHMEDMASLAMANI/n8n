<?php

if (!defined('ABSPATH')) {
	exit;
}

class ASM_Json {
	public static function decode_first_object(string $raw): array {
		$trimmed = trim($raw);
		if ($trimmed === '') {
			return ['error' => 'Provider returned empty response body.'];
		}

		$decoded = json_decode($trimmed, true);
		if (is_array($decoded)) {
			return ['data' => $decoded];
		}

		if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $trimmed, $matches) === 1) {
			$candidate = trim($matches[0]);
			$decodedCandidate = json_decode($candidate, true);
			if (is_array($decodedCandidate)) {
				return ['data' => $decodedCandidate];
			}
		}

		return ['error' => 'Provider response did not contain valid JSON metadata. Raw: ' . mb_substr($trimmed, 0, 400)];
	}
}
