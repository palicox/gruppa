<?php
defined( 'ABSPATH' ) || exit;

/**
 * Gruppa Login — log in, lost password, new password (on the page) and log out.
 *
 *   Shortcode:  [gruppa_login redirect="/moj-profil/" register_url="/registracia/"]
 *   Elementor:  widget "Gruppa Login"
 *
 * Uses WordPress users (wp_signon, retrieve_password keys, reset_password).
 * REST: POST /wp-json/gruppa/v1/auth/login | auth/lost | auth/reset
 * Markup reuses the Dynamic Forms classes, so form styles apply.
 */
class Gruppa_Login {

	const MAX_FAILS = 5;
	const LOCK_MIN  = 15;

	private static $count = 0;

	public static function defaults() {
		return array(
			'redirect'        => '',          // after login; empty = same page
			'logout_redirect' => '',          // after logout; empty = same page
			'register_url'    => '',
			'register_text'   => __( 'Create account', 'gruppa-cms' ),
			'remember'        => 'yes',
			'lost'            => 'yes',
			'login_after_reset' => 'yes',
			'user_label'      => __( 'Email or username', 'gruppa-cms' ),
			'pass_label'      => __( 'Password', 'gruppa-cms' ),
			'remember_label'  => __( 'Remember me', 'gruppa-cms' ),
			'button'          => __( 'Log in', 'gruppa-cms' ),
			'lost_text'       => __( 'Forgot your password?', 'gruppa-cms' ),
			'lost_intro'      => __( 'Enter your email or username. We will send you a link to set a new password.', 'gruppa-cms' ),
			'lost_button'     => __( 'Send link', 'gruppa-cms' ),
			'back_text'       => __( 'Back to log in', 'gruppa-cms' ),
			'reset_label'     => __( 'New password', 'gruppa-cms' ),
			'reset_button'    => __( 'Save password', 'gruppa-cms' ),
			'logged_in_text'  => __( 'Logged in as {user_name}.', 'gruppa-cms' ),
			'logout_text'     => __( 'Log out', 'gruppa-cms' ),
		);
	}

