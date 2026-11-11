<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;

class Gruppa_Tag_Gallery extends Data_Tag {
	use Gruppa_Tag_Common;

	public function get_name() {
		return 'gruppa-gallery';
	}

	public function get_title() {
		return 'Gruppa Gallery';
	}

	public function get_categories() {
		return array( Module::GALLERY_CATEGORY );
	}

	protected function field_types() {
		return array( 'media' );
	}

	protected function register_controls() {
		$this->register_common_controls();
	}

	public function get_value( array $options = array() ) {
		$r = $this->resolve();
		if ( ! $r ) {
			return array();
		}
		return array_map( function ( $id ) {
			return array( 'id' => $id );
		}, Gruppa_Render::media_ids( $r[0], $r[1], $r[2] ) );
	}
}
