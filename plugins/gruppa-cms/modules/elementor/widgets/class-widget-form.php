<?php
defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/trait-gruppa-form-style.php';

/**
 * Elementor widget: Gruppa Form — renders a Dynamic Form.
 */
class Gruppa_Widget_Form extends \Elementor\Widget_Base {

	use Gruppa_Form_Style;

	public function get_name() {
		return 'gruppa-form';
	}

	public function get_title() {
		return 'Gruppa Form';
	}

	public function get_icon() {
		return class_exists( 'Gruppa_Elementor' ) ? Gruppa_Elementor::icon( 'form' ) : 'eicon-form-horizontal';
	}

	public function get_categories() {
		return array( Gruppa_Elementor::CATEGORY );
	}

	public function get_keywords() {
		return array( 'gruppa', 'form', 'formular' );
	}

	public function get_style_depends() {
		return array( 'gruppa-form' );
	}

	public function get_script_depends() {
		return array( 'gruppa-form' );
	}

	protected function register_controls() {
		$options = array( '' => '— Select form —' );
		foreach ( Gruppa_Forms::all() as $f ) {
			$options[ $f->slug ] = $f->name . ( 'publish' === $f->status ? '' : ' (draft)' );
		}

		$this->start_controls_section( 'content', array( 'label' => 'Form' ) );
		$this->add_control( 'form', array(
			'label'       => 'Form',
			'type'        => \Elementor\Controls_Manager::SELECT,
			'options'     => $options,
			'default'     => '',
			'label_block' => true,
		) );
		$this->end_controls_section();

		$this->style_form();
		$this->style_label();
		$this->style_text();
		$this->style_select();
		$this->style_choice( 'checkbox' );
		$this->style_choice( 'radio' );
		$this->style_switcher();
		$this->style_media();
		$this->style_button();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo Gruppa_Form_Render::render( isset( $s['form'] ) ? $s['form'] : '' ); // phpcs:ignore
	}
}
