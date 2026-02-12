<?php

if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__);
}

$GLOBALS['asm_test_options'] = [];

function get_option($key, $default = null) {
	return $GLOBALS['asm_test_options'][$key] ?? $default;
}
function update_option($key, $value) {
	$GLOBALS['asm_test_options'][$key] = $value;
	return true;
}
function sanitize_text_field($value) {
	return trim((string) $value);
}
function sanitize_textarea_field($value) {
	return trim((string) $value);
}

require_once dirname(__DIR__) . '/includes/class-asm-settings.php';
require_once dirname(__DIR__) . '/includes/class-asm-validator.php';

function assert_true($cond, $message) {
	if (!$cond) {
		fwrite(STDERR, "Assertion failed: {$message}\n");
		exit(1);
	}
}

$settings = new ASM_Settings();
$validator = new ASM_Validator($settings);

assert_true($settings->normalize_profile('') === 'general', 'missing profile must normalize to general');

$baseKeywords = [];
for ($i = 1; $i <= 44; $i++) {
	$baseKeywords[] = "token{$i}";
}
$baseKeywords[] = 'urgent care';

$valid = $validator->validate([
	'title' => 'modern clinic corridor with medical equipment',
	'description' => 'clean interior with visible examination devices and hallway lighting',
	'keywords' => $baseKeywords,
	'categories' => [
		'primary' => 'Healthcare and Medical',
	],
], 'general');
assert_true($valid['is_valid'] === true, 'valid payload should pass with split keywords in 40-49 range');

$missingCategory = $validator->validate([
	'title' => 'modern clinic corridor with medical equipment',
	'description' => 'clean interior with visible examination devices and hallway lighting',
	'keywords' => $baseKeywords,
], 'general');
assert_true($missingCategory['is_valid'] === false, 'missing category must fail');

$invalidCategory = $validator->validate([
	'title' => 'modern clinic corridor with medical equipment',
	'description' => 'clean interior with visible examination devices and hallway lighting',
	'keywords' => $baseKeywords,
	'categories' => [
		'primary' => 'Medical Scene',
	],
], 'general');
assert_true($invalidCategory['is_valid'] === false, 'non-whitelist category must fail');

$medical = $validator->validate([
	'title' => 'hospital corridor with medical cart and tools',
	'description' => 'clinical interior showing diagnosis board and hallway lighting',
	'keywords' => $baseKeywords,
	'categories' => [
		'primary' => 'Healthcare and Medical',
	],
], 'medical');
assert_true($medical['is_valid'] === false, 'medical profile diagnosis terms must fail');

$tooFew = $validator->validate([
	'title' => 'building exterior with windows and facade geometry',
	'description' => 'urban structure with concrete and glass materials in daylight',
	'keywords' => ['building', 'facade'],
	'categories' => [
		'primary' => 'Buildings and Architecture',
	],
], 'architecture');
assert_true($tooFew['is_valid'] === false, 'keyword count under 40 must fail');

echo "OK\n";
