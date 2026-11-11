<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

class Gruppa_Tag_Field extends Tag {
	use Gruppa_Tag_Common;

	public function get_name() {
		return 'gruppa-field';
	}

	public function get_title() {
		return 'Gruppa Field';
	}

	public function get_categories() {
		return array( Module::TEXT_CATEGORY, Module::NUMBER_CATEGORY, Module::POST_META_CATEGORY );
	}

	protected function field_types() {
		return array();
	}

	protected function register_controls() {
		$this->register_common_controls();

		$this->add_control( 'separator', array(
			'label'   => 'Separator',
			'type'    => Controls_Manager::TEXT,
			'default' => ', ',
		) );
		$this->add_control( 'date_format', array(
			'label'       => 'Date format',
			'type'        => Controls_Manager::TEXT,
			'placeholder' => get_option( 'date_format' ),
		) );
		$this->add_control( 'decimals', array(
			'label'       => 'Decimals',
			'type'        => Controls_Manager::NUMBER,
			'min'         => 0,
			'max'         => 6,
			'placeholder' => 'as stored',
			'separator'   => 'before',
		) );
		$this->add_control( 'dec_point', array(
			'label'       => 'Decimal separator',
			'type'        => Controls_Manager::TEXT,
			'placeholder' => '.',
		) );
		$this->add_control( 'thousands_sep', array(
			'label'       => 'Thousands separator',
			'type'        => Controls_Manager::TEXT,
			'placeholder' => 'none',
		) );
		$this->add_control( 'true_text', array(
			'separator' => 'before',
			'label'   => 'True text',
			'type'    => Controls_Manager::TEXT,
			'default' => 'Yes',
		) );
		$this->add_control( 'false_text', array(
			'label'   => 'False text',
			'type'    => Controls_Manager::TEXT,
			'default' => 'No',
		) );
	}

	public function render() {
		$r = $this->resolve();
		if ( ! $r ) {
			return;
		}
		echo Gruppa_Render::text( $r[0], $r[1], $r[2], array( // phpcs:ignore -- escaped in Gruppa_Render::text().
			'separator'   => (string) $this->get_settings( 'separator' ),
			'date_format' => (string) $this->get_settings( 'date_format' ),
			'true_text'   => (string) $this->get_settings( 'true_text' ),
			'false_text'  => (string) $this->get_settings( 'false_text' ),
			'decimals'      => (string) $this->get_settings( 'decimals' ),
			'dec_point'     => (string) $this->get_settings( 'dec_point' ),
			'thousands_sep' => (string) $this->get_settings( 'thousands_sep' ),
		) );
	}
}
