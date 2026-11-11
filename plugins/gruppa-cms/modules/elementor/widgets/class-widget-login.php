<?php
defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/trait-gruppa-form-style.php';

/**
 * Elementor widget: Gruppa Login — log in, lost / new password, log out.
 */
class Gruppa_Widget_Login extends \Elementor\Widget_Base {

	use Gruppa_Form_Style;

	public function get_name() {
		return 'gruppa-login';
	}

	public function get_title() {
		return 'Gruppa Login';
	}

	public function get_icon() {
		return class_exists( 'Gruppa_Elementor' ) ? Gruppa_Elementor::icon( 'login' ) : 'eicon-lock-user';
	}

	public function get_categories() {
		return array( Gruppa_Elementor::CATEGORY );
	}

	public function get_keywords() {
		return array( 'gruppa', 'login', 'prihlasenie', 'logout', 'password' );
	}

	public function get_style_depends() {
		return array( 'gruppa-form' );
	}

	public function get_script_depends() {
		return array( 'gruppa-form' );
	}

	protected function register_controls() {
		$d   = Gruppa_Login::defaults();
		$url = function ( $id, $label, $desc ) {
			$this->add_control( $id, array(
				'label'       => $label,
				'type'        => \Elementor\Controls_Manager::URL,
				'options'     => false,
				'placeholder' => home_url( '/…' ),
				'description' => $desc,
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
			) );
		};
		$yes = function ( $id, $label, $default = 'yes' ) {
			$this->add_control( $id, array(
				'label'        => $label,
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => $default,
			) );
		};

		$this->start_controls_section( 'content', array( 'label' => 'Login' ) );
		$url( 'redirect', 'Redirect after login', 'Empty = stay on this page.' );
		$url( 'logout_redirect', 'Redirect after logout', 'Empty = this page.' );
		$url( 'register_url', 'Registration page', 'Shows a link under the form (page with your registration form).' );
		$yes( 'remember', 'Remember me' );
		$yes( 'lost', 'Lost password' );
		$yes( 'login_after_reset', 'Log in after new password' );
		$this->add_control( 'preview', array(
			'label'       => 'Editor preview',
			'type'        => \Elementor\Controls_Manager::SELECT,
			'options'     => array( 'login' => 'Login form', 'lost' => 'Lost password', 'logged' => 'Logged in' ),
			'default'     => 'login',
			'separator'   => 'before',
			'description' => 'Only in the Elementor editor, so you can style every state.',
		) );
		$this->end_controls_section();

		$this->start_controls_section( 'texts', array( 'label' => 'Texts' ) );
		$labels = array(
			'user_label'     => 'Username label',
			'pass_label'     => 'Password label',
			'remember_label' => 'Remember me',
			'button'         => 'Login button',
			'register_text'  => 'Registration link',
			'lost_text'      => 'Lost password link',
			'lost_intro'     => 'Lost password text',
			'lost_button'    => 'Send link button',
			'back_text'      => 'Back link',
			'reset_label'    => 'New password label',
			'reset_button'   => 'Save password button',
			'logged_in_text' => 'Logged in text',
			'logout_text'    => 'Logout link',
		);
		foreach ( $labels as $k => $l ) {
			$this->add_control( $k, array(
				'label'       => $l,
				'type'        => 'lost_intro' === $k ? \Elementor\Controls_Manager::TEXTAREA : \Elementor\Controls_Manager::TEXT,
				'default'     => $d[ $k ],
				'placeholder' => $d[ $k ],
				'label_block' => true,
				'description' => 'logged_in_text' === $k ? 'Placeholders: {user_name}, {user_email}' : '',
			) );
		}
		$this->end_controls_section();

		$this->style_form();
		$this->style_label();
		$this->style_text();
		$this->style_choice( 'checkbox' );
		$this->style_button();
		$this->style_links();
	}

	private function style_links() {
		$a  = '{{WRAPPER}} .gruppa-login .gruppa-login-links a, {{WRAPPER}} .gruppa-login .gruppa-login-logout';
		$ah = '{{WRAPPER}} .gruppa-login .gruppa-login-links a:hover, {{WRAPPER}} .gruppa-login .gruppa-login-logout:hover';
		$t  = '{{WRAPPER}} .gruppa-login .gruppa-login-user, {{WRAPPER}} .gruppa-login .gruppa-login-intro';
		$this->start_controls_section( 'style_links', array( 'label' => 'Links & texts', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'links_typography', 'label' => 'Links typography', 'selector' => $a ) );
		$this->color( 'links_color', 'Link color', array( $a => 'color: {{VALUE}};' ) );
		$this->color( 'links_hover', 'Link hover color', array( $ah => 'color: {{VALUE}};' ) );
		$this->add_responsive_control( 'links_align', array(
			'label'     => 'Links alignment',
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'flex-start' => array( 'title' => 'Left', 'icon' => 'eicon-text-align-left' ),
				'center'     => array( 'title' => 'Center', 'icon' => 'eicon-text-align-center' ),
				'flex-end'   => array( 'title' => 'Right', 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}} .gruppa-login .gruppa-login-links, {{WRAPPER}} .gruppa-login .gruppa-login-status' => 'justify-content: {{VALUE}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'texts_typography', 'label' => 'Texts typography', 'selector' => $t, 'separator' => 'before' ) );
		$this->color( 'texts_color', 'Text color', array( $t => 'color: {{VALUE}};' ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$o = array();
		foreach ( Gruppa_Login::defaults() as $k => $def ) {
			if ( ! isset( $s[ $k ] ) ) {
				continue;
			}
			$v       = $s[ $k ];
			$o[ $k ] = is_array( $v ) ? ( isset( $v['url'] ) ? $v['url'] : '' ) : ( in_array( $k, array( 'remember', 'lost', 'login_after_reset' ), true ) ? ( 'yes' === $v ? 'yes' : 'no' ) : ( '' === (string) $v ? $def : $v ) );
		}
		$editor = class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->editor && \Elementor\Plugin::$instance->editor->is_edit_mode();
		$editor = $editor || ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->preview && \Elementor\Plugin::$instance->preview->is_preview_mode() );
		if ( $editor ) {
			$o['_preview'] = isset( $s['preview'] ) ? $s['preview'] : 'login';
		}
		echo Gruppa_Login::render( $o ); // phpcs:ignore
	}
}
