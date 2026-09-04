<?php

/**
 * Fired during plugin activation.
 *
 * @since      1.0.0
 * @package    Xophz_Compass_Polos
 * @subpackage Xophz_Compass_Polos/includes
 */

class Xophz_Compass_Polos_Activator {

	/**
	 * Create required database tables for fractal scopes, delegations,
	 * voice credits, ZK nullifiers, and federated peer nodes.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// 1. Scopes table (Fractal hierarchy: Circle -> Guild -> Polis)
		$table_scopes = $wpdb->prefix . 'polos_scopes';
		$sql_scopes = "CREATE TABLE $table_scopes (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			parent_id bigint(20) unsigned DEFAULT 0,
			name varchar(191) NOT NULL,
			slug varchar(191) NOT NULL,
			scope_type varchar(50) NOT NULL DEFAULT 'circle',
			quorum_threshold float NOT NULL DEFAULT 0.5,
			description text DEFAULT NULL,
			created_by bigint(20) unsigned NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY parent_id (parent_id),
			KEY scope_type (scope_type),
			KEY slug (slug)
		) $charset_collate;";
		dbDelta( $sql_scopes );

		// 2. Web of Trust: Circle Vouch Attestations
		$table_vouch = $wpdb->prefix . 'polos_vouch_attestations';
		$sql_vouch = "CREATE TABLE $table_vouch (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scope_id bigint(20) unsigned NOT NULL,
			voucher_user_id bigint(20) unsigned NOT NULL,
			target_user_id bigint(20) unsigned NOT NULL,
			attestation_hash varchar(191) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_vouch (scope_id, voucher_user_id, target_user_id),
			KEY target_user_id (target_user_id)
		) $charset_collate;";
		dbDelta( $sql_vouch );

		// 3. Liquid Delegations Matrix
		$table_delegations = $wpdb->prefix . 'polos_delegations';
		$sql_delegations = "CREATE TABLE $table_delegations (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scope_id bigint(20) unsigned NOT NULL,
			delegator_id bigint(20) unsigned NOT NULL,
			delegate_id bigint(20) unsigned NOT NULL,
			category_slug varchar(100) NOT NULL DEFAULT 'general',
			weight_fraction float NOT NULL DEFAULT 1.0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			revoked_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY delegator_lookup (delegator_id, category_slug, scope_id),
			KEY delegate_lookup (delegate_id, category_slug)
		) $charset_collate;";
		dbDelta( $sql_delegations );

		// 4. Voice Credit Ledger
		$table_credits = $wpdb->prefix . 'polos_credits';
		$sql_credits = "CREATE TABLE $table_credits (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			scope_id bigint(20) unsigned NOT NULL,
			cycle_id varchar(100) NOT NULL DEFAULT 'default',
			credit_balance float NOT NULL DEFAULT 100.0,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_user_scope_cycle (user_id, scope_id, cycle_id)
		) $charset_collate;";
		dbDelta( $sql_credits );

		// 5. Quadratic Vote Ledger & Receipts
		$table_votes = $wpdb->prefix . 'polos_votes';
		$sql_votes = "CREATE TABLE $table_votes (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ballot_id bigint(20) unsigned NOT NULL,
			scope_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			option_id varchar(100) NOT NULL,
			credits_spent float NOT NULL DEFAULT 0.0,
			quadratic_weight float NOT NULL DEFAULT 0.0,
			receipt_hash varchar(191) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY ballot_option (ballot_id, option_id),
			KEY user_ballot (user_id, ballot_id)
		) $charset_collate;";
		dbDelta( $sql_votes );

		// 6. Spent ZK Nullifiers for Bioregion & Global Ballots
		$table_nullifiers = $wpdb->prefix . 'polos_nullifiers';
		$sql_nullifiers = "CREATE TABLE $table_nullifiers (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			nullifier_hash varchar(191) NOT NULL,
			ballot_id varchar(191) NOT NULL,
			burned_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_nullifier_ballot (nullifier_hash, ballot_id)
		) $charset_collate;";
		dbDelta( $sql_nullifiers );

		// 7. Federated w⁴ Nodes Registry
		$table_nodes = $wpdb->prefix . 'polos_nodes';
		$sql_nodes = "CREATE TABLE $table_nodes (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			node_id varchar(191) NOT NULL,
			endpoint_url varchar(255) NOT NULL,
			public_key text NOT NULL,
			scope_tier int(11) NOT NULL DEFAULT 4,
			name varchar(191) NOT NULL,
			bioregion_tag varchar(100) NOT NULL DEFAULT 'unassigned',
			trust_score float NOT NULL DEFAULT 1.0,
			status varchar(50) NOT NULL DEFAULT 'active',
			last_handshake datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_node_id (node_id),
			KEY bioregion_tag (bioregion_tag)
		) $charset_collate;";
		dbDelta( $sql_nodes );

		// Insert seed data if table is fresh
		self::seed_initial_scopes();
	}

	/**
	 * Populate initial fractal scopes if none exist.
	 */
	private static function seed_initial_scopes() {
		global $wpdb;
		$table_scopes = $wpdb->prefix . 'polos_scopes';

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_scopes" );
		if ( $count > 0 ) {
			return;
		}

		$wpdb->insert( $table_scopes, array(
			'parent_id'        => 0,
			'name'             => 'Genesis Polis',
			'slug'             => 'genesis-polis',
			'scope_type'       => 'polis',
			'quorum_threshold' => 0.35,
			'description'      => 'Root Municipal / Campus governance jurisdiction.',
			'created_by'       => 1,
			'created_at'       => current_time( 'mysql' ),
		) );

		$polis_id = $wpdb->insert_id;

		$wpdb->insert( $table_scopes, array(
			'parent_id'        => $polis_id,
			'name'             => 'Engineering & Research Guild',
			'slug'             => 'engineering-research-guild',
			'scope_type'       => 'guild',
			'quorum_threshold' => 0.40,
			'description'      => 'Departmental co-op and research resource allocation.',
			'created_by'       => 1,
			'created_at'       => current_time( 'mysql' ),
		) );

		$wpdb->insert( $table_scopes, array(
			'parent_id'        => $polis_id,
			'name'             => 'Omega Pod Alpha',
			'slug'             => 'omega-pod-alpha',
			'scope_type'       => 'circle',
			'quorum_threshold' => 0.60,
			'description'      => 'Local intimate pod for high-trust human peer vouching.',
			'created_by'       => 1,
			'created_at'       => current_time( 'mysql' ),
		) );
	}
}
