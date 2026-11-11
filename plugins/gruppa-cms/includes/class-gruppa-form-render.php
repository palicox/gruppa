<?php
defined( 'ABSPATH' ) || exit;

/**
 * Dynamic Forms on the frontend.
 *
 *   Shortcode:  [gruppa_form form="slug"]   (or form="12" / id="12")
 *   Elementor:  widget "Gruppa Form" (category Gruppa CMS)
 *   Gutenberg:  Shortcode block with the shortcode above
 *
 * Submission: POST /wp-json/gruppa/v1/form-submit/{form}
 * The form itself is the gate – the type's REST API switch is not needed,
 * but the type's access rules (Create / Update) are respected.
 */
class Gruppa_Form_Render {

	const ROUTE = 'form-submit';

	private static $count = 0;

	public static function init() {
		add_shortcode( 'gruppa_form', array( __CLASS__, 'shortcode' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		$url = GRUPPA_CMS_URL . 'assets/';
		wp_register_style( 'gruppa-form', $url . 'gruppa-form.css', array(), GRUPPA_CMS_VERSION );
		wp_register_script( 'gruppa-form', $url . 'gruppa-form.js', array(), GRUPPA_CMS_VERSION, true );
		wp_localize_script(
			'gruppa-form',
			'gruppaFormL10n',
			array(
				/* translators: %d: number of files */
				'maxFiles'   => __( 'Max %d files.', 'gruppa-cms' ),
				/* translators: %s: file name */
				'fileType'   => __( '%s: file type not allowed.', 'gruppa-cms' ),
				/* translators: 1: file name, 2: size limit */
				'fileSize'   => __( '%1$s: max %2$s.', 'gruppa-cms' ),
				'required'   => __( 'Required', 'gruppa-cms' ),
				'error'      => __( 'Error.', 'gruppa-cms' ),
				'requestErr' => __( 'Request failed. Please try again.', 'gruppa-cms' ),
			)
		);
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'form' => '', 'id' => '' ), $atts, 'gruppa_form' );
		$ref  = '' !== $atts['form'] ? $atts['form'] : $atts['id'];
		return self::render( $ref );
	}

	/* ======================================================
	 * Render
	 * ==================================================== */

	/** Item the update form edits, or null. */
	private static function target_item( $form, $type ) {
		$s = $form->settings;
		if ( 'user' === $s['item_source'] ) {
			return self::user_item( $type );
		}
		if ( 'current' === $s['item_source'] ) {
			$hit = Gruppa_Render::item( $type->slug, 'current' );
			return $hit ? $hit[1] : null;
		}
		$id = isset( $_GET[ $s['item_param'] ] ) ? absint( $_GET[ $s['item_param'] ] ) : 0; // phpcs:ignore
		return $id ? Gruppa_Items::get( $type, $id ) : null;
	}

	/** Newest item whose author is the logged-in user. */
	public static function user_item( $type ) {
		$uid = get_current_user_id();
		if ( ! $uid ) {
			return null;
		}
		global $wpdb;
		$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM `' . Gruppa_DB::data_table( $type->slug ) . '` WHERE author = %d ORDER BY id DESC LIMIT 1', $uid ) ); // phpcs:ignore
		return $id ? Gruppa_Items::get( $type, $id ) : null;
	}

	private static function message( $text ) {
		return '<div class="gruppa-form-message">' . esc_html( $text ) . '</div>';
	}