	public static function init() {
		add_shortcode( 'gruppa_login', array( __CLASS__, 'shortcode' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function shortcode( $atts ) {
		return self::render( shortcode_atts( self::defaults(), $atts, 'gruppa_login' ) );
	}

	private static function current_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore
		return remove_query_arg( array( 'gl_action', 'key', 'login', 'gl_msg' ), home_url( $uri ) );
	}

	private static function field( $name, $label, $type, $attrs = '' ) {
		printf(
			'<div class="gruppa-form-field" data-key="%1$s"><label class="gruppa-form-label" for="%2$s">%3$s</label><input type="%4$s" id="%2$s" name="%1$s" %5$s><div class="gruppa-form-error" hidden></div></div>',
			esc_attr( $name ), esc_attr( 'gl-' . self::$count . '-' . $name ), esc_html( $label ), esc_attr( $type ), $attrs // phpcs:ignore
		);
	}

	private static function open_form( $view, $endpoint, $after, $hidden = false ) {
		printf(
			'<form class="gruppa-form gruppa-login-form" data-view="%s" novalidate data-endpoint="%s" data-nonce="" data-after="%s" data-redirect="" data-success="" data-confirm=""%s>',
			esc_attr( $view ), esc_url( rest_url( Gruppa_REST::NS . '/auth/' . $endpoint ) ), esc_attr( $after ), $hidden ? ' hidden' : ''
		);
		echo '<div class="gruppa-form-hp" aria-hidden="true"><input type="text" name="_gf_hp" tabindex="-1" autocomplete="off"></div>';
		echo '<div class="gruppa-form-fields">';
	}

	private static function close_form( $button, $links = '' ) {
		echo '</div>';
		printf( '<div class="gruppa-form-submit" data-align="left"><button type="submit" class="gruppa-form-button">%s</button></div>', esc_html( $button ) );
		if ( $links ) {
			echo '<div class="gruppa-login-links">' . $links . '</div>'; // phpcs:ignore
		}
		echo '<div class="gruppa-form-notice" role="status" hidden></div></form>';
	}

	public static function render( $o ) {
		$o       = wp_parse_args( $o, self::defaults() );
		$preview = isset( $o['_preview'] ) ? $o['_preview'] : ''; // Elementor editor: login | lost | logged
		wp_enqueue_style( 'gruppa-form' );
		wp_enqueue_script( 'gruppa-form' );
		self::$count++;
		$here = self::current_url();

		ob_start();
		echo '<div class="gruppa-login">';

		/* Logged in */
		if ( ( is_user_logged_in() && '' === $preview ) || 'logged' === $preview ) {
			$u    = wp_get_current_user();
			$text = str_replace( array( '{user_name}', '{user_email}' ), array( $u->display_name, $u->user_email ), $o['logged_in_text'] );
			printf(
				'<div class="gruppa-login-status"><span class="gruppa-login-user">%s</span> <a class="gruppa-login-logout" href="%s">%s</a></div>',
				esc_html( $text ),
				esc_url( wp_logout_url( '' !== $o['logout_redirect'] ? $o['logout_redirect'] : $here ) ),
				esc_html( $o['logout_text'] )
			);
			echo '</div>';
			return ob_get_clean();
		}

		/* New password (link from the email) */
		$action = isset( $_GET['gl_action'] ) ? sanitize_key( $_GET['gl_action'] ) : ''; // phpcs:ignore
		if ( 'rp' === $action ) {
			$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore
			$login = isset( $_GET['login'] ) ? sanitize_user( wp_unslash( $_GET['login'] ) ) : ''; // phpcs:ignore
			$user  = check_password_reset_key( $key, $login );
			if ( is_wp_error( $user ) ) {
				echo '<div class="gruppa-form-notice is-error">This link is invalid or has expired. Request a new one.</div>';
			} else {
				self::open_form( 'reset', 'reset', 'hide' );
				printf( '<input type="hidden" name="key" value="%s"><input type="hidden" name="login" value="%s">', esc_attr( $key ), esc_attr( $login ) );
				printf( '<input type="hidden" name="redirect" value="%s">', esc_attr( '' !== $o['redirect'] ? $o['redirect'] : $here ) );
				printf( '<input type="hidden" name="auto_login" value="%s">', 'yes' === $o['login_after_reset'] ? '1' : '0' );
				printf( '<input type="hidden" name="page" value="%s">', esc_attr( $here ) );
				self::field( 'pass', $o['reset_label'], 'password', 'autocomplete="new-password" required minlength="8"' );
				self::close_form( $o['reset_button'] );
				echo '</div>';
				return ob_get_clean();
			}
		}

		/* Log in */
		if ( isset( $_GET['gl_msg'] ) && 'reset' === $_GET['gl_msg'] ) { // phpcs:ignore
			echo '<div class="gruppa-form-notice is-success">Your password has been changed. You can log in now.</div>';
		}
		$links = array();
		if ( 'yes' === $o['lost'] ) {
			$links[] = '<a href="#" data-gl-show="lost">' . esc_html( $o['lost_text'] ) . '</a>';
		}
		if ( '' !== $o['register_url'] ) {
			$links[] = '<a href="' . esc_url( $o['register_url'] ) . '">' . esc_html( $o['register_text'] ) . '</a>';
		}
		self::open_form( 'login', 'login', 'keep', 'lost' === $preview );
		printf( '<input type="hidden" name="redirect" value="%s">', esc_attr( '' !== $o['redirect'] ? $o['redirect'] : $here ) );
		self::field( 'log', $o['user_label'], 'text', 'autocomplete="username" required' );
		self::field( 'pwd', $o['pass_label'], 'password', 'autocomplete="current-password" required' );
		if ( 'yes' === $o['remember'] ) {
			printf(
				'<div class="gruppa-form-field gruppa-form-field--checkbox" data-key="remember"><label class="gruppa-form-check"><input type="checkbox" name="remember" value="1"> <span>%s</span></label></div>',
				esc_html( $o['remember_label'] )
			);
		}
		self::close_form( $o['button'], implode( '<span class="gruppa-login-sep"> · </span>', $links ) );

		/* Lost password */
		if ( 'yes' === $o['lost'] ) {
			self::open_form( 'lost', 'lost', 'hide', 'lost' !== $preview );
			printf( '<input type="hidden" name="page" value="%s">', esc_attr( $here ) );
			echo '<p class="gruppa-login-intro">' . esc_html( $o['lost_intro'] ) . '</p>';
			self::field( 'user_login', $o['user_label'], 'text', 'autocomplete="username" required' );
			self::close_form( $o['lost_button'], '<a href="#" data-gl-show="login">' . esc_html( $o['back_text'] ) . '</a>' );
		}

		echo '</div>';
		return ob_get_clean();
	}

	/* ======================================================
	 * REST
	 * ==================================================== */

	public static function routes() {
		foreach ( array( 'login', 'lost', 'reset' ) as $r ) {
			register_rest_route( Gruppa_REST::NS, '/auth/' . $r, array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, $r ),
				'permission_callback' => '__return_true',
			) );
		}
	}

	private static function error( $msg, $fields = array(), $status = 400 ) {
		return new WP_Error( 'gruppa_auth', $msg, array( 'status' => $status, 'fields' => $fields ) );
	}

	/** Same-site redirect only. */
	private static function safe_redirect( $url ) {
		$url = esc_url_raw( (string) $url );
		return $url ? wp_validate_redirect( $url, home_url( '/' ) ) : home_url( '/' );
	}

