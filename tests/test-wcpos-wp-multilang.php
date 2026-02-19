<?php

class Test_WCPOS_WP_Multilang extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = get_user_by( 'id', $user_id );
		$user->add_cap( 'access_woocommerce_pos' );
		wp_set_current_user( $user_id );

		if ( ! post_type_exists( 'product' ) ) {
			register_post_type(
				'product',
				array(
					'public' => true,
				)
			);
		}

		if ( ! post_type_exists( 'product_variation' ) ) {
			register_post_type(
				'product_variation',
				array(
					'public' => false,
				)
			);
		}

		if ( ! post_type_exists( 'wcpos_store_test' ) ) {
			register_post_type(
				'wcpos_store_test',
				array(
					'public' => false,
				)
			);
		}

		if ( ! function_exists( 'wpm_get_default_language' ) || ! function_exists( 'wpm_get_languages' ) ) {
			add_filter( 'wcpos_wp_multilang_is_supported', '__return_true' );
		}
	}

	public function tearDown(): void {
		remove_all_filters( 'wcpos_wp_multilang_default_language' );
		remove_all_filters( 'wcpos_wp_multilang_is_supported' );
		remove_all_filters( 'wcpos_wp_multilang_minimum_version' );
		remove_all_filters( 'posts_where' );
		remove_all_filters( 'posts_pre_query' );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function test_product_query_adds_lang_and_meta_query_for_wcpos_route(): void {
		add_filter(
			'wcpos_wp_multilang_default_language',
			static function () {
				return 'en';
			}
		);

		$args    = array();
		$request = new WP_REST_Request( 'GET', '/wcpos/v1/products' );

		$filtered = apply_filters( 'woocommerce_rest_product_object_query', $args, $request );

		$this->assertArrayHasKey( 'lang', $filtered );
		$this->assertSame( 'en', $filtered['lang'] );
		$this->assertArrayHasKey( 'meta_query', $filtered );
		$this->assertTrue( $this->meta_query_contains_language_clause( $filtered['meta_query'], 'en' ) );
		$this->assertTrue( $this->meta_query_contains_not_exists_clause( $filtered['meta_query'] ) );
	}

	public function test_product_query_does_not_add_constraints_for_non_wcpos_route(): void {
		add_filter(
			'wcpos_wp_multilang_default_language',
			static function () {
				return 'en';
			}
		);

		$args    = array();
		$request = new WP_REST_Request( 'GET', '/wc/v3/products' );

		$filtered = apply_filters( 'woocommerce_rest_product_object_query', $args, $request );
		$this->assertArrayNotHasKey( 'lang', $filtered );
		$this->assertArrayNotHasKey( 'meta_query', $filtered );
	}

	public function test_variation_query_adds_lang_and_meta_query_for_wcpos_route(): void {
		add_filter(
			'wcpos_wp_multilang_default_language',
			static function () {
				return 'en';
			}
		);

		$args    = array();
		$request = new WP_REST_Request( 'GET', '/wcpos/v1/products/variations' );

		$filtered = apply_filters( 'woocommerce_rest_product_variation_object_query', $args, $request );
		$this->assertArrayHasKey( 'lang', $filtered );
		$this->assertSame( 'en', $filtered['lang'] );
		$this->assertArrayHasKey( 'meta_query', $filtered );
		$this->assertTrue( $this->meta_query_contains_language_clause( $filtered['meta_query'], 'en' ) );
	}

	public function test_fast_sync_products_returns_default_language_only(): void {
		add_filter(
			'wcpos_wp_multilang_default_language',
			static function () {
				return 'en';
			}
		);

		$english_id = $this->create_product( 'English Product', 'en' );
		$french_id  = $this->create_product( 'French Product', 'fr' );

		$request = new WP_REST_Request( 'GET', '/wcpos/v1/products' );
		$request->set_param( 'posts_per_page', -1 );
		$request->set_param( 'fields', array( 'id' ) );

		$response = apply_filters( 'rest_pre_dispatch', null, rest_get_server(), $request );
		$this->assertInstanceOf( 'WP_REST_Response', $response );

		$data = $response->get_data();
		$ids  = wp_list_pluck( $data, 'id' );

		$this->assertContains( $english_id, $ids );
		$this->assertNotContains( $french_id, $ids );
		$this->assertSame( (string) count( $data ), (string) $response->get_headers()['X-WP-Total'] );
	}

	public function test_fast_sync_variations_route_returns_language_only(): void {
		add_filter(
			'wcpos_wp_multilang_default_language',
			static function () {
				return 'en';
			}
		);

		$parent_id = $this->create_product( 'Parent Product', 'en' );
		$en_id     = $this->create_variation( $parent_id, 'EN Variation', 'en' );
		$fr_id     = $this->create_variation( $parent_id, 'FR Variation', 'fr' );

		$request = new WP_REST_Request( 'GET', '/wcpos/v1/products/variations' );
		$request->set_param( 'posts_per_page', -1 );
		$request->set_param( 'fields', array( 'id' ) );

		$response = apply_filters( 'rest_pre_dispatch', null, rest_get_server(), $request );
		$this->assertInstanceOf( 'WP_REST_Response', $response );

		$ids = wp_list_pluck( $response->get_data(), 'id' );
		$this->assertContains( $en_id, $ids );
		$this->assertNotContains( $fr_id, $ids );
	}

	public function test_fast_sync_child_variations_route_respects_parent_and_language(): void {
		add_filter(
			'wcpos_wp_multilang_default_language',
			static function () {
				return 'en';
			}
		);

		$parent_a_id = $this->create_product( 'Parent A', 'en' );
		$parent_b_id = $this->create_product( 'Parent B', 'en' );

			$target_id    = $this->create_variation( $parent_a_id, 'Parent A EN', 'en' );
			$other_lang   = $this->create_variation( $parent_a_id, 'Parent A FR', 'fr' );
			$other_parent = $this->create_variation( $parent_b_id, 'Parent B EN', 'en' );

		$request = new WP_REST_Request( 'GET', '/wcpos/v1/products/' . $parent_a_id . '/variations' );
		$request->set_param( 'posts_per_page', -1 );
		$request->set_param( 'fields', array( 'id' ) );

		$response = apply_filters( 'rest_pre_dispatch', null, rest_get_server(), $request );
		$this->assertInstanceOf( 'WP_REST_Response', $response );

		$ids = wp_list_pluck( $response->get_data(), 'id' );
		$this->assertContains( $target_id, $ids );
		$this->assertNotContains( $other_lang, $ids );
		$this->assertNotContains( $other_parent, $ids );
	}

	public function test_fast_sync_not_intercepted_for_non_fast_sync_fields(): void {
		$request = new WP_REST_Request( 'GET', '/wcpos/v1/products' );
		$request->set_param( 'posts_per_page', -1 );
		$request->set_param( 'fields', array( 'id', 'name' ) );

		$response = apply_filters( 'rest_pre_dispatch', null, rest_get_server(), $request );
		$this->assertNull( $response );
	}

	public function test_fast_sync_with_modified_date_field_returns_date(): void {
		add_filter(
			'wcpos_wp_multilang_default_language',
			static function () {
				return 'en';
			}
		);

		$english_id = $this->create_product( 'English Product Date', 'en' );

		$request = new WP_REST_Request( 'GET', '/wcpos/v1/products' );
		$request->set_param( 'posts_per_page', -1 );
		$request->set_param( 'fields', array( 'id', 'date_modified_gmt' ) );

		$response = apply_filters( 'rest_pre_dispatch', null, rest_get_server(), $request );
		$this->assertInstanceOf( 'WP_REST_Response', $response );

		$data = $response->get_data();
		$this->assertNotEmpty( $data );
		$this->assertSame( $english_id, (int) $data[0]['id'] );
		$this->assertMatchesRegularExpression( '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', (string) $data[0]['date_modified_gmt'] );
	}

	public function test_store_meta_fields_include_language(): void {
		$fields = apply_filters( 'woocommerce_pos_store_meta_fields', array() );
		$this->assertArrayHasKey( 'language', $fields );
		$this->assertSame( '_wcpos_wp_multilang_language', $fields['language'] );
	}

	public function test_store_response_includes_language_meta_for_single_item(): void {
		$store_id = wp_insert_post(
			array(
				'post_type'   => 'wcpos_store_test',
				'post_status' => 'publish',
				'post_title'  => 'Test Store',
			)
		);
		$this->assertGreaterThan( 0, $store_id );
		update_post_meta( $store_id, '_wcpos_wp_multilang_language', 'fr' );

		$request  = new WP_REST_Request( 'GET', '/wcpos/v1/stores/' . $store_id );
		$response = new WP_REST_Response(
			array(
				'id' => $store_id,
			)
		);

		$result = apply_filters( 'rest_post_dispatch', $response, rest_get_server(), $request );
		$data   = $result->get_data();

		$this->assertSame( 'fr', $data['language'] );
	}

	public function test_store_response_collection_includes_language_and_default_fallback(): void {
		add_filter(
			'wcpos_wp_multilang_default_language',
			static function () {
				return 'en';
			}
		);

		$fr_store_id      = wp_insert_post(
			array(
				'post_type'   => 'wcpos_store_test',
				'post_status' => 'publish',
				'post_title'  => 'French Store',
			)
		);
		$default_store_id = wp_insert_post(
			array(
				'post_type'   => 'wcpos_store_test',
				'post_status' => 'publish',
				'post_title'  => 'Default Store',
			)
		);

		update_post_meta( $fr_store_id, '_wcpos_wp_multilang_language', 'fr' );

		$request  = new WP_REST_Request( 'GET', '/wcpos/v1/stores' );
		$response = new WP_REST_Response(
			array(
				array( 'id' => $fr_store_id ),
				array( 'id' => $default_store_id ),
			)
		);

		$result = apply_filters( 'rest_post_dispatch', $response, rest_get_server(), $request );
		$data   = $result->get_data();

		$this->assertSame( 'fr', $data[0]['language'] );
		$this->assertSame( 'en', $data[1]['language'] );
	}

	public function test_wp_multilang_guard_disables_query_and_store_fields(): void {
		add_filter( 'wcpos_wp_multilang_is_supported', '__return_false' );

		$args     = array();
		$request  = new WP_REST_Request( 'GET', '/wcpos/v1/products' );
		$filtered = apply_filters( 'woocommerce_rest_product_object_query', $args, $request );

		$this->assertArrayNotHasKey( 'lang', $filtered );
		$this->assertArrayNotHasKey( 'meta_query', $filtered );

		$fields = apply_filters( 'woocommerce_pos_store_meta_fields', array() );
		$this->assertArrayNotHasKey( 'language', $fields );
	}

	public function test_minimum_version_gate_disables_integration(): void {
		remove_all_filters( 'wcpos_wp_multilang_is_supported' );

		add_filter(
			'wcpos_wp_multilang_minimum_version',
			static function () {
				return '99.0.0';
			}
		);

		$args     = array();
		$request  = new WP_REST_Request( 'GET', '/wcpos/v1/products' );
		$filtered = apply_filters( 'woocommerce_rest_product_object_query', $args, $request );

		$this->assertArrayNotHasKey( 'lang', $filtered );
		$this->assertArrayNotHasKey( 'meta_query', $filtered );
	}

	private function create_product( string $title, string $language = '' ): int {
		$product_id = wp_insert_post(
			array(
				'post_type'   => 'product',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);

		$this->assertGreaterThan( 0, $product_id );

		if ( '' !== $language ) {
			update_post_meta( $product_id, '_languages', array( $language ) );
		}

		return (int) $product_id;
	}

	private function create_variation( int $parent_id, string $title, string $language = '' ): int {
		$variation_id = wp_insert_post(
			array(
				'post_type'   => 'product_variation',
				'post_status' => 'publish',
				'post_parent' => $parent_id,
				'post_title'  => $title,
			)
		);

		$this->assertGreaterThan( 0, $variation_id );

		if ( '' !== $language ) {
			update_post_meta( $variation_id, '_languages', array( $language ) );
		}

		return (int) $variation_id;
	}

	private function meta_query_contains_language_clause( $meta_query, string $language ): bool {
		if ( ! is_array( $meta_query ) ) {
			return false;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Matches WP Multilang serialized meta value format.
		$serialized_language = serialize( $language );

		if (
			isset( $meta_query['key'], $meta_query['compare'], $meta_query['value'] ) &&
			'_languages' === $meta_query['key'] &&
			'LIKE' === $meta_query['compare'] &&
			$serialized_language === $meta_query['value']
		) {
			return true;
		}

		foreach ( $meta_query as $clause ) {
			if ( is_array( $clause ) && $this->meta_query_contains_language_clause( $clause, $language ) ) {
				return true;
			}
		}

		return false;
	}

	private function meta_query_contains_not_exists_clause( $meta_query ): bool {
		if ( ! is_array( $meta_query ) ) {
			return false;
		}

		if (
			isset( $meta_query['key'], $meta_query['compare'] ) &&
			'_languages' === $meta_query['key'] &&
			'NOT EXISTS' === $meta_query['compare']
		) {
			return true;
		}

		foreach ( $meta_query as $clause ) {
			if ( is_array( $clause ) && $this->meta_query_contains_not_exists_clause( $clause ) ) {
				return true;
			}
		}

		return false;
	}
}