	public static function render( $ref ) {
		if ( '' === (string) $ref ) {
			return self::is_editor() ? self::message( 'Gruppa Form: select a form.' ) : '';
		}
		$form = Gruppa_Forms::get_by( $ref );
		$type = $form ? Gruppa_Types::get_by_slug( $form->type_slug ) : null;
		if ( ! $form || 'publish' !== $form->status || ! $type || 'publish' !== $type->status ) {
			return self::is_editor() ? self::message( 'Gruppa Form: form not found or not published.' ) : '';
		}

		$s    = $form->settings;
		$mode = $s['mode'];
		$item = null;
		if ( 'create' !== $mode ) {
			$item = self::target_item( $form, $type );
			if ( ! $item ) {
				if ( 'user' === $s['item_source'] ) {
					return self::message( is_user_logged_in() ? __( 'Nothing to edit yet.', 'gruppa-cms' ) : __( 'Please log in.', 'gruppa-cms' ) );
				}
				return self::message( __( 'Item not found.', 'gruppa-cms' ) );
			}
		}
		if ( ! Gruppa_Forms::can( $form, $type, $item ) ) {
			return self::message( is_user_logged_in() ? __( 'You are not allowed to use this form.', 'gruppa-cms' ) : __( 'Please log in to use this form.', 'gruppa-cms' ) );
		}

		wp_enqueue_style( 'gruppa-form' );
		wp_enqueue_script( 'gruppa-form' );

		$fields = array();
		foreach ( Gruppa_Types::fields_all( $type ) as $f ) {
			if ( Gruppa_Relations::is_link( $f ) ) {
				$f['published_only'] = true; // Visitors pick only from published values.
			}
			$fields[ $f['key'] ] = $f;
		}

		self::$count++;
		$uid = 'gruppa-form-' . $form->id . '-' . self::$count;

		ob_start();
		// Administrators always pass — tell them when visitors would not see the form.
		if ( current_user_can( 'manage_options' ) && ! Gruppa_Forms::can( $form, $type, $item, 0 ) ) {
			$opts = Gruppa_Forms::access_options( $mode );
			$rule = Gruppa_Forms::rule( $form, $type );
			printf(
				'<div class="gruppa-form-message gruppa-form-admin-note">%s</div>',
				esc_html( sprintf( 'Only for admins: visitors who are not logged in cannot use this form (Who can submit: %s). Change it in Gruppa CMS → Forms → %s.', isset( $opts[ $rule ] ) ? $opts[ $rule ] : $rule, $form->name ) )
			);
		}
		printf(
			'<form class="gruppa-form gruppa-form--%s" id="%s" enctype="multipart/form-data" novalidate data-endpoint="%s" data-nonce="%s" data-after="%s" data-redirect="%s" data-success="%s" data-confirm="%s">',
			esc_attr( $mode ),
			esc_attr( $uid ),
			esc_url( rest_url( Gruppa_REST::NS . '/' . self::ROUTE . '/' . $form->id ) ),
			esc_attr( is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '' ),
			esc_attr( $s['after_success'] ),
			esc_attr( $s['redirect'] ),
			esc_attr( Gruppa_I18n::text( $s['success_message'], 'Thank you.' ) ),
			esc_attr( 'delete' === $mode ? $s['confirm_text'] : '' )
		);
		if ( $item ) {
			printf( '<input type="hidden" name="_item" value="%d">', (int) $item->id );
		}
		// Honeypot.
		echo '<div class="gruppa-form-hp" aria-hidden="true"><input type="text" name="_gf_hp" tabindex="-1" autocomplete="off"></div>';

		echo '<div class="gruppa-form-fields">';
		foreach ( 'delete' === $mode ? array() : $s['layout'] as $e ) {
			if ( $e['hidden'] ) {
				continue; // Value is enforced on the server.
			}
			$key = $e['key'];

			if ( isset( Gruppa_Forms::SYSTEM[ $key ] ) ) {
				$f = Gruppa_Fields::normalize( array(
					'key'      => $key,
					'label'    => Gruppa_Forms::SYSTEM[ $key ],
					'type'     => 'status' === $key ? 'select' : 'text',
					'required' => 'title' === $key,
					'options'  => 'status' === $key ? array(
						array( 'value' => 'publish', 'label' => __( 'Published', 'gruppa-cms' ) ),
						array( 'value' => 'draft', 'label' => __( 'Draft', 'gruppa-cms' ) ),
					) : array(),
				) );
				$val = $item ? $item->{$key} : ( 'status' === $key ? 'publish' : '' );
			} elseif ( isset( $fields[ $key ] ) ) {
				$f   = $fields[ $key ];
				$val = $item ? Gruppa_Fields::decode( $f, isset( $item->{$key} ) ? $item->{$key} : null ) : Gruppa_Fields::default_value( $f );
			} else {
				continue;
			}

			// Form-level overrides.
			if ( '' !== $e['label'] ) {
				$f['label'] = $e['label'];
			}
			if ( '' !== $e['placeholder'] ) {
				$f['placeholder'] = $e['placeholder'];
			}
			if ( '' !== $e['description'] ) {
				$f['description'] = $e['description'];
			}
			$displays = isset( $fields[ $key ] ) ? Gruppa_Forms::displays( $fields[ $key ] ) : array();
			$display  = ! empty( $e['display'] ) && isset( $displays[ $e['display'] ] ) ? $e['display'] : ( $displays ? key( $displays ) : $f['type'] );

			$w = (int) $e['width'];
			printf(
				'<div class="gruppa-form-field gruppa-form-field--%s" style="--gf-w:%s%%" data-key="%s">',
				esc_attr( 'media' === $f['type'] ? 'media' : $display ), esc_attr( 66 === $w ? '66.666' : ( 33 === $w ? '33.333' : $w ) ), esc_attr( $key )
			);
			printf(
				'<label class="gruppa-form-label" for="gf-%s">%s%s</label>',
				esc_attr( $key ), esc_html( $f['label'] ), $f['required'] ? ' <span class="gruppa-form-req">*</span>' : ''
			);
			self::input( $f, $val, $display, $uid );
			echo '<div class="gruppa-form-error" hidden></div>';
			echo '</div>';
		}
		echo '</div>';

		printf(
			'<div class="gruppa-form-submit" data-align="%s"><button type="submit" class="gruppa-form-button">%s</button></div>',
			esc_attr( $s['submit_align'] ), esc_html( Gruppa_I18n::text( $s['submit_text'], 'Send' ) )
		);
		echo '<div class="gruppa-form-notice" role="status" hidden></div>';
		echo '</form>';
		return ob_get_clean();
	}

