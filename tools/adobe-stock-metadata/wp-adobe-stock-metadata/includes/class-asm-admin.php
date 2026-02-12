<?php

if (!defined('ABSPATH')) {
	exit;
}

class ASM_Admin {
	private ASM_Settings $settings;
	private ASM_Queue $queue;

	public function __construct(ASM_Settings $settings, ASM_Queue $queue) {
		$this->settings = $settings;
		$this->queue = $queue;
		add_action('admin_menu', [$this, 'register_menu']);
		add_action('admin_post_asm_save_settings', [$this, 'handle_save_settings']);
		add_action('admin_post_asm_upload_images', [$this, 'handle_upload_images']);
		add_action('admin_post_asm_toggle_injection', [$this, 'handle_toggle_injection']);
		add_action('admin_post_asm_process_now', [$this, 'handle_process_now']);
		add_action('admin_post_asm_process_all_now', [$this, 'handle_process_all_now']);
		add_action('admin_post_asm_clear_terminal_jobs', [$this, 'handle_clear_terminal_jobs']);
	}

	public function register_menu(): void {
		add_menu_page(
			'Adobe Metadata',
			'Adobe Metadata',
			'manage_options',
			'asm-dashboard',
			[$this, 'render_dashboard'],
			'dashicons-format-image'
		);

		add_submenu_page('asm-dashboard', 'Settings', 'Settings', 'manage_options', 'asm-settings', [$this, 'render_settings']);
	}

