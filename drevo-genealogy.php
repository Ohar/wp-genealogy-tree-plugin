<?php
/**
 * Plugin Name: Drevo Genealogy Trees
 * Description: Uploads static genealogy HTML exports as isolated packages and renders them via shortcode.
 * Version: 0.1.0
 * Author: Ohar / Codex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Drevo_Genealogy_Trees {
	const OPTION_TREES = 'drevo_genealogy_trees';
	const NONCE_ACTION = 'drevo_genealogy_upload';
	const SHORTCODE = 'genealogy_tree';
	const MAX_FILES = 3000;
	const MAX_UNPACKED_BYTES = 536870912; // 512 MB.

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_post_drevo_genealogy_upload', array( $this, 'handle_upload' ) );
		add_action( 'admin_post_drevo_genealogy_register_existing', array( $this, 'handle_register_existing' ) );
		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
	}

	public static function activate() {
		self::ensure_storage_root();
	}

	public function add_admin_menu() {
		add_management_page(
			'Родословные',
			'Родословные',
			'manage_options',
			'drevo-genealogy',
			array( $this, 'render_admin_page' )
		);
	}

	public function register_assets() {
		$base_url = plugin_dir_url( __FILE__ );
		$version  = '0.1.0';

		wp_register_style(
			'drevo-genealogy-viewer',
			$base_url . 'assets/viewer.css',
			array(),
			$version
		);

		wp_register_script(
			'drevo-genealogy-viewer',
			$base_url . 'assets/viewer.js',
			array(),
			$version,
			true
		);
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'drevo-genealogy' ) );
		}

		$notice = isset( $_GET['drevo_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['drevo_notice'] ) ) : '';
		$error  = isset( $_GET['drevo_error'] ) ? sanitize_text_field( wp_unslash( $_GET['drevo_error'] ) ) : '';
		$trees  = $this->get_trees();
		?>
		<div class="wrap">
			<h1>Родословные</h1>

			<?php if ( $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>

			<?php if ( $error ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<p>Загружайте ZIP-архив экспорта из «Древа Жизни»: HTML-файл и папку вида <code>*.html.files</code>. Пакет будет опубликован как статический мини-сайт и показан через изолированный iframe.</p>
			<p><strong>Фильтрация персон:</strong> при импорте домашний адрес и телефон удаляются у всех персон. Карточки без признака смерти в диапазоне дат дополнительно очищаются для публичного просмотра: в них остаются имя, главная фотография, город/место жительства, возраст или дата рождения и родственные связи. Биографические заметки, источники, адреса, события и дополнительные фотографии удаляются. Карточки персон с диапазоном жизни, включая неизвестную дату смерти вроде <code>1840 - ?</code>, кроме удаления адреса и телефона не изменяются.</p>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="drevo_genealogy_upload" />
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="drevo_tree_title">Название</label></th>
						<td><input type="text" class="regular-text" id="drevo_tree_title" name="tree_title" placeholder="Род Лысенко" required /></td>
					</tr>
					<tr>
						<th scope="row"><label for="drevo_tree_slug">Slug</label></th>
						<td>
							<input type="text" class="regular-text" id="drevo_tree_slug" name="tree_slug" placeholder="lysenko-main" required />
							<p class="description">Латиница, цифры и дефисы. Используется в шорткоде и URL папки.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="drevo_tree_zip">ZIP-пакет</label></th>
						<td><input type="file" id="drevo_tree_zip" name="tree_zip" accept=".zip,application/zip" required /></td>
					</tr>
				</table>

				<?php submit_button( 'Загрузить родословную' ); ?>
			</form>

			<h2>Зарегистрировать уже загруженную папку</h2>
			<p>Если ZIP слишком большой для браузерной загрузки, залейте папку по SSH/SFTP в <code>wp-content/genealogy/{slug}/</code>, затем зарегистрируйте ее здесь. Та же фильтрация живых персон будет применена при регистрации папки.</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="drevo_genealogy_register_existing" />
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="drevo_existing_title">Название</label></th>
						<td><input type="text" class="regular-text" id="drevo_existing_title" name="tree_title" placeholder="Род Лысенко" required /></td>
					</tr>
					<tr>
						<th scope="row"><label for="drevo_existing_slug">Slug папки</label></th>
						<td>
							<input type="text" class="regular-text" id="drevo_existing_slug" name="tree_slug" placeholder="lysenko-main" required />
							<p class="description">Папка должна содержать <code>index.html</code>.</p>
						</td>
					</tr>
				</table>

				<?php submit_button( 'Зарегистрировать папку', 'secondary' ); ?>
			</form>

			<hr />

			<h2>Загруженные родословные</h2>
			<?php if ( empty( $trees ) ) : ?>
				<p>Пока ничего не загружено.</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>Название</th>
							<th>Slug</th>
							<th>Файлов</th>
							<th>Размер</th>
							<th>Шорткод</th>
							<th>Открыть</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $trees as $tree ) : ?>
							<tr>
								<td><?php echo esc_html( $tree['title'] ); ?></td>
								<td><code><?php echo esc_html( $tree['slug'] ); ?></code></td>
								<td><?php echo isset( $tree['files'] ) ? esc_html( (string) $tree['files'] ) : '—'; ?></td>
								<td><?php echo isset( $tree['bytes'] ) ? esc_html( size_format( (int) $tree['bytes'], 1 ) ) : '—'; ?></td>
								<td><code>[<?php echo esc_html( self::SHORTCODE ); ?> slug="<?php echo esc_attr( $tree['slug'] ); ?>"]</code></td>
								<td><a href="<?php echo esc_url( $this->get_tree_url( $tree['slug'] ) . 'index.html' ); ?>" target="_blank" rel="noopener">index.html</a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handle_upload() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'drevo-genealogy' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		$redirect = admin_url( 'tools.php?page=drevo-genealogy' );

		try {
			$title = isset( $_POST['tree_title'] ) ? sanitize_text_field( wp_unslash( $_POST['tree_title'] ) ) : '';
			$slug  = isset( $_POST['tree_slug'] ) ? sanitize_title( wp_unslash( $_POST['tree_slug'] ) ) : '';

			if ( '' === $title || '' === $slug ) {
				throw new RuntimeException( 'Укажите название и slug.' );
			}

			if ( empty( $_FILES['tree_zip']['tmp_name'] ) || ! is_uploaded_file( $_FILES['tree_zip']['tmp_name'] ) ) {
				throw new RuntimeException( 'ZIP-файл не был загружен.' );
			}

			if ( ! class_exists( 'ZipArchive' ) ) {
				throw new RuntimeException( 'На сервере недоступен ZipArchive. Без него WordPress не сможет распаковать пакет.' );
			}

			self::ensure_storage_root();

			$result = $this->extract_zip_package( $_FILES['tree_zip']['tmp_name'], $slug, $title );
			$trees  = $this->get_trees();
			$trees[ $slug ] = $result;
			update_option( self::OPTION_TREES, $trees, false );

			wp_safe_redirect( add_query_arg( 'drevo_notice', rawurlencode( 'Родословная загружена.' ), $redirect ) );
			exit;
		} catch ( Exception $e ) {
			wp_safe_redirect( add_query_arg( 'drevo_error', rawurlencode( $e->getMessage() ), $redirect ) );
			exit;
		}
	}

	public function handle_register_existing() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'drevo-genealogy' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		$redirect = admin_url( 'tools.php?page=drevo-genealogy' );

		try {
			$title = isset( $_POST['tree_title'] ) ? sanitize_text_field( wp_unslash( $_POST['tree_title'] ) ) : '';
			$slug  = isset( $_POST['tree_slug'] ) ? sanitize_title( wp_unslash( $_POST['tree_slug'] ) ) : '';

			if ( '' === $title || '' === $slug ) {
				throw new RuntimeException( 'Укажите название и slug.' );
			}

			self::ensure_storage_root();

			$result = $this->register_existing_package( $slug, $title );
			$trees  = $this->get_trees();
			$trees[ $slug ] = $result;
			update_option( self::OPTION_TREES, $trees, false );

			wp_safe_redirect( add_query_arg( 'drevo_notice', rawurlencode( 'Папка родословной зарегистрирована.' ), $redirect ) );
			exit;
		} catch ( Exception $e ) {
			wp_safe_redirect( add_query_arg( 'drevo_error', rawurlencode( $e->getMessage() ), $redirect ) );
			exit;
		}
	}

	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'slug'   => '',
				'height' => '75vh',
				'title'  => '',
			),
			$atts,
			self::SHORTCODE
		);

		$slug = sanitize_title( $atts['slug'] );
		if ( '' === $slug ) {
			return '';
		}

		$trees = $this->get_trees();
		if ( empty( $trees[ $slug ] ) ) {
			return current_user_can( 'manage_options' ) ? '<p>Родословная не найдена: <code>' . esc_html( $slug ) . '</code></p>' : '';
		}

		$tree   = $trees[ $slug ];
		$url    = $this->get_tree_url( $slug ) . 'index.html';
		$title  = '' !== $atts['title'] ? $atts['title'] : $tree['title'];
		$height = preg_match( '/^[0-9.]+(px|vh|vw|rem|em|%)$/', $atts['height'] ) ? $atts['height'] : '75vh';

		wp_enqueue_style( 'drevo-genealogy-viewer' );
		wp_enqueue_script( 'drevo-genealogy-viewer' );

		ob_start();
		?>
		<div class="drevo-genealogy-tree" style="--drevo-tree-height: <?php echo esc_attr( $height ); ?>">
			<div class="drevo-genealogy-toolbar">
				<strong class="drevo-genealogy-title"><?php echo esc_html( $title ); ?></strong>
				<div class="drevo-genealogy-actions">
					<a class="drevo-genealogy-button" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">Открыть в новой вкладке</a>
					<button class="drevo-genealogy-button" type="button" data-drevo-fullscreen>На весь экран</button>
				</div>
			</div>
			<iframe
				class="drevo-genealogy-frame"
				src="<?php echo esc_url( $url ); ?>"
				title="<?php echo esc_attr( $title ); ?>"
				loading="lazy"
				allowfullscreen
				sandbox="allow-scripts allow-same-origin allow-popups"
				referrerpolicy="no-referrer"></iframe>
		</div>
		<?php

		return ob_get_clean();
	}

	private function extract_zip_package( $zip_path, $slug, $title ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			throw new RuntimeException( 'Не удалось открыть ZIP-файл.' );
		}

		$root          = self::get_storage_root();
		$target        = trailingslashit( $root ) . $slug;
		$tmp_target    = trailingslashit( $root ) . $slug . '.tmp-' . wp_generate_password( 8, false, false );
		$main_html     = '';
		$total_bytes   = 0;
		$total_files   = 0;
		$allowed_exts  = array( 'html', 'htm', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'css', 'js', 'txt', 'json' );
		$blocked_names = array( '.htaccess', 'web.config' );

		if ( $zip->numFiles > self::MAX_FILES ) {
			$zip->close();
			throw new RuntimeException( 'В архиве слишком много файлов.' );
		}

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$stat = $zip->statIndex( $i );
			if ( empty( $stat['name'] ) ) {
				continue;
			}

			$name = str_replace( '\\', '/', $stat['name'] );

			if ( $this->is_unsafe_zip_path( $name ) ) {
				$zip->close();
				throw new RuntimeException( 'Архив содержит небезопасный путь: ' . $name );
			}

			if ( '/' === substr( $name, -1 ) ) {
				continue;
			}

			$basename = strtolower( basename( $name ) );
			$ext      = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

			if ( in_array( $basename, $blocked_names, true ) || ! in_array( $ext, $allowed_exts, true ) ) {
				$zip->close();
				throw new RuntimeException( 'Недопустимый файл в архиве: ' . $name );
			}

			$total_files++;
			$total_bytes += isset( $stat['size'] ) ? (int) $stat['size'] : 0;

			if ( $total_bytes > self::MAX_UNPACKED_BYTES ) {
				$zip->close();
				throw new RuntimeException( 'Распакованный пакет слишком большой.' );
			}

			if ( '' === $main_html && preg_match( '/\.html?$/i', $name ) && false === strpos( $name, '/' ) ) {
				$main_html = $name;
			}
		}

		if ( '' === $main_html ) {
			$zip->close();
			throw new RuntimeException( 'В корне архива не найден HTML-файл родословной.' );
		}

		if ( ! wp_mkdir_p( $tmp_target ) ) {
			$zip->close();
			throw new RuntimeException( 'Не удалось создать временную папку.' );
		}

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$stat = $zip->statIndex( $i );
			if ( empty( $stat['name'] ) ) {
				continue;
			}

			$name = str_replace( '\\', '/', $stat['name'] );
			if ( '/' === substr( $name, -1 ) ) {
				continue;
			}

			$destination = trailingslashit( $tmp_target ) . $name;
			if ( ! wp_mkdir_p( dirname( $destination ) ) ) {
				$zip->close();
				$this->delete_tree_dir( $tmp_target );
				throw new RuntimeException( 'Не удалось создать папку для файла: ' . $name );
			}

			$contents = $zip->getFromIndex( $i );
			if ( false === $contents ) {
				$zip->close();
				$this->delete_tree_dir( $tmp_target );
				throw new RuntimeException( 'Не удалось прочитать файл из архива: ' . $name );
			}

			if ( false === file_put_contents( $destination, $contents ) ) {
				$zip->close();
				$this->delete_tree_dir( $tmp_target );
				throw new RuntimeException( 'Не удалось записать файл: ' . $name );
			}
		}

		$zip->close();

		if ( 'index.html' !== strtolower( $main_html ) ) {
			if ( ! copy( trailingslashit( $tmp_target ) . $main_html, trailingslashit( $tmp_target ) . 'index.html' ) ) {
				$this->delete_tree_dir( $tmp_target );
				throw new RuntimeException( 'Не удалось создать index.html.' );
			}
		}

		$privacy_stats = $this->apply_living_person_privacy_filter( $tmp_target );
		$this->write_package_guards( $tmp_target );

		$manifest = array(
			'title'       => $title,
			'slug'        => $slug,
			'main_html'   => $main_html,
			'files'       => $total_files,
			'bytes'       => $total_bytes,
			'privacy'     => $privacy_stats,
			'uploaded_at' => current_time( 'mysql' ),
		);

		file_put_contents(
			trailingslashit( $tmp_target ) . 'manifest.json',
			wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
		);

		if ( file_exists( $target ) ) {
			$this->delete_tree_dir( $target );
		}

		if ( ! rename( $tmp_target, $target ) ) {
			$this->delete_tree_dir( $tmp_target );
			throw new RuntimeException( 'Не удалось опубликовать пакет.' );
		}

		return $manifest;
	}

	private function register_existing_package( $slug, $title ) {
		$target = trailingslashit( self::get_storage_root() ) . $slug;

		if ( ! is_dir( $target ) ) {
			throw new RuntimeException( 'Папка не найдена: wp-content/genealogy/' . $slug );
		}

		if ( ! file_exists( trailingslashit( $target ) . 'index.html' ) ) {
			throw new RuntimeException( 'В папке нет index.html.' );
		}

		$stats = $this->scan_package_dir( $target );
		$privacy_stats = $this->apply_living_person_privacy_filter( $target );
		$this->write_package_guards( $target );

		$manifest = array(
			'title'       => $title,
			'slug'        => $slug,
			'main_html'   => 'index.html',
			'files'       => $stats['files'],
			'bytes'       => $stats['bytes'],
			'privacy'     => $privacy_stats,
			'uploaded_at' => current_time( 'mysql' ),
		);

		file_put_contents(
			trailingslashit( $target ) . 'manifest.json',
			wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
		);

		return $manifest;
	}

	private function scan_package_dir( $dir ) {
		$allowed_exts = array( 'html', 'htm', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'css', 'js', 'txt', 'json' );
		$blocked     = array( '.htaccess', 'web.config' );
		$files       = 0;
		$bytes       = 0;

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				continue;
			}

			$relative = ltrim( str_replace( '\\', '/', substr( $item->getPathname(), strlen( $dir ) ) ), '/' );
			if ( in_array( $relative, array( '.htaccess', 'index.php', 'manifest.json' ), true ) ) {
				continue;
			}

			$basename = strtolower( basename( $relative ) );
			$ext      = strtolower( pathinfo( $relative, PATHINFO_EXTENSION ) );

			if ( in_array( $basename, $blocked, true ) || ! in_array( $ext, $allowed_exts, true ) ) {
				throw new RuntimeException( 'Недопустимый файл в папке: ' . $relative );
			}

			$files++;
			$bytes += $item->getSize();

			if ( $files > self::MAX_FILES ) {
				throw new RuntimeException( 'В папке слишком много файлов.' );
			}

			if ( $bytes > self::MAX_UNPACKED_BYTES ) {
				throw new RuntimeException( 'Пакет слишком большой.' );
			}
		}

		return array(
			'files' => $files,
			'bytes' => $bytes,
		);
	}

	private function apply_living_person_privacy_filter( $dir ) {
		$stats = array(
			'processed_cards' => 0,
			'living_cards'   => 0,
			'contact_cards'  => 0,
		);

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $items as $item ) {
			if ( $item->isDir() || ! preg_match( '/^p[0-9]+\.html?$/i', $item->getFilename() ) ) {
				continue;
			}

			$stats['processed_cards']++;

			$path = $item->getPathname();
			$html = file_get_contents( $path );
			if ( false === $html ) {
				continue;
			}

			$filtered = $this->filter_contact_fields( $html );
			if ( $filtered !== $html ) {
				$stats['contact_cards']++;
			}

			$is_living = $this->is_living_person_card( $html );
			if ( $is_living ) {
				$living_filtered = $this->filter_living_person_card( $filtered );
				if ( $living_filtered ) {
					$filtered = $living_filtered;
				}
			}

			if ( $filtered !== $html ) {
				file_put_contents( $path, $filtered );
			}

			if ( $is_living && $filtered !== $html ) {
				$stats['living_cards']++;
			}
		}

		return $stats;
	}

	private function filter_contact_fields( $html ) {
		return preg_replace_callback(
			'/<p\b[^>]*>.*?<\/p>/is',
			function ( $match ) {
				$paragraph = $match[0];
				$inner     = preg_replace( '/^<p\b[^>]*>|<\/p>$/i', '', $paragraph );
				$lines     = preg_split( '/(<br\s*\/?>)/i', $inner, -1, PREG_SPLIT_DELIM_CAPTURE );
				$keep      = array();
				$removed   = false;

				for ( $i = 0; $i < count( $lines ); $i++ ) {
					$line = $lines[ $i ];
					if ( preg_match( '/^<br\s*\/?>$/i', $line ) ) {
						$keep[] = $line;
						continue;
					}

					$text = trim( wp_strip_all_tags( html_entity_decode( $line, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
					if ( preg_match( '/^(Домашний адрес|Телефон)\s*:/iu', $text ) ) {
						$removed = true;
						if ( isset( $lines[ $i + 1 ] ) && preg_match( '/^<br\s*\/?>$/i', $lines[ $i + 1 ] ) ) {
							$i++;
						} elseif ( ! empty( $keep ) && preg_match( '/^<br\s*\/?>$/i', end( $keep ) ) ) {
							array_pop( $keep );
						}
						continue;
					}

					$keep[] = $line;
				}

				if ( ! $removed ) {
					return $paragraph;
				}

				$inner = trim( implode( '', $keep ) );
				$inner = preg_replace( '/^(<br\s*\/?>\s*)+|(\s*<br\s*\/?>)+$/i', '', $inner );
				if ( '' === trim( wp_strip_all_tags( html_entity_decode( $inner, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) ) {
					return '';
				}

				return '<p>' . $inner . '</p>';
			},
			$html
		);
	}

	private function is_living_person_card( $html ) {
		return ! $this->is_dead_person_card( $html );
	}

	private function is_dead_person_card( $html ) {
		if ( ! preg_match( '/<h1[^>]*>(.*?)<\/h1>/is', $html, $match ) ) {
			return false;
		}

		$title = wp_strip_all_tags( html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( ! preg_match_all( '/\(([^()]*)\)/u', $title, $date_matches ) ) {
			return false;
		}

		foreach ( $date_matches[1] as $date_range ) {
			if ( preg_match( '/\s-\s.+$/u', $date_range ) ) {
				return true;
			}
		}

		return false;
	}

	private function filter_living_person_card( $html ) {
		if ( ! preg_match( '/<div class="cwrap">\s*(<h1[^>]*>.*?<\/h1>)(.*?)(<div class="c"><\/div>)/is', $html, $match ) ) {
			return $html;
		}

		$head = substr( $html, 0, strpos( $html, $match[0] ) );
		$tail = substr( $html, strpos( $html, $match[0] ) + strlen( $match[0] ) );
		$body = $match[2];

		$main_photo = '';
		if ( preg_match( '/<div class="mblock"[^>]*>\s*(<a\b.*?<\/a>).*?<\/div>/is', $body, $photo_match ) ) {
			$main_photo = '<div class="mblock" style="width: 200px">' . $photo_match[1] . '</div>';
		}

		$paragraphs = array();
		if ( preg_match_all( '/<p\b[^>]*>.*?<\/p>/is', $body, $paragraph_matches ) ) {
			$paragraphs = $paragraph_matches[0];
		}

		$kept = array();
		foreach ( $paragraphs as $paragraph ) {
			$filtered_info = $this->filter_living_info_paragraph( $paragraph );
			if ( '' !== $filtered_info ) {
				$kept[] = $filtered_info;
				continue;
			}

			if ( $this->is_relations_paragraph( $paragraph ) ) {
				$kept[] = $paragraph;
				continue;
			}

			$birth = $this->filter_birth_paragraph( $paragraph );
			if ( '' !== $birth ) {
				$kept[] = $birth;
			}
		}

		$new_card = '<div class="cwrap">' . "\n" . $match[1] . "\n" . $main_photo . implode( '', $kept ) . "\n" . '<div class="c"></div>' . "\n" . '</div>';

		return $head . $new_card . $tail;
	}

	private function filter_living_info_paragraph( $paragraph ) {
		if ( false === strpos( $paragraph, 'Возраст:' ) && false === strpos( $paragraph, 'Место жительства:' ) ) {
			return '';
		}

		$inner = preg_replace( '/^<p\b[^>]*>|<\/p>$/i', '', $paragraph );
		$lines = preg_split( '/<br\s*\/?>/i', $inner );
		$keep = array();

		foreach ( $lines as $line ) {
			$text = trim( wp_strip_all_tags( html_entity_decode( $line, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
			if ( preg_match( '/^(Возраст|Место жительства):/u', $text ) ) {
				$keep[] = trim( $line );
			}
		}

		return empty( $keep ) ? '' : '<p>' . implode( '<br />', $keep ) . '<br /></p>';
	}

	private function is_relations_paragraph( $paragraph ) {
		$text = wp_strip_all_tags( html_entity_decode( $paragraph, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		return (bool) preg_match( '/(^|\s)(Отец|Мать|Брат|Сестра|Единоутробная сестра|Единокровный брат|Муж|Жена|Бывший муж|Бывшая жена|Отец ребёнка|Мать ребёнка|Сын|Дочь):/u', $text );
	}

	private function filter_birth_paragraph( $paragraph ) {
		$text = wp_strip_all_tags( html_entity_decode( $paragraph, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( ! preg_match( '/^(Родился|Родилась):/u', trim( $text ) ) ) {
			return '';
		}

		$inner = preg_replace( '/^<p\b[^>]*>|<\/p>$/i', '', $paragraph );
		$inner = preg_replace( '/\s*(Отец|Мать|Сведения|Ссылка на источник):.*$/us', '', $inner );
		$inner = preg_replace( '/\.\s+.*/us', '.', $inner );
		$inner = trim( $inner );

		return '' === $inner ? '' : '<p>' . $inner . '</p>';
	}

	private function is_unsafe_zip_path( $path ) {
		return (
			'' === $path ||
			false !== strpos( $path, '../' ) ||
			false !== strpos( $path, '..\\' ) ||
			'/' === $path[0] ||
			preg_match( '#^[a-z]:#i', $path )
		);
	}

	private static function ensure_storage_root() {
		$root = self::get_storage_root();
		if ( ! wp_mkdir_p( $root ) ) {
			return false;
		}

		$index = trailingslashit( $root ) . 'index.html';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '' );
		}

		$htaccess = trailingslashit( $root ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents(
				$htaccess,
				"Options -Indexes\n<FilesMatch \"\\.(php|phtml|php[0-9])$\">\nRequire all denied\n</FilesMatch>\nRemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8\n<IfModule mod_php.c>\nphp_flag engine off\n</IfModule>\n"
			);
		}

		return true;
	}

	private function write_package_guards( $dir ) {
		file_put_contents( trailingslashit( $dir ) . 'index.php', "<?php\n// Silence is golden.\n" );
		file_put_contents(
			trailingslashit( $dir ) . '.htaccess',
			"Options -Indexes\n<FilesMatch \"\\.(php|phtml|php[0-9])$\">\nRequire all denied\n</FilesMatch>\nRemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8\n<IfModule mod_php.c>\nphp_flag engine off\n</IfModule>\n"
		);
	}

	private function get_trees() {
		$trees = get_option( self::OPTION_TREES, array() );
		return is_array( $trees ) ? $trees : array();
	}

	private static function get_storage_root() {
		return trailingslashit( WP_CONTENT_DIR ) . 'genealogy';
	}

	private function get_tree_url( $slug ) {
		return trailingslashit( content_url( 'genealogy/' . sanitize_title( $slug ) ) );
	}

	private function delete_tree_dir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}

		rmdir( $dir );
	}
}

Drevo_Genealogy_Trees::instance();
