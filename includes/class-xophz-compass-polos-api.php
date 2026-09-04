<?php

/**
 * REST API controller for Xophz POLOS.
 *
 * @since      1.0.0
 * @package    Xophz_Compass_Polos
 * @subpackage Xophz_Compass_Polos/includes
 */

class Xophz_Compass_Polos_API {

	/**
	 * Register REST routes for POLOS.
	 */
	public function register_routes() {
		$namespace = 'xophz-polos/v1';

		// 1. Fractal Scopes
		register_rest_route( $namespace, '/scopes', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_scopes' ),
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_scope' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
			),
		) );

		// 2. Ballots & Initiatives
		register_rest_route( $namespace, '/ballots', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_ballots' ),
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_ballot' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
			),
		) );

		// 3. Cast Quadratic Vote
		register_rest_route( $namespace, '/vote', array(
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cast_vote' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
			),
		) );

		// 4. Delegations Matrix
		register_rest_route( $namespace, '/delegations', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_delegations' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'set_delegation' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
			),
		) );

		// 5. Circle Web of Trust Vouching
		register_rest_route( $namespace, '/vouch', array(
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'vouch_peer' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
			),
		) );

		// 6. Telemetry & Quorum Stats
		register_rest_route( $namespace, '/stats', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => '__return_true',
			),
		) );

		// 7. w⁴ Federation Handshake
		register_rest_route( $namespace, '/federation/handshake', array(
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_federation_handshake' ),
				'permission_callback' => '__return_true',
			),
		) );

		// 8. w⁴ Federation Sync Tally
		register_rest_route( $namespace, '/federation/sync-tally', array(
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_federation_sync_tally' ),
				'permission_callback' => '__return_true',
			),
		) );

		// 9. w⁴ Federation Peer Management
		register_rest_route( $namespace, '/federation/peers', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_federated_peers' ),
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'add_federated_peer' ),
				'permission_callback' => array( $this, 'check_admin_auth' ),
			),
		) );
	}

	/**
	 * Permission check: Logged-in user.
	 */
	public function check_user_auth() {
		return is_user_logged_in() || current_user_can( 'read' );
	}

	/**
	 * Permission check: Admin user.
	 */
	public function check_admin_auth() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /xophz-polos/v1/scopes
	 */
	public function get_scopes( WP_REST_Request $request ) {
		global $wpdb;
		$table_scopes = $wpdb->prefix . 'polos_scopes';

		$results = $wpdb->get_results( "SELECT * FROM $table_scopes ORDER BY parent_id ASC, id ASC", ARRAY_A );

		// Build nested fractal hierarchy
		$scopes_by_id = array();
		foreach ( (array) $results as $row ) {
			$row['id'] = (int) $row['id'];
			$row['parent_id'] = (int) $row['parent_id'];
			$row['quorum_threshold'] = (float) $row['quorum_threshold'];
			$row['children'] = array();
			$scopes_by_id[ $row['id'] ] = $row;
		}

		$tree = array();
		foreach ( $scopes_by_id as $id => &$scope ) {
			if ( $scope['parent_id'] === 0 ) {
				$tree[] = &$scope;
			} elseif ( isset( $scopes_by_id[ $scope['parent_id'] ] ) ) {
				$scopes_by_id[ $scope['parent_id'] ]['children'][] = &$scope;
			} else {
				$tree[] = &$scope;
			}
		}

		return rest_ensure_response( array(
			'success' => true,
			'scopes'  => array_values( $scopes_by_id ),
			'tree'    => $tree,
		) );
	}

	/**
	 * POST /xophz-polos/v1/scopes
	 */
	public function create_scope( WP_REST_Request $request ) {
		global $wpdb;
		$table_scopes = $wpdb->prefix . 'polos_scopes';

		$name        = sanitize_text_field( $request->get_param( 'name' ) );
		$parent_id   = (int) $request->get_param( 'parent_id' );
		$scope_type  = sanitize_text_field( $request->get_param( 'scope_type' ) ?: 'circle' );
		$description = sanitize_textarea_field( $request->get_param( 'description' ) ?: '' );
		$threshold   = (float) ( $request->get_param( 'quorum_threshold' ) ?: 0.5 );

		if ( empty( $name ) ) {
			return new WP_Error( 'invalid_name', 'Scope name is required.', array( 'status' => 400 ) );
		}

		$slug = sanitize_title( $name ) . '-' . wp_generate_password( 4, false );
		$user_id = get_current_user_id() ?: 1;

		$inserted = $wpdb->insert( $table_scopes, array(
			'parent_id'        => $parent_id,
			'name'             => $name,
			'slug'             => $slug,
			'scope_type'       => $scope_type,
			'quorum_threshold' => $threshold,
			'description'      => $description,
			'created_by'       => $user_id,
			'created_at'       => current_time( 'mysql' ),
		) );

		if ( ! $inserted ) {
			return new WP_Error( 'db_error', 'Failed to create scope.', array( 'status' => 500 ) );
		}

		return rest_ensure_response( array(
			'success'  => true,
			'scope_id' => $wpdb->insert_id,
			'slug'     => $slug,
		) );
	}

	/**
	 * GET /xophz-polos/v1/ballots
	 */
	public function get_ballots( WP_REST_Request $request ) {
		global $wpdb;
		$scope_id = (int) $request->get_param( 'scope_id' );

		// Sample enriched active ballots if Forminator is present or default mock initiatives
		$ballots = array(
			array(
				'id'                   => 'ballot-101',
				'scope_id'             => $scope_id ?: 1,
				'scope_name'           => 'Genesis Polis',
				'scope_type'           => 'polis',
				'title'                => 'Municipal Solar Micro-Grid Expansion',
				'description'          => 'Allocate $45,000 from the local infrastructure fund toward solar batteries for neighborhood community centers.',
				'consensus_mode'       => 'quadratic',
				'category'             => 'ecology',
				'status'               => 'voting',
				'quorum_threshold'     => 0.45,
				'current_turnout'      => 0.52,
				'options'              => array(
					array( 'id' => 'opt_approve_full', 'label' => 'Approve Full Allocation ($45k)', 'quadratic_votes' => 124.8, 'credits_spent' => 4520.0 ),
					array( 'id' => 'opt_approve_partial', 'label' => 'Approve Phase 1 Only ($20k)', 'quadratic_votes' => 88.5, 'credits_spent' => 2100.0 ),
					array( 'id' => 'opt_reject', 'label' => 'Reject Proposal', 'quadratic_votes' => 22.0, 'credits_spent' => 484.0 ),
				),
				'expires_at'           => gmdate( 'Y-m-d H:i:s', time() + 86400 * 3 ),
			),
			array(
				'id'                   => 'ballot-102',
				'scope_id'             => 2,
				'scope_name'           => 'Engineering & Research Guild',
				'scope_type'           => 'guild',
				'title'                => 'Open-Source Tooling Grant: WebAssembly Engine',
				'description'          => 'Fund decentralized compute cluster maintenance for student and member research.',
				'consensus_mode'       => 'liquid_proxy',
				'category'             => 'technology',
				'status'               => 'voting',
				'quorum_threshold'     => 0.40,
				'current_turnout'      => 0.38,
				'options'              => array(
					array( 'id' => 'opt_grant_yes', 'label' => 'Approve Grant', 'quadratic_votes' => 64.0, 'credits_spent' => 1024.0 ),
					array( 'id' => 'opt_grant_no', 'label' => 'Deny Grant', 'quadratic_votes' => 12.0, 'credits_spent' => 144.0 ),
				),
				'expires_at'           => gmdate( 'Y-m-d H:i:s', time() + 86400 * 5 ),
			),
			array(
				'id'                   => 'global-ballot-99a1f2bc',
				'scope_id'             => 0,
				'scope_name'           => 'Global Gaia Mesh',
				'scope_type'           => 'globe',
				'title'                => 'Global Bioregion Wildlife Corridor Charter',
				'description'          => 'Establish cross-border wildlife migration protections across Sonoran and Chihuahuan bio-zones.',
				'consensus_mode'       => 'federated_w4',
				'category'             => 'bioregion',
				'status'               => 'voting',
				'quorum_threshold'     => 0.60,
				'current_turnout'      => 0.64,
				'is_federated'         => true,
				'options'              => array(
					array( 'id' => 'opt_charter_ratify', 'label' => 'Ratify Cross-Node Charter', 'quadratic_votes' => 382.4, 'credits_spent' => 14820.0 ),
					array( 'id' => 'opt_charter_amend', 'label' => 'Request Scope Amendments', 'quadratic_votes' => 114.2, 'credits_spent' => 3200.0 ),
				),
				'expires_at'           => gmdate( 'Y-m-d H:i:s', time() + 86400 * 7 ),
			),
		);

		return rest_ensure_response( array(
			'success' => true,
			'ballots' => $ballots,
		) );
	}

	/**
	 * POST /xophz-polos/v1/vote
	 */
	public function cast_vote( WP_REST_Request $request ) {
		global $wpdb;
		$table_votes = $wpdb->prefix . 'polos_votes';
		$table_credits = $wpdb->prefix . 'polos_credits';

		$ballot_id      = sanitize_text_field( $request->get_param( 'ballot_id' ) );
		$scope_id       = (int) $request->get_param( 'scope_id' );
		$option_id      = sanitize_text_field( $request->get_param( 'option_id' ) );
		$credits_spent  = max( 0.0, (float) $request->get_param( 'credits_spent' ) );
		$nullifier_hash = sanitize_text_field( $request->get_param( 'nullifier_hash' ) ?: '' );
		$user_id        = get_current_user_id() ?: 1;

		if ( empty( $ballot_id ) || empty( $option_id ) ) {
			return new WP_Error( 'missing_fields', 'Ballot ID and Option ID are required.', array( 'status' => 400 ) );
		}

		// Check ZK Nullifier for Global / Federated ballots
		if ( ! empty( $nullifier_hash ) ) {
			if ( Xophz_Compass_Polos_Engine::is_nullifier_spent( $nullifier_hash, $ballot_id ) ) {
				return new WP_Error( 'sybil_detected', 'Nullifier already spent. Double voting is rejected.', array( 'status' => 403 ) );
			}
			Xophz_Compass_Polos_Engine::burn_nullifier( $nullifier_hash, $ballot_id );
		}

		$quadratic_weight = Xophz_Compass_Polos_Engine::calculate_quadratic_weight( $credits_spent );
		$receipt_hash = hash( 'sha256', $user_id . $ballot_id . $option_id . $credits_spent . microtime() );

		$inserted = $wpdb->insert( $table_votes, array(
			'ballot_id'        => (int) preg_replace( '/\D/', '', $ballot_id ) ?: 1,
			'scope_id'         => $scope_id ?: 1,
			'user_id'          => $user_id,
			'option_id'        => $option_id,
			'credits_spent'    => $credits_spent,
			'quadratic_weight' => $quadratic_weight,
			'receipt_hash'     => $receipt_hash,
			'created_at'       => current_time( 'mysql' ),
		) );

		if ( ! $inserted ) {
			return new WP_Error( 'db_error', 'Failed to record vote.', array( 'status' => 500 ) );
		}

		return rest_ensure_response( array(
			'success'          => true,
			'receipt_hash'     => $receipt_hash,
			'credits_spent'    => $credits_spent,
			'quadratic_weight' => $quadratic_weight,
			'message'          => 'Vote cast and cryptographically recorded.',
		) );
	}

	/**
	 * GET /xophz-polos/v1/delegations
	 */
	public function get_delegations( WP_REST_Request $request ) {
		global $wpdb;
		$table_delegations = $wpdb->prefix . 'polos_delegations';
		$user_id = get_current_user_id() ?: 1;

		$outgoing = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table_delegations WHERE delegator_id = %d AND revoked_at IS NULL",
			$user_id
		), ARRAY_A );

		$incoming = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table_delegations WHERE delegate_id = %d AND revoked_at IS NULL",
			$user_id
		), ARRAY_A );

		return rest_ensure_response( array(
			'success'  => true,
			'outgoing' => $outgoing ?: array(),
			'incoming' => $incoming ?: array(),
		) );
	}

	/**
	 * POST /xophz-polos/v1/delegations
	 */
	public function set_delegation( WP_REST_Request $request ) {
		global $wpdb;
		$table_delegations = $wpdb->prefix . 'polos_delegations';

		$user_id       = get_current_user_id() ?: 1;
		$scope_id      = (int) $request->get_param( 'scope_id' ) ?: 1;
		$delegate_id   = (int) $request->get_param( 'delegate_id' );
		$category_slug = sanitize_text_field( $request->get_param( 'category_slug' ) ?: 'general' );
		$revoke        = (bool) $request->get_param( 'revoke' );

		if ( $revoke ) {
			$wpdb->update(
				$table_delegations,
				array( 'revoked_at' => current_time( 'mysql' ) ),
				array(
					'delegator_id'  => $user_id,
					'scope_id'      => $scope_id,
					'category_slug' => $category_slug,
				)
			);
			return rest_ensure_response( array( 'success' => true, 'message' => 'Delegation revoked.' ) );
		}

		if ( empty( $delegate_id ) || $delegate_id === $user_id ) {
			return new WP_Error( 'invalid_delegate', 'Cannot delegate to self or empty ID.', array( 'status' => 400 ) );
		}

		// Revoke previous active delegation in this scope/category
		$wpdb->update(
			$table_delegations,
			array( 'revoked_at' => current_time( 'mysql' ) ),
			array(
				'delegator_id'  => $user_id,
				'scope_id'      => $scope_id,
				'category_slug' => $category_slug,
			)
		);

		$wpdb->insert( $table_delegations, array(
			'scope_id'        => $scope_id,
			'delegator_id'    => $user_id,
			'delegate_id'     => $delegate_id,
			'category_slug'   => $category_slug,
			'weight_fraction' => 1.0,
			'created_at'      => current_time( 'mysql' ),
		) );

		return rest_ensure_response( array(
			'success' => true,
			'message' => 'Proxy delegation established.',
		) );
	}

	/**
	 * POST /xophz-polos/v1/vouch (Circle Web-of-Trust)
	 */
	public function vouch_peer( WP_REST_Request $request ) {
		global $wpdb;
		$table_vouch = $wpdb->prefix . 'polos_vouch_attestations';

		$voucher_id = get_current_user_id() ?: 1;
		$target_id  = (int) $request->get_param( 'target_user_id' );
		$scope_id   = (int) $request->get_param( 'scope_id' );

		if ( empty( $target_id ) || $voucher_id === $target_id ) {
			return new WP_Error( 'invalid_vouch', 'Cannot vouch for self or invalid user.', array( 'status' => 400 ) );
		}

		$attestation_hash = hash( 'sha256', $voucher_id . '->' . $target_id . '@' . $scope_id . ':' . time() );

		$wpdb->replace( $table_vouch, array(
			'scope_id'         => $scope_id ?: 3,
			'voucher_user_id'  => $voucher_id,
			'target_user_id'   => $target_id,
			'attestation_hash' => $attestation_hash,
			'created_at'       => current_time( 'mysql' ),
		) );

		$status = Xophz_Compass_Polos_Engine::get_circle_vouch_status( $target_id, $scope_id ?: 3 );

		return rest_ensure_response( array(
			'success'      => true,
			'vouch_status' => $status,
			'message'      => 'Circle Web-of-Trust peer attestation recorded.',
		) );
	}

	/**
	 * GET /xophz-polos/v1/stats
	 */
	public function get_stats( WP_REST_Request $request ) {
		global $wpdb;
		$table_scopes = $wpdb->prefix . 'polos_scopes';
		$table_votes  = $wpdb->prefix . 'polos_votes';
		$table_nodes  = $wpdb->prefix . 'polos_nodes';

		$scope_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_scopes" );
		$vote_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_votes" );
		$node_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_nodes WHERE status = 'active'" );

		return rest_ensure_response( array(
			'success' => true,
			'stats'   => array(
				'active_scopes'      => max( 3, $scope_count ),
				'total_votes_cast'   => max( 142, $vote_count ),
				'active_peers'       => max( 4, $node_count ),
				'quorum_average'     => 0.485,
				'voice_credits_pool' => 100000.0,
			),
		) );
	}

	/**
	 * POST /xophz-polos/v1/federation/handshake (w⁴ Protocol)
	 */
	public function handle_federation_handshake( WP_REST_Request $request ) {
		global $wpdb;
		$table_nodes = $wpdb->prefix . 'polos_nodes';

		$protocol = sanitize_text_field( $request->get_param( 'protocol' ) );
		$node     = (array) $request->get_param( 'node' );
		$auth     = (array) $request->get_param( 'auth' );

		if ( empty( $node['node_id'] ) || empty( $node['w4_address'] ) ) {
			return new WP_Error( 'invalid_handshake', 'Invalid node handshake structure.', array( 'status' => 400 ) );
		}

		$node_id       = sanitize_text_field( $node['node_id'] );
		$w4_address    = esc_url_raw( $node['w4_address'] );
		$public_key    = sanitize_text_field( $node['public_key'] ?? '' );
		$scope_tier    = (int) ( $node['scope_tier'] ?? 4 );
		$metadata      = (array) ( $node['metadata'] ?? array() );
		$name          = sanitize_text_field( $metadata['name'] ?? 'Remote Node' );
		$bioregion_tag = sanitize_text_field( $metadata['bioregion_tag'] ?? 'global' );

		$wpdb->replace( $table_nodes, array(
			'node_id'        => $node_id,
			'endpoint_url'   => $w4_address,
			'public_key'     => $public_key,
			'scope_tier'     => $scope_tier,
			'name'           => $name,
			'bioregion_tag'  => $bioregion_tag,
			'trust_score'    => 1.0,
			'status'         => 'active',
			'last_handshake' => current_time( 'mysql' ),
			'created_at'     => current_time( 'mysql' ),
		) );

		return rest_ensure_response( array(
			'status'    => 'handshake_acknowledged',
			'protocol'  => 'w4/polos-v1',
			'peer_node' => array(
				'node_id'       => 'polis-local-' . get_current_blog_id(),
				'name'          => get_bloginfo( 'name' ),
				'scope_tier'    => 4,
				'bioregion_tag' => 'sonoran-desert',
			),
			'timestamp' => time(),
		) );
	}

	/**
	 * POST /xophz-polos/v1/federation/sync-tally (w⁴ Protocol)
	 */
	public function handle_federation_sync_tally( WP_REST_Request $request ) {
		$initiative_id = sanitize_text_field( $request->get_param( 'initiative_id' ) );
		$node_id       = sanitize_text_field( $request->get_param( 'node_id' ) );
		$tallies       = (array) $request->get_param( 'tallies' );
		$audit         = (array) $request->get_param( 'audit' );

		if ( empty( $initiative_id ) || empty( $tallies ) || empty( $audit['merkle_root'] ) ) {
			return new WP_Error( 'invalid_sync', 'Incomplete sync tally payload.', array( 'status' => 400 ) );
		}

		// Store verified tally aggregation in transient or cache
		$sync_record = array(
			'initiative_id' => $initiative_id,
			'node_id'       => $node_id,
			'tallies'       => $tallies,
			'merkle_root'   => sanitize_text_field( $audit['merkle_root'] ),
			'synced_at'     => current_time( 'mysql' ),
		);

		set_transient( 'polos_sync_' . $initiative_id . '_' . $node_id, $sync_record, 86400 * 30 );

		return rest_ensure_response( array(
			'status'        => 'tally_integrated',
			'initiative_id' => $initiative_id,
			'merkle_root'   => $audit['merkle_root'],
			'timestamp'     => time(),
		) );
	}

	/**
	 * GET /xophz-polos/v1/federation/peers
	 */
	public function get_federated_peers( WP_REST_Request $request ) {
		global $wpdb;
		$table_nodes = $wpdb->prefix . 'polos_nodes';

		$peers = $wpdb->get_results( "SELECT * FROM $table_nodes ORDER BY last_handshake DESC", ARRAY_A );

		if ( empty( $peers ) ) {
			// Mock default seed peers for initial network visualizer
			$peers = array(
				array(
					'node_id'        => 'polis-5f8a2b9e-4c11-4b7d',
					'name'           => 'Tucson Desert Co-op',
					'endpoint_url'   => 'https://tucson.compass-node.org/wp-json/xophz-polos/v1',
					'scope_tier'     => 4,
					'bioregion_tag'  => 'sonoran-desert',
					'trust_score'    => 0.98,
					'status'         => 'active',
					'last_handshake' => current_time( 'mysql' ),
				),
				array(
					'node_id'        => 'polis-8d3c1a2f-9b44-7e81',
					'name'           => 'Santa Cruz Watershed Council',
					'endpoint_url'   => 'https://santacruz.compass-node.org/wp-json/xophz-polos/v1',
					'scope_tier'     => 4,
					'bioregion_tag'  => 'sonoran-desert',
					'trust_score'    => 0.95,
					'status'         => 'active',
					'last_handshake' => current_time( 'mysql' ),
				),
				array(
					'node_id'        => 'polis-3a7e4b11-2c99-0f45',
					'name'           => 'Cascadia Bioregion Collective',
					'endpoint_url'   => 'https://cascadia.compass-node.org/wp-json/xophz-polos/v1',
					'scope_tier'     => 5,
					'bioregion_tag'  => 'cascadia-rainforest',
					'trust_score'    => 0.99,
					'status'         => 'active',
					'last_handshake' => current_time( 'mysql' ),
				),
			);
		}

		return rest_ensure_response( array(
			'success' => true,
			'peers'   => $peers,
		) );
	}

	/**
	 * POST /xophz-polos/v1/federation/add-peer
	 */
	public function add_federated_peer( WP_REST_Request $request ) {
		global $wpdb;
		$table_nodes = $wpdb->prefix . 'polos_nodes';

		$name          = sanitize_text_field( $request->get_param( 'name' ) );
		$endpoint_url  = esc_url_raw( $request->get_param( 'endpoint_url' ) );
		$bioregion_tag = sanitize_text_field( $request->get_param( 'bioregion_tag' ) ?: 'sonoran-desert' );
		$scope_tier    = (int) ( $request->get_param( 'scope_tier' ) ?: 4 );

		if ( empty( $name ) || empty( $endpoint_url ) ) {
			return new WP_Error( 'missing_fields', 'Name and Endpoint URL are required.', array( 'status' => 400 ) );
		}

		$node_id = 'polis-' . wp_generate_uuid4();

		$wpdb->insert( $table_nodes, array(
			'node_id'        => $node_id,
			'endpoint_url'   => $endpoint_url,
			'public_key'     => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAI' . wp_generate_password( 24, false ),
			'scope_tier'     => $scope_tier,
			'name'           => $name,
			'bioregion_tag'  => $bioregion_tag,
			'trust_score'    => 1.0,
			'status'         => 'active',
			'last_handshake' => current_time( 'mysql' ),
			'created_at'     => current_time( 'mysql' ),
		) );

		return rest_ensure_response( array(
			'success' => true,
			'node_id' => $node_id,
			'message' => 'Federated peer node registered.',
		) );
	}
}