	private function guard_admin(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Unauthorized', 'asm'));
		}
	}

	public function handle_save_settings(): void {
		$this->guard_admin();
		check_admin_referer('asm_save_settings');

		$this->settings->update($_POST);
		wp_safe_redirect(admin_url('admin.php?page=asm-settings&saved=1'));
		exit;
	}

	public function handle_upload_images(): void {
		$this->guard_admin();
		check_admin_referer('asm_upload_images');

		$inject = isset($_POST['inject_enabled']);
		$files = $_FILES['asm_images'] ?? [];
		$count = $this->queue->enqueue_uploads($files, $inject);
		wp_safe_redirect(admin_url('admin.php?page=asm-dashboard&queued=' . intval($count)));
		exit;
	}

	public function handle_toggle_injection(): void {
		$this->guard_admin();
		check_admin_referer('asm_toggle_injection');
		$ids = array_map('sanitize_text_field', (array) ($_POST['job_ids'] ?? []));
		if (isset($_POST['set_inject'])) {
			$enable = true;
		} elseif (isset($_POST['set_skip'])) {
			$enable = false;
		} else {
			wp_safe_redirect(admin_url('admin.php?page=asm-dashboard&toggle_error=1'));
			exit;
		}
		$this->queue->toggle_injection($ids, $enable);
		wp_safe_redirect(admin_url('admin.php?page=asm-dashboard&toggled=1'));
		exit;
	}

	public function handle_process_now(): void {
		$this->guard_admin();
		check_admin_referer('asm_process_now');
		$this->queue->process_next_job();
		wp_safe_redirect(admin_url('admin.php?page=asm-dashboard&processed=1'));
		exit;
	}

	public function handle_process_all_now(): void {
		$this->guard_admin();
		check_admin_referer('asm_process_all_now');
		$maxJobs = isset($_POST['max_jobs']) ? intval($_POST['max_jobs']) : 10;
		$processed = $this->queue->process_pending_now($maxJobs);
		wp_safe_redirect(admin_url('admin.php?page=asm-dashboard&processed_all=' . intval($processed)));
		exit;
	}

	public function handle_clear_terminal_jobs(): void {
		$this->guard_admin();
		check_admin_referer('asm_clear_terminal_jobs');
		$removed = $this->queue->clear_terminal_jobs();
		wp_safe_redirect(admin_url('admin.php?page=asm-dashboard&cleared=' . intval($removed)));
		exit;
	}

	public function render_settings(): void {
		$this->guard_admin();
		$config = $this->settings->get_all();
		?>
		<div class="wrap">
			<h1>Adobe Metadata Settings</h1>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<input type="hidden" name="action" value="asm_save_settings" />
				<?php wp_nonce_field('asm_save_settings'); ?>
				<table class="form-table" role="presentation">
					<tr><th>Provider</th><td>
						<select name="provider">
							<?php foreach (['openai' => 'OpenAI', 'gemini' => 'Gemini', 'openrouter' => 'OpenRouter'] as $k => $label): ?>
								<option value="<?php echo esc_attr($k); ?>" <?php selected($config['provider'], $k); ?>><?php echo esc_html($label); ?></option>
							<?php endforeach; ?>
						</select>
					</td></tr>
					<tr><th>Enable Profiles Auto Detection</th><td><label><input type="checkbox" name="enable_profiles" value="1" <?php checked(!empty($config['enable_profiles'])); ?>> medical / architecture / food / general</label></td></tr>
					<tr><th>OpenAI API Key</th><td><input type="password" name="openai_api_key" class="regular-text" value="<?php echo esc_attr($config['openai_api_key']); ?>"></td></tr>
					<tr><th>OpenAI Model</th><td><input type="text" name="openai_model" class="regular-text" value="<?php echo esc_attr($config['openai_model']); ?>"></td></tr>
					<tr><th>Gemini API Key</th><td><input type="password" name="gemini_api_key" class="regular-text" value="<?php echo esc_attr($config['gemini_api_key']); ?>"></td></tr>
					<tr><th>Gemini Model</th><td><input type="text" name="gemini_model" class="regular-text" value="<?php echo esc_attr($config['gemini_model']); ?>"></td></tr>
					<tr><th>OpenRouter API Key</th><td><input type="password" name="openrouter_api_key" class="regular-text" value="<?php echo esc_attr($config['openrouter_api_key']); ?>"></td></tr>
					<tr><th>OpenRouter Model</th><td><input type="text" name="openrouter_model" class="regular-text" value="<?php echo esc_attr($config['openrouter_model']); ?>"></td></tr>
					<tr><th>Prompt Template</th><td><textarea name="prompt_template" rows="6" class="large-text"><?php echo esc_textarea($config['prompt_template']); ?></textarea></td></tr>
					<tr><th>Keyword Min</th><td><input type="number" name="keyword_min" min="40" max="49" value="<?php echo esc_attr((string) $config['keyword_min']); ?>"></td></tr>
					<tr><th>Keyword Max</th><td><input type="number" name="keyword_max" min="40" max="49" value="<?php echo esc_attr((string) $config['keyword_max']); ?>"></td></tr>
					<tr><th>ExifTool Path</th><td><input type="text" name="exiftool_path" class="regular-text" value="<?php echo esc_attr($config['exiftool_path']); ?>"></td></tr>
					<tr><th>Forbidden Terms (one per line)</th><td><textarea name="forbidden_terms" rows="6" class="large-text"><?php echo esc_textarea($config['forbidden_terms']); ?></textarea></td></tr>
				</table>
				<?php submit_button('Save Settings'); ?>
			</form>
		</div>
		<?php
	}

	public function render_dashboard(): void {
		$this->guard_admin();
		$queue = array_reverse($this->queue->get_queue());
		?>
		<div class="wrap">
			<h1>Adobe Stock Metadata Processor</h1>
			<p>Admin-only. Upload one or many images (recommended up to 50) for queued sequential processing.</p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<input type="hidden" name="action" value="asm_upload_images" />
				<?php wp_nonce_field('asm_upload_images'); ?>
				<input type="file" name="asm_images[]" multiple accept="image/*" required />
				<label><input type="checkbox" name="inject_enabled" checked /> Inject metadata after validation</label>
				<?php submit_button('Queue Uploads', 'primary', 'submit', false); ?>
			</form>

			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px;">
				<input type="hidden" name="action" value="asm_process_now" />
				<?php wp_nonce_field('asm_process_now'); ?>
				<?php submit_button('Process Next Job Now', 'secondary', 'submit', false); ?>
			</form>

			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px;">
				<input type="hidden" name="action" value="asm_process_all_now" />
				<?php wp_nonce_field('asm_process_all_now'); ?>
				<label for="asm-max-jobs">Process all pending now (max N jobs):</label>
				<input id="asm-max-jobs" type="number" name="max_jobs" min="1" max="100" value="10" style="width:90px;" />
				<?php submit_button('Process Pending (Max N)', 'secondary', 'submit', false); ?>
			</form>

			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px;">
				<input type="hidden" name="action" value="asm_clear_terminal_jobs" />
				<?php wp_nonce_field('asm_clear_terminal_jobs'); ?>
				<?php submit_button('Clear Completed/Failed Jobs', 'delete', 'submit', false); ?>
			</form>

			<h2>Queue</h2>
			<p>
				<label>Profile:
					<select id="asm-filter-profile">
						<option value="">All</option>
						<option value="general">general</option>
						<option value="medical">medical</option>
						<option value="architecture">architecture</option>
						<option value="food">food</option>
					</select>
				</label>
				<label style="margin-left:10px;">Primary Category:
					<select id="asm-filter-primary">
						<option value="">All</option>
					</select>
				</label>
			</p>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<input type="hidden" name="action" value="asm_toggle_injection" />
				<?php wp_nonce_field('asm_toggle_injection'); ?>
				<table class="widefat striped" id="asm-queue-table">
					<thead><tr><th></th><th>File</th><th>Status</th><th>Inject</th><th>Profile</th><th>Primary Category</th><th>Secondary Category</th><th>Confidence</th><th>Title</th><th>Errors / Notes</th><th>Updated</th></tr></thead>
					<tbody>
					<?php if (empty($queue)): ?>
						<tr><td colspan="11">No jobs yet.</td></tr>
					<?php else: foreach ($queue as $job):
						$profile = (string) ($job['metadata']['profile'] ?? 'general');
						$primary = (string) ($job['metadata']['categories']['primary_category'] ?? '');
						$secondary = (string) ($job['metadata']['categories']['secondary_category'] ?? '');
						$cp = (float) ($job['metadata']['categories']['confidence_primary'] ?? 0);
						$cs = (float) ($job['metadata']['categories']['confidence_secondary'] ?? 0);
					?>
						<tr data-profile="<?php echo esc_attr($profile); ?>" data-primary="<?php echo esc_attr(strtolower($primary)); ?>">
							<td><input type="checkbox" name="job_ids[]" value="<?php echo esc_attr((string) $job['id']); ?>"></td>
							<td><?php echo esc_html(basename((string) $job['file_path'])); ?></td>
							<td><?php echo esc_html((string) $job['status']); ?></td>
							<td><?php echo !empty($job['inject_enabled']) ? 'yes' : 'no'; ?></td>
							<td><?php echo esc_html($profile); ?></td>
							<td><?php echo esc_html($primary); ?></td>
							<td><?php echo esc_html($secondary); ?></td>
							<td><?php echo esc_html(number_format($cp, 2) . " / " . number_format($cs, 2)); ?></td>
							<td><?php echo esc_html((string) ($job['metadata']['title'] ?? '')); ?></td>
							<td><?php echo esc_html(implode(' | ', (array) ($job['errors'] ?? []))); ?></td>
							<td><?php echo esc_html((string) ($job['updated_at'] ?? '')); ?></td>
						</tr>
					<?php endforeach; endif; ?>
					</tbody>
				</table>
				<p>
					<button class="button" type="submit" name="set_inject" value="1">Enable Injection for Selected</button>
					<button class="button" type="submit" name="set_skip" value="1">Skip Injection for Selected</button>
				</p>
			</form>
		</div>
		<script>
		(function () {
			const table = document.getElementById('asm-queue-table');
			if (!table) return;
			const rows = Array.from(table.querySelectorAll('tbody tr[data-profile]'));
			const profileSelect = document.getElementById('asm-filter-profile');
			const primarySelect = document.getElementById('asm-filter-primary');
			const categories = new Set();
			rows.forEach((row) => {
				const primary = (row.dataset.primary || '').trim();
				if (primary) categories.add(primary);
			});
			Array.from(categories).sort().forEach((value) => {
				const opt = document.createElement('option');
				opt.value = value;
				opt.textContent = value;
				primarySelect.appendChild(opt);
			});
			function applyFilter() {
				const profile = profileSelect.value;
				const primary = primarySelect.value;
				rows.forEach((row) => {
					const okProfile = !profile || row.dataset.profile === profile;
					const okPrimary = !primary || row.dataset.primary === primary;
					row.style.display = okProfile && okPrimary ? '' : 'none';
				});
			}
			profileSelect.addEventListener('change', applyFilter);
			primarySelect.addEventListener('change', applyFilter);
		})();
		</script>
		<?php
	}
}