	private static function lock_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : ''; // phpcs:ignore
		return 'gruppa_login_' . md5( $ip );
	}

	public static function login( $request ) {
		if ( '' !== (string) $request->get_param( '_gf_hp' ) ) {
			return self::error( __( 'Login failed.', 'gruppa-cms' ) );
		}
		if ( is_user_logged_in() ) {
			return rest_ensure_response( array( 'ok' => true, 'redirect' => self::safe_redirect( $request->get_param( 'redirect' ) ) ) );
		}
		$fails = (int) get_transient( self::lock_key() );
		if ( $fails >= self::MAX_FAILS ) {
			return self::error( sprintf( __( 'Too many failed attempts. Try again in %d minutes.', 'gruppa-cms' ), self::LOCK_MIN ), array(), 429 );
		}
		$log = trim( (string) $request->get_param( 'log' ) );
		$pwd = (string) $request->get_param( 'pwd' );
		$req = array();
		if ( '' === $log ) {
			$req['log'] = __( 'Required', 'gruppa-cms' );
		}
		if ( '' === $pwd ) {
			$req['pwd'] = __( 'Required', 'gruppa-cms' );
		}
		if ( $req ) {
			return self::error( __( 'Please fill in both fields.', 'gruppa-cms' ), $req );
		}

		$user = wp_signon( array(
			'user_login'    => $log,
			'user_password' => $pwd,
			'remember'      => (bool) $request->get_param( 'remember' ),
		), is_ssl() );

		if ( is_wp_error( $user ) ) {
			set_transient( self::lock_key(), $fails + 1, self::LOCK_MIN * MINUTE_IN_SECONDS );
			// One generic message: no hint whether the account exists.
			return self::error( __( 'Wrong email / username or password.', 'gruppa-cms' ), array( 'pwd' => __( 'Wrong email / username or password.', 'gruppa-cms' ) ), 401 );
		}
		delete_transient( self::lock_key() );
		wp_set_current_user( $user->ID );

		$to = apply_filters( 'gruppa_cms/login_redirect', self::safe_redirect( $request->get_param( 'redirect' ) ), $user );
		return rest_ensure_response( array( 'ok' => true, 'message' => '', 'redirect' => $to ) );
	}

	public static function lost( $request ) {
		$generic = rest_ensure_response( array( 'ok' => true, 'message' => __( 'If the account exists, we have sent an email with a link to set a new password.', 'gruppa-cms' ) ) );
		if ( '' !== (string) $request->get_param( '_gf_hp' ) ) {
			return $generic;
		}
		$login = trim( (string) $request->get_param( 'user_login' ) );
		if ( '' === $login ) {
			return self::error( __( 'Please enter your email or username.', 'gruppa-cms' ), array( 'user_login' => __( 'Required', 'gruppa-cms' ) ) );
		}
		$user = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', sanitize_user( $login ) );
		if ( ! $user ) {
			return $generic;
		}
		$page = self::safe_redirect( $request->get_param( 'page' ) );
		$key  = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			return $generic;
		}
		$link = add_query_arg( array( 'gl_action' => 'rp', 'key' => $key, 'login' => rawurlencode( $user->user_login ) ), $page );
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		$message = "Someone has requested a password reset for the following account:\r\n\r\n"
			. 'Site: ' . $site . "\r\n"
			. 'Username: ' . $user->user_login . "\r\n\r\n"
			. "If this was a mistake, ignore this email and nothing will happen.\r\n\r\n"
			. "To set a new password, visit:\r\n" . $link . "\r\n";
		$subject = '[' . $site . '] Password reset';

		$mail = apply_filters( 'gruppa_cms/lost_password_email', array( 'subject' => $subject, 'message' => $message ), $user, $link );
		wp_mail( $user->user_email, $mail['subject'], $mail['message'] );
		return $generic;
	}

	public static function reset( $request ) {
		$key   = (string) $request->get_param( 'key' );
		$login = sanitize_user( (string) $request->get_param( 'login' ) );
		$pass  = (string) $request->get_param( 'pass' );

		$user = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) ) {
			return self::error( __( 'This link is invalid or has expired. Request a new one.', 'gruppa-cms' ) );
		}
		if ( strlen( $pass ) < 8 ) {
			return self::error( __( 'At least 8 characters.', 'gruppa-cms' ), array( 'pass' => __( 'At least 8 characters.', 'gruppa-cms' ) ) );
		}
		reset_password( $user, $pass );

		if ( $request->get_param( 'auto_login' ) ) {
			wp_set_current_user( $user->ID );
			wp_set_auth_cookie( $user->ID, false, is_ssl() );
			$redirect = self::safe_redirect( $request->get_param( 'redirect' ) );
		} else {
			// Back to the clean page with the login form and a "password changed" notice.
			$redirect = add_query_arg( 'gl_msg', 'reset', self::safe_redirect( $request->get_param( 'page' ) ) );
		}
		return rest_ensure_response( array( 'ok' => true, 'message' => '', 'redirect' => $redirect ) );
	}
}
