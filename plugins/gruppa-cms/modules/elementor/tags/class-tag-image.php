<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;

class Gruppa_Tag_Image extends Data_Tag {
	use Gruppa_Tag_Common;

	public function get_name() {
		return 'gruppa-image';
	}

	public function get_title() {
		return 'Gruppa Image';
	}

	public function get_categories() {
		return array( Module::IMAGE_CATEGORY, Module::MEDIA_CATEGORY );
	}

	protected function field_types() {
		return array( 'media' );
	}

	protected function register_controls() {
		$this->register_common_controls();
		$this->add_control( 'fallback', array(
			'label' => 'Fallback',
			'type'  => Controls_Manager::MEDIA,
		) );
	}

	public function get_value( array $options = array() ) {
		$r   = $this->resolve();
		$ids = $r ? Gruppa_Render::media_ids( $r[0], $r[1], $r[2] ) : array();
		if ( $ids ) {
			return array(
				'id'  => $ids[0],
				'url' => (string) wp_get_attachment_url( $ids[0] ),
			);
		}
		$fallback = $this->get_settings( 'fallback' );
		return ! empty( $fallback['id'] ) ? $fallback : array( 'id' => null, 'url' => '' );
	}
}
