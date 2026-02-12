<?php

if (!defined('ABSPATH')) {
	exit;
}

interface ASM_Provider_Interface {
	public function generate_metadata(string $image_path, string $prompt_template): array;
}