	/** One input, honouring the form's "Display as" choice. */
	private static function input( $f, $val, $display, $uid ) {
		switch ( $f['type'] . '>' . $display ) {
			case 'textarea>editor':
			case 'editor>editor':
				wp_editor( (string) $val, preg_replace( '/[^a-z0-9_]/', '_', strtolower( $uid . '_' . $f['key'] ) ), array(
					'textarea_name' => 'gf[' . $f['key'] . ']',
					'textarea_rows' => 8,
					'media_buttons' => false,
					'teeny'         => true,
					'quicktags'     => false,
					'editor_class'  => 'gruppa-form-editor',
					'tinymce'       => array(
						'resize'    => 'vertical',
						'statusbar' => true,
						// Neutral sans-serif inside the editor (WordPress default is a serif).
						// No double quotes here: WordPress prints this into JS inside "…" without escaping.
						'content_style' => "body#tinymce,body.mce-content-body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif!important;font-size:15px!important;line-height:1.6!important;color:#1d2327!important;margin:12px 14px!important;max-width:none!important}",
					),
				) );
				if ( $f['description'] ) {
					echo '<p class="description">' . esc_html( $f['description'] ) . '</p>';
				}
				return;
			case 'editor>textarea':
				$f['type'] = 'textarea';
				break;
			case 'select>radio':
				$f['type'] = 'radio';
				break;
			case 'select>checkbox':
				$f['type'] = 'checkbox';
				break;
			case 'radio>select':
				$f['type']     = 'select';
				$f['multiple'] = false;
				break;
			case 'checkbox>select':
				$f['type']     = 'select';
				$f['multiple'] = true;
				break;
			case 'relation>list':
			case 'relation>select':
				$f['ui'] = $display;
				break;
			case 'switcher>checkbox':
				printf(
					'<input type="hidden" name="gf[%1$s]" value="0"><label class="gruppa-form-check"><input type="checkbox" id="gf-%1$s" name="gf[%1$s]" value="1" %2$s> <span>%3$s</span></label>',
					esc_attr( $f['key'] ), checked( (int) $val, 1, false ), esc_html( $f['placeholder'] )
				);
				if ( $f['description'] ) {
					echo '<p class="description">' . esc_html( $f['description'] ) . '</p>';
				}
				return;
		}
		if ( 'media' === $f['type'] ) {
			self::media_input( $f, $val );
			return;
		}
		if ( 'editor' === $f['type'] ) {
			$f['type'] = 'textarea';
		}
		Gruppa_Fields::render_input( $f, $val );
	}

