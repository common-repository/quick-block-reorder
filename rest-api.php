<?php

defined( 'QUICK_BLOCK_REORDER_API_VERSION' ) ?: define( 'QUICK_BLOCK_REORDER_API_VERSION', 1.0 );
defined( 'QUICK_BLOCK_REORDER_REST_NAMESPACE' ) ?: define( 'QUICK_BLOCK_REORDER_REST_NAMESPACE', 'quick-block-reorder' );


class Qbr_Blocks {

	/**
	 * @param $post_id
	 */
	public static function get_blocks( $post_id ) {
		$content = get_post_field( 'post_content', $post_id );
		$blocks  = parse_blocks( $content );

		return $blocks;
	}

	/**
	 * @param $blocks array
	 *
	 * @return array
	 */
	public static function filter_blocks( $blocks ) {
		return array_filter( $blocks, function ( $item ) {

			return self::is_block_valid( $item );

		} );
	}

	public static function format_block_data( $block ) {
		$hash = self::block_to_hash( $block );

		return array(
			'blockName' => $block['blockName'],
			'hash'      => $hash,
			'content'   => wp_strip_all_tags( $block['innerHTML'] )
		);
	}

	/**
	 * @param $blocks array
	 */
	public static function format_blocks( $blocks ) {
		return array_map( array( Qbr_Blocks::class, 'format_block_data' ), $blocks );
	}

	public static function get_map( $post_id ) {
		$map = array();
		$content = get_post_field('post_content', $post_id);
		$blocks = parse_blocks($content);
		foreach ( $blocks as $block ) {
			if ( ! self::is_block_valid($block) ) {
				continue;
			}
			$map[ self::block_to_hash($block) ] = $block;
		}
		return $map;

	}

	/**
	 * @param $item
	 *
	 * @return bool
	 */
	public static function is_block_valid( $item ) {
		if ( ! array_key_exists( 'innerHTML', $item ) ) {
			return false;
		}
		$without_spaces_block_html = preg_replace( '/\s+/', '', $item['innerHTML'] );

		return strlen( $without_spaces_block_html ) > 0;
	}

	/**
	 * @param $block
	 *
	 * @return string
	 */
	public static function block_to_hash( $block ) {
		return md5( $block['innerHTML'] );
	}

}


class Qbr_Rest_Handler {


	public function __construct() {

		add_action( 'rest_api_init', function () {
			$namespace = QUICK_BLOCK_REORDER_REST_NAMESPACE;
			$version   = QUICK_BLOCK_REORDER_API_VERSION;

			register_rest_route( "${namespace}/${version}", "/blocks/(?P<id>\d+)", array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_blocks_handler' ),
				'args'                => array( 'id' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				}
			) );


			register_rest_route( "${namespace}/${version}", "/blocks/(?P<id>\d+)", array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_block_order' ),
				'args'                => array(
					'id'     => array(
						'validate_callback' => function ( $value, $request, $param ) {
							return is_numeric( $value );
						}
					),
					'blocks' => array(
						'validate_callback' => function ( $value, $request, $param ) {
							return is_array( $value );
						}
					)
				),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				}

			) );


		} );
	}

	/**
	 * @param $request WP_REST_Request
	 *
	 * @return array|bool
	 */
	public function get_blocks_handler( $request ) {
		$id = (int) $request->get_param( 'id' );
		if ( ! get_post( $id ) ) {
			return false;
		}
		$blocks = Qbr_Blocks::get_blocks( $id );

		if ( 0 === count( $blocks ) ) {
			return false;
		}

		$filtered_blocks = Qbr_Blocks::filter_blocks( $blocks );

		return array_values( Qbr_Blocks::format_blocks( $filtered_blocks ) );

	}

	/**
	 * @param $request WP_REST_Request
	 */
	public function update_block_order( $request ) {

		$client_blocks = $request->get_param( 'blocks' );

		$id = (int) $request->get_param( 'id' );

		$map = Qbr_Blocks::get_map( $id );


		$reordered_blocks = array();

		foreach ( $client_blocks as $client_block ) {

			// if the hash is not present then the data got changed, throw an error.
			$hash = $client_block['hash'];

			if ( ! array_key_exists( $hash, $map ) ) {
				return new WP_Error( "Post content got changed by other source while reordering, unable to save" );
			}

			// collect the blocks
			$reordered_blocks[] = $map[$hash];

			// remove entry from map.
			//unset($map[$hash]);

		}

		$post_content = serialize_blocks($reordered_blocks);

		 $result = wp_update_post(array(
			'ID' => $id,
			'post_content' => $post_content
		));

		 if ( ! is_wp_error($result) ) {
		 	return array("result" => "Successfully saved");
		 }

		 return $result;
	}


}


new Qbr_Rest_Handler();
