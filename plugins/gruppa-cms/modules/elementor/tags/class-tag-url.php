<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;

class Gruppa_Tag_URL extends Data_Tag {
	use Gruppa_Tag_Common;

	public function get_name() {
		return 'gruppa-url';
	}

	public function get_title() {
		return 'Gruppa URL';
	}

	public function get_categories() {
		return array( Module::URL_CATEGORY );
	}

	protected function field_types() {
		return array( 'system', 'text', 'media', 'relation' );
	}

	protected function register_controls() {
		$this->register_common_controls();
	}

	public function get_value( array $options = array() ) {
		$r = $this->resolve();
		return $r ? Gruppa_Render::url( $r[0], $r[1], $r[2] ) : '';
	}
}