	/** Attachment IDs currently stored in a media value (IDs or URLs). */
	private static function media_ids( $val ) {
		$ids = array();
		foreach ( (array) $val as $v ) {
			if ( is_numeric( $v ) ) {
				$ids[] = (int) $v;
			} elseif ( is_string( $v ) && '' !== $v ) {
				$aid = attachment_url_to_postid( $v );
				if ( $aid ) {
					$ids[] = $aid;
				}
			}
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	private static function media_input( $f, $val ) {
		$key = $f['key'];
		if ( ! is_user_logged_in() ) {
			echo '<p class="gruppa-form-login">Log in to upload files.</p>';
			return;
		}
		$ids   = self::media_ids( $val );
		$max   = (int) $f['max_files'];
		$exts  = '' !== $f['file_types'] ? explode( ',', $f['file_types'] ) : array();
		$limit = (int) wp_max_upload_size();
		if ( $f['max_mb'] > 0 ) {
			$limit = min( $limit, (int) ( $f['max_mb'] * MB_IN_BYTES ) );
		}
		printf(
			'<div class="gruppa-form-media" data-max="%d" data-bytes="%d" data-types="%s">',
			$max, $limit, esc_attr( implode( ',', $exts ) )
		);
		printf( '<input type="hidden" name="gf[%s]" value="%s" class="gruppa-form-media-keep">', esc_attr( $key ), esc_attr( implode( ',', $ids ) ) );
		echo '<ul class="gruppa-form-media-list">';
		foreach ( $ids as $aid ) {
			$thumb = wp_attachment_is_image( $aid ) ? wp_get_attachment_image_url( $aid, 'thumbnail' ) : wp_mime_type_icon( $aid );
			printf(
				'<li data-id="%d"><img src="%s" alt=""><span>%s</span><button type="button" class="gruppa-form-media-remove" aria-label="Remove">&times;</button></li>',
				(int) $aid, esc_url( $thumb ), esc_html( wp_basename( (string) get_attached_file( $aid ) ) )
			);
		}
		echo '</ul>';
		printf(
			'<input type="file" id="gf-%s" name="gf_files_%s[]" class="gruppa-form-file" tabindex="-1"%s%s>',
			esc_attr( $key ), esc_attr( $key ),
			1 === $max ? '' : ' multiple',
			$exts ? ' accept="' . esc_attr( '.' . implode( ',.', $exts ) ) . '"' : ''
		);
		$empty = 1 === $max ? __( 'No file chosen', 'gruppa-cms' ) : __( 'No files chosen', 'gruppa-cms' );
		printf(
			'<div class="gruppa-form-upload-row"><button type="button" class="gruppa-form-upload" aria-controls="gf-%s">%s</button><span class="gruppa-form-file-names" data-empty="%s">%s</span></div>',
			esc_attr( $key ),
			esc_html( '' !== $f['placeholder'] ? $f['placeholder'] : ( 1 === $max ? __( 'Choose file', 'gruppa-cms' ) : __( 'Choose files', 'gruppa-cms' ) ) ),
			esc_attr( $empty ), esc_html( $empty )
		);
		// Note: the form's own text (builder → Note) or an automatic summary.
		if ( '' !== $f['description'] ) {
			$note = $f['description'];
		} else {
			$hint = array();
			if ( $exts ) {
				$hint[] = strtoupper( implode( ', ', $exts ) );
			}
			$hint[] = 'max ' . size_format( $limit );
			if ( $max > 1 ) {
				$hint[] = 'max ' . $max . ' files';
			}
			$note = implode( ' · ', $hint );
		}
		echo '<p class="gruppa-form-media-hint">' . esc_html( $note ) . '</p>';
		echo '</div>';
	}

	/**
	 * Upload files for the form's media fields (logged-in users only).
	 * Kept IDs are limited to files the item already had.
	 *
	 * @return array [ data, new attachment IDs, errors ]
	 */
	private static function handle_media( $form, $type, $data, $item ) {
		$fields = array();
		foreach ( $type->fields as $f ) {
			$fields[ $f['key'] ] = $f;
		}
		$new    = array();
		$errors = array();

		foreach ( $form->settings['layout'] as $e ) {
			$key = $e['key'];
			if ( $e['hidden'] || ! isset( $fields[ $key ] ) || 'media' !== $fields[ $key ]['type'] ) {
				continue;
			}
			$f     = $fields[ $key ];
			$label = '' !== $e['label'] ? $e['label'] : $f['label'];

			$had  = $item ? self::media_ids( Gruppa_Fields::decode( $f, isset( $item->{$key} ) ? $item->{$key} : null ) ) : array();
			$keep = array_intersect( array_map( 'absint', explode( ',', isset( $data[ $key ] ) ? (string) $data[ $key ] : '' ) ), $had );

			$files = self::files( 'gf_files_' . $key );
			if ( ! is_user_logged_in() ) {
				// Guests never touch media: no upload, stored files stay as they are.
				unset( $data[ $key ] );
				if ( $files ) {
					$errors[ $key ] = $label . ': log in to upload files';
				}
				continue;
			}
			$max = (int) $f['max_files'];
			if ( 1 === $max && $files ) {
				$keep  = array();
				$files = array_slice( $files, 0, 1 );
			}
			if ( $max > 0 && count( $keep ) + count( $files ) > $max ) {
				$errors[ $key ] = $label . ': max ' . $max . ' files';
				continue;
			}

			$exts = '' !== $f['file_types'] ? explode( ',', $f['file_types'] ) : array();
			$ids  = array();
			foreach ( $files as $file ) {
				$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
				if ( $exts && ! in_array( $ext, $exts, true ) ) {
					$errors[ $key ] = $label . ': file type not allowed (' . $ext . ')';
					break;
				}
				if ( $f['max_mb'] > 0 && $file['size'] > $f['max_mb'] * MB_IN_BYTES ) {
					$errors[ $key ] = $label . ': max ' . $f['max_mb'] . ' MB';
					break;
				}
				$aid = self::upload( $file );
				if ( is_wp_error( $aid ) ) {
					$errors[ $key ] = $label . ': ' . $aid->get_error_message();
					break;
				}
				$ids[] = $aid;
				$new[] = $aid;
			}
			$data[ $key ] = implode( ',', array_merge( array_values( $keep ), $ids ) );
		}
		return array( $data, $new, $errors );
	}

	/** Normalised list of uploaded files for an input name ("name[]" or single). */
	private static function files( $name ) {
		if ( empty( $_FILES[ $name ] ) || empty( $_FILES[ $name ]['name'] ) ) { // phpcs:ignore
			return array();
		}
		$f   = $_FILES[ $name ]; // phpcs:ignore
		$out = array();
		foreach ( (array) $f['name'] as $i => $n ) {
			$err = is_array( $f['error'] ) ? $f['error'][ $i ] : $f['error'];
			if ( '' === $n || UPLOAD_ERR_NO_FILE === (int) $err ) {
				continue;
			}
			$out[] = array(
				'name'     => is_array( $f['name'] ) ? $f['name'][ $i ] : $f['name'],
				'type'     => is_array( $f['type'] ) ? $f['type'][ $i ] : $f['type'],
				'tmp_name' => is_array( $f['tmp_name'] ) ? $f['tmp_name'][ $i ] : $f['tmp_name'],
				'error'    => $err,
				'size'     => is_array( $f['size'] ) ? $f['size'][ $i ] : $f['size'],
			);
		}
		return $out;
	}

	/** @return int|WP_Error attachment ID */
	private static function upload( $file ) {
		if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error( 'upload', 'upload failed (code ' . (int) $file['error'] . ')' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		// WordPress checks the real file type against allowed mime types.
		return media_handle_sideload( $file, 0 );
	}

	private static function is_editor() {
		return current_user_can( 'edit_posts' );
	}

	/* ======================================================
	 * Submit
	 * ==================================================== */

	public static function routes() {
		register_rest_route( Gruppa_REST::NS, '/' . self::ROUTE . '/(?P<form>[a-z0-9_]+)', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'submit' ),
			'permission_callback' => '__return_true', // Checked per form / type below.
		) );
	}

	public static function submit( $request ) {
		$form = Gruppa_Forms::get_by( $request['form'] );
		$type = $form ? Gruppa_Types::get_by_slug( $form->type_slug ) : null;
		if ( ! $form || 'publish' !== $form->status || ! $type || 'publish' !== $type->status ) {
			return new WP_Error( 'gruppa_form', __( 'Form not found.', 'gruppa-cms' ), array( 'status' => 404 ) );
		}
		$s    = $form->settings;
		$mode = $s['mode'];

		if ( '' !== (string) $request->get_param( '_gf_hp' ) ) {
			return rest_ensure_response( array( 'ok' => true, 'message' => Gruppa_I18n::text( $s['success_message'], 'Thank you.' ) ) ); // Bot.
		}
		if ( ! Gruppa_Forms::can( $form, $type ) ) {
			return new WP_Error( 'gruppa_forbidden', is_user_logged_in() ? __( 'You are not allowed to use this form.', 'gruppa-cms' ) : __( 'Please log in to use this form.', 'gruppa-cms' ), array( 'status' => is_user_logged_in() ? 403 : 401 ) );
		}

		$id = 0;
		if ( 'create' !== $mode ) {
			// "Item of current user" is resolved on the server, never taken from the request.
			$target = 'user' === $s['item_source'] ? self::user_item( $type ) : Gruppa_Items::get( $type, absint( $request->get_param( '_item' ) ) );
			if ( ! $target ) {
				return new WP_Error( 'gruppa_item_not_found', __( 'Item not found.', 'gruppa-cms' ), array( 'status' => 404 ) );
			}
			if ( ! Gruppa_Forms::can( $form, $type, $target ) ) {
				return new WP_Error( 'gruppa_forbidden', __( 'You can only change your own items.', 'gruppa-cms' ), array( 'status' => 403 ) );
			}
			$id = (int) $target->id;
		}

		$ctx = array(
			'form'     => $form,
			'type'     => $type,
			'mode'     => $mode,
			'data'     => array(),
			'item'     => null,
			'item_obj' => null,
		);

		/* ---- Delete ---- */
		if ( 'delete' === $mode ) {
			$obj             = Gruppa_Items::get( $type, $id );
			$ctx['item_obj'] = $obj;
			$ctx['item']     = Gruppa_Items::format( $type, $obj );
			$redirect        = self::redirect( $s['redirect'], $ctx );
			$message         = Gruppa_Form_Actions::replace( Gruppa_I18n::text( $s['success_message'], 'Thank you.' ), $ctx );
			Gruppa_Items::delete( $type, $id );
			Gruppa_Form_Actions::run_after( $ctx );
			do_action( 'gruppa_cms/form_submitted', $form, $type, $ctx['item'], $mode );
			return rest_ensure_response( array( 'ok' => true, 'id' => $id, 'message' => $message, 'redirect' => $redirect ) );
		}

		/* ---- Create / Update ---- */
		$is_guest = ! is_user_logged_in();
		$raw      = $request->get_param( 'gf' );
		$data     = Gruppa_Forms::apply( $form, $type, is_array( $raw ) ? $raw : array(), $mode );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$data = self::hidden_placeholders( $form, $type, $mode, $data );

		list( $data, $uploaded, $media_errors ) = self::handle_media( $form, $type, $data, $id ? Gruppa_Items::get( $type, $id ) : null );
		$cleanup = function () use ( $uploaded ) {
			foreach ( $uploaded as $aid ) {
				wp_delete_attachment( $aid, true );
			}
		};
		if ( $media_errors ) {
			$cleanup();
			return self::invalid( $media_errors );
		}
		// No visible Title element (missing or hidden with an empty value): generate one so the item can be created.
		if ( 'create' === $mode && ( ! isset( $data['title'] ) || '' === trim( (string) $data['title'] ) ) && ! self::in_layout( $form, 'title', true ) ) {
			$data['title'] = $form->name . ' – ' . current_time( 'Y-m-d H:i:s' );
		}

		list( $row, $errors ) = Gruppa_Items::from_data( $type, $data, 'create' === $mode );
		if ( $errors ) {
			$cleanup();
			return self::invalid( $errors );
		}

		// Moderation: status of new items (administrators are never moderated).
		if ( 'create' === $mode && ! current_user_can( 'manage_options' ) ) {
			if ( 'draft' === $s['new_status'] || ( 'draft_guests' === $s['new_status'] && $is_guest ) ) {
				$row['status'] = 'draft';
			}
		}

		// Actions that must succeed before saving (Register user).
		$ctx['data'] = $data;
		$ctx         = Gruppa_Form_Actions::run_before( $ctx );
		if ( is_wp_error( $ctx ) ) {
			$cleanup();
			return $ctx;
		}

		$saved = $row ? Gruppa_Items::save( $type, $row, $id ) : $id;
		if ( is_wp_error( $saved ) ) {
			$cleanup();
			return new WP_Error( 'gruppa_db', $saved->get_error_message(), array( 'status' => 500 ) );
		}
		self::save_links( $form, $type, (int) $saved, $data );
		$ctx['item_obj'] = Gruppa_Items::get( $type, (int) $saved );
		$ctx['item']     = Gruppa_Items::format( $type, $ctx['item_obj'] );

		Gruppa_Form_Actions::run_after( $ctx );
		do_action( 'gruppa_cms/form_submitted', $form, $type, $ctx['item'], $mode );

		$message = Gruppa_Form_Actions::replace( Gruppa_I18n::text( $s['success_message'], 'Thank you.' ), $ctx );
		if ( 'create' === $mode && 'draft' === $ctx['item']['status'] && ! empty( $row['status'] ) && $s['new_status'] !== 'publish' ) {
			$message = apply_filters( 'gruppa_cms/form_pending_message', $message, $form );
		}

		/*
		 * Whole response of a create / update submission — add-ons may change the message,
		 * e.g. Gruppa Payments when the payment gateway is not reachable.
		 */
		return rest_ensure_response( (array) apply_filters( 'gruppa_cms/form_response', array(
			'ok'       => true,
			'id'       => (int) $saved,
			'message'  => $message,
			/*
			 * Actions may send the visitor elsewhere, e.g. Gruppa Payments → payment gateway.
			 * @param string $redirect URL from the form settings ('' = stay on the page)
			 */
			'redirect' => (string) apply_filters( 'gruppa_cms/form_redirect', self::redirect( $s['redirect'], $ctx ), $ctx ),
		), $ctx ) );
	}

	/**
	 * Picked Data values of the form's link elements. Visitors can pick only published
	 * values; a hidden element takes IDs, titles or slugs separated by commas.
	 */
	private static function save_links( $form, $type, $item_id, $data ) {
		if ( ! $item_id ) {
			return;
		}
		$links = array();
		foreach ( Gruppa_Relations::links( $type ) as $l ) {
			$links[ $l['key'] ] = $l;
		}
		foreach ( $form->settings['layout'] as $e ) {
			$key = $e['key'];
			if ( ! isset( $links[ $key ] ) || ! array_key_exists( $key, $data ) ) {
				continue;
			}
			$l    = $links[ $key ];
			$vals = $data[ $key ];
			if ( ! is_array( $vals ) ) {
				$vals = '' === trim( (string) $vals ) ? array() : array_map( 'trim', explode( ',', (string) $vals ) );
			}
			$vals = array_filter( array_map( 'strval', array_filter( $vals, 'is_scalar' ) ), 'strlen' );
			if ( ! $e['hidden'] ) {
				$ok   = Gruppa_Relations::options( $l, true );
				$vals = array_filter( Gruppa_Relations::resolve( $l, $vals ), function ( $id ) use ( $ok ) {
					return isset( $ok[ $id ] );
				} );
			}
			Gruppa_Relations::set( $type, $item_id, $l, $vals );
		}
	}

	/**
	 * Hidden values may use {field} placeholders of the submitted fields,
	 * e.g. Title = "{meno_firma} – {email}".
	 */
	private static function hidden_placeholders( $form, $type, $mode, $data ) {
		$ctx = array( 'form' => $form, 'type' => $type, 'mode' => $mode, 'data' => $data, 'item' => null, 'item_obj' => null );
		foreach ( $form->settings['layout'] as $e ) {
			$k = $e['key'];
			if ( ! $e['hidden'] || ! isset( $data[ $k ] ) || ! is_string( $data[ $k ] ) || false === strpos( $data[ $k ], '{' ) ) {
				continue;
			}
			$ctx_k          = $ctx;
			$ctx_k['data'][ $k ] = ''; // A value never refers to itself.
			$data[ $k ]     = trim( Gruppa_Form_Actions::replace( $data[ $k ], $ctx_k ) );
		}
		return $data;
	}

	/** 400 with errors keyed by field. */
	private static function invalid( $errors ) {
		$fields = array();
		foreach ( $errors as $k => $m ) {
			$fields[ $k ] = preg_replace( '/^[^:]+:\s*/', '', $m );
		}
		return new WP_Error( 'gruppa_invalid', __( 'Please check the highlighted fields.', 'gruppa-cms' ), array( 'status' => 400, 'fields' => $fields ) );
	}

	private static function in_layout( $form, $key, $visible_only = false ) {
		foreach ( $form->settings['layout'] as $e ) {
			if ( $e['key'] === $key && ( ! $visible_only || ! $e['hidden'] ) ) {
				return true;
			}
		}
		return false;
	}

	/** Redirect URL with {placeholders} (URL-encoded values). */
	private static function redirect( $url, $ctx ) {
		if ( '' === $url ) {
			return '';
		}
		return esc_url_raw( Gruppa_Form_Actions::replace( $url, $ctx, 'url' ) );
	}

}
