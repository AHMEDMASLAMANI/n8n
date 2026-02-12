<?php

if (!defined('ABSPATH')) {
	exit;
}

class ASM_Injector {
	private ASM_Settings $settings;

	public function __construct(ASM_Settings $settings) {
		$this->settings = $settings;
	}

	public function detect_exiftool(): string {
		$settings = $this->settings->get_all();
		$path = trim((string) $settings['exiftool_path']);
		if ($path !== '' && is_executable($path)) {
			return $path;
		}
		$which = trim((string) shell_exec('which exiftool 2>/dev/null'));
		if ($which !== '' && is_executable($which)) {
			return $which;
		}
		return '';
	}

	public function inject(string $image_path, array $metadata): array {
		$exiftool = $this->detect_exiftool();
		if ($exiftool !== '') {
			return $this->inject_with_exiftool($exiftool, $image_path, $metadata);
		}
		return $this->write_xmp_sidecar($image_path, $metadata);
	}

	private function inject_with_exiftool(string $bin, string $image_path, array $metadata): array {
		$title = escapeshellarg((string) $metadata['title']);
		$desc = escapeshellarg((string) $metadata['description']);
		$keywords = (array) ($metadata['keywords'] ?? []);
		$keywordArgs = '';
		foreach ($keywords as $keyword) {
			$keywordArgs .= ' -IPTC:Keywords+=' . escapeshellarg((string) $keyword);
		}

		$cmd = escapeshellarg($bin)
			. ' -overwrite_original'
			. ' -XMP-dc:Title=' . $title
			. ' -XMP-dc:Description=' . $desc
			. $keywordArgs
			. ' ' . escapeshellarg($image_path) . ' 2>&1';

		$output = [];
		$return = 1;
		exec($cmd, $output, $return);

		if ($return !== 0) {
			return [
				'mode' => 'exiftool',
				'success' => false,
				'message' => implode("\n", $output),
			];
		}

		return [
			'mode' => 'exiftool',
			'success' => true,
			'message' => 'Injected via ExifTool',
		];
	}

	private function write_xmp_sidecar(string $image_path, array $metadata): array {
		$sidecar = $image_path . '.xmp';
		$keywordsXml = '';
		foreach ((array) ($metadata['keywords'] ?? []) as $keyword) {
			$keywordsXml .= '<rdf:li>' . esc_html((string) $keyword) . '</rdf:li>';
		}

		$xmp = '<?xpacket begin="﻿" id="W5M0MpCehiHzreSzNTczkc9d"?>'
			. '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
			. '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
			. '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
			. '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . esc_html((string) $metadata['title']) . '</rdf:li></rdf:Alt></dc:title>'
			. '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . esc_html((string) $metadata['description']) . '</rdf:li></rdf:Alt></dc:description>'
			. '<dc:subject><rdf:Bag>' . $keywordsXml . '</rdf:Bag></dc:subject>'
			. '</rdf:Description></rdf:RDF></x:xmpmeta>'
			. '<?xpacket end="w"?>';

		$ok = file_put_contents($sidecar, $xmp);
		if ($ok === false) {
			return [
				'mode' => 'xmp-sidecar',
				'success' => false,
				'message' => 'Failed to write sidecar XMP file.',
			];
		}

		return [
			'mode' => 'xmp-sidecar',
			'success' => true,
			'message' => 'ExifTool not available, sidecar XMP created: ' . basename($sidecar),
		];
	}
}
