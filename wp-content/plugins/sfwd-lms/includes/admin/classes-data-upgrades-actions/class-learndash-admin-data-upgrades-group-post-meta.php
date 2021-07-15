<?php
/**
 * LearnDash Data Upgrades for Group Post Meta
 *
 * @since 3.4.1
 * @package LearnDash\Data_Upgrades
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ( class_exists( 'Learndash_Admin_Data_Upgrades' ) ) && ( ! class_exists( 'Learndash_Admin_Data_Upgrades_Group_Post_Meta' ) ) ) {
	/**
	 * Class to create the Data Upgrade for Group Post Meta.
	 *
	 * @since 3.4.1
	 */
	class Learndash_Admin_Data_Upgrades_Group_Post_Meta extends Learndash_Admin_Data_Upgrades {

		/**
		 * DB Version. This is used to trigger future processing actions.
		 *
		 * @since 3.4.1
		 */
		private $db_version = '3.4.1';

		/**
		 * Protected constructor for class
		 *
		 * @since 3.4.1
		 */
		protected function __construct() {
			$this->data_slug = 'group-post-meta';
			parent::__construct();
			parent::register_upgrade_action();
		}

		/**
		 * Initialize the Post Meta Processing.
		 *
		 * @since 3.4.1
		 */
		public function process_post_meta( $force = false ) {
			$run_processing = false;

			if ( is_admin() ) {
				$data_settings = $this->get_data_settings( $this->data_slug );

				if ( true === $force ) {
					$run_processing = true;
				} elseif ( ( ! isset( $data_settings['version'] ) ) || ( version_compare( $data_settings['version'], $this->db_version, '<' ) ) ) {
					$run_processing = true;
				} elseif ( ( ! isset( $data_settings['process_status'] ) ) || ( 'complete' !== $data_settings['process_status'] ) ) {
					$run_processing = true;
				}

				if ( true === $run_processing ) {
					$ret = $this->process_post_meta_items();
					if ( $ret ) {
						$data_settings['process_status'] = 'complete';
					} else {
						$data_settings['process_status'] = 'in_progress';
					}
					$this->set_last_run_info( $data_settings );
				}
			}
		}

		/** This filter is documented in includes/admin/class-learndash-admin-data-upgrades.php */
		public function set_last_run_info( $data = array() ) {
			$data = array_merge( $data, array( 'version' => $this->db_version ) );
			parent::set_last_run_info( $data );
		}

		/**
		 * Process Post Meta items.
		 *
		 * @since 3.4.1
		 */
		public function process_post_meta_items() {
			global $wpdb;

			$post_ids_all  = array();
			$post_ids_meta = array();

			$post_ids_all_query_args = array(
				'post_type' => learndash_get_post_type_slug( 'group' ),
				'fields'    => 'ids',
				'nopaging'  => true,
			);

			$post_ids_all_query = new WP_Query( $post_ids_all_query_args );
			if ( property_exists( $post_ids_all_query, 'posts' ) ) {
				$post_ids_all = $post_ids_all_query->posts;
			}

			if ( empty( $post_ids_all ) ) {
				return true;
			}
			$post_ids_all = array_map( 'absint', $post_ids_all );

			$post_ids_meta_query_args = array(
				'post_type'    => learndash_get_post_type_slug( 'group' ),
				'fields'       => 'ids',
				'nopaging'     => true,
				'meta_key'     => '_ld_certificate',
				'meta_compare' => 'EXISTS',
			);

			$post_ids_meta_query = new WP_Query( $post_ids_meta_query_args );
			if ( property_exists( $post_ids_meta_query, 'posts' ) ) {
				$post_ids_meta = $post_ids_meta_query->posts;
			}
			$post_ids_meta = array_map( 'absint', $post_ids_meta );

			$post_ids_diff = array_diff( $post_ids_all, $post_ids_meta );
			if ( ! empty( $post_ids_diff ) ) {
				/**
				 * Filter the number of items to process.
				 *
				 * @since 3.4.1
				 *
				 * @param int    $batch_size Number of items to process per run. Default 500.
				 * @param string $post_type  Post Type slug bring processed.
				 */
				$processing_limit = apply_filters( 'learndash_post_meta_process_batch_size', 500, learndash_get_post_type_slug( 'quiz' ) );
				$processing_limit = absint( $processing_limit );
				if ( $processing_limit > 0 ) {
					$post_ids_diff = array_slice( $post_ids_diff, 0, $processing_limit );
				}

				foreach ( $post_ids_diff as $post_id ) {
					// Convert Certificate to post_meta.
					$certificate = learndash_get_setting( $post_id, 'certificate' );
					$certificate = absint( $certificate );
					update_post_meta( $post_id, '_ld_certificate', $certificate );

					// Convert Price type to post_meta.
					$price_type = learndash_get_setting( $post_id, 'group_price_type' );
					$price_type = esc_attr( $price_type );
					if ( empty( $price_type ) ) {
						if ( defined( 'LEARNDASH_DEFAULT_COURSE_PRICE_TYPE' ) ) {
							$price_type = LEARNDASH_DEFAULT_COURSE_PRICE_TYPE;
						} else {
							$price_type = '';
						}
					}

					update_post_meta( $post_id, '_ld_price_type', $price_type );
				}
			} else {
				return true;
			}

			return false;
		}

		// End of functions.
	}
}

add_action(
	'learndash_data_upgrades_init',
	function() {
		Learndash_Admin_Data_Upgrades_Group_Post_Meta::add_instance();
	}
);
