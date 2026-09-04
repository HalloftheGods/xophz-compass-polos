<?php

/**
 * Calculation and consensus engine for POLOS.
 *
 * @since      1.0.0
 * @package    Xophz_Compass_Polos
 * @subpackage Xophz_Compass_Polos/includes
 */

class Xophz_Compass_Polos_Engine {

	/**
	 * Compute quadratic votes generated from voice credits.
	 * Weight = sqrt(Credits)
	 *
	 * @param float $credits
	 * @return float
	 */
	public static function calculate_quadratic_weight( $credits ) {
		$credits = max( 0.0, (float) $credits );
		return round( sqrt( $credits ), 4 );
	}

	/**
	 * Compute voice credit cost for desired quadratic votes.
	 * Cost = Votes^2
	 *
	 * @param float $votes
	 * @return float
	 */
	public static function calculate_credit_cost( $votes ) {
		$votes = max( 0.0, (float) $votes );
		return round( pow( $votes, 2 ), 4 );
	}

	/**
	 * Compute cryptographic Merkle Root from an array of receipt hashes.
	 *
	 * @param array $receipt_hashes
	 * @return string
	 */
	public static function compute_merkle_root( array $receipt_hashes ) {
		if ( empty( $receipt_hashes ) ) {
			return hash( 'sha256', 'polos_empty_tree' );
		}

		$current_layer = array_map( function( $hash ) {
			return hash( 'sha256', (string) $hash );
		}, $receipt_hashes );

		while ( count( $current_layer ) > 1 ) {
			$next_layer = array();
			$count = count( $current_layer );

			for ( $i = 0; $i < $count; $i += 2 ) {
				$left = $current_layer[ $i ];
				$right = ( $i + 1 < $count ) ? $current_layer[ $i + 1 ] : $left;
				$next_layer[] = hash( 'sha256', $left . $right );
			}

			$current_layer = $next_layer;
		}

		return $current_layer[0];
	}

	/**
	 * Resolve liquid proxy voting power for a user in a given scope and category.
	 * Traverses transitive delegation chains while preventing circular loops.
	 *
	 * @param int    $user_id
	 * @param int    $scope_id
	 * @param string $category_slug
	 * @return array
	 */
	public static function resolve_delegation_chain( $user_id, $scope_id, $category_slug = 'general' ) {
		global $wpdb;
		$table_delegations = $wpdb->prefix . 'polos_delegations';

		$visited = array( (int) $user_id );
		$current_delegate = (int) $user_id;

		while ( true ) {
			$sql = $wpdb->prepare(
				"SELECT delegate_id FROM $table_delegations 
				 WHERE delegator_id = %d AND scope_id = %d AND category_slug = %s AND revoked_at IS NULL 
				 LIMIT 1",
				$current_delegate,
				$scope_id,
				$category_slug
			);

			$next_delegate = (int) $wpdb->get_var( $sql );

			if ( empty( $next_delegate ) || in_array( $next_delegate, $visited, true ) ) {
				break;
			}

			$visited[] = $next_delegate;
			$current_delegate = $next_delegate;
		}

		return array(
			'final_proxy_user_id' => $current_delegate,
			'chain'               => $visited,
			'is_delegated'        => ( $current_delegate !== (int) $user_id ),
		);
	}

	/**
	 * Verify if ZK Nullifier has been spent for a specific ballot.
	 *
	 * @param string $nullifier_hash
	 * @param string $ballot_id
	 * @return bool True if already spent / double-vote attempt.
	 */
	public static function is_nullifier_spent( $nullifier_hash, $ballot_id ) {
		global $wpdb;
		$table_nullifiers = $wpdb->prefix . 'polos_nullifiers';

		$exists = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $table_nullifiers WHERE nullifier_hash = %s AND ballot_id = %s",
			$nullifier_hash,
			$ballot_id
		) );

		return $exists > 0;
	}

	/**
	 * Burn a ZK Nullifier upon successful vote registration.
	 *
	 * @param string $nullifier_hash
	 * @param string $ballot_id
	 * @return bool
	 */
	public static function burn_nullifier( $nullifier_hash, $ballot_id ) {
		global $wpdb;
		$table_nullifiers = $wpdb->prefix . 'polos_nullifiers';

		$result = $wpdb->insert( $table_nullifiers, array(
			'nullifier_hash' => sanitize_text_field( $nullifier_hash ),
			'ballot_id'      => sanitize_text_field( $ballot_id ),
			'burned_at'      => current_time( 'mysql' ),
		) );

		return false !== $result;
	}

	/**
	 * Calculate Web of Trust status for a user within a Circle scope.
	 * Requires at least 3 distinct human peer attestations.
	 *
	 * @param int $user_id
	 * @param int $scope_id
	 * @return array
	 */
	public static function get_circle_vouch_status( $user_id, $scope_id ) {
		global $wpdb;
		$table_vouch = $wpdb->prefix . 'polos_vouch_attestations';

		$vouchers = $wpdb->get_col( $wpdb->prepare(
			"SELECT voucher_user_id FROM $table_vouch WHERE target_user_id = %d AND scope_id = %d",
			$user_id,
			$scope_id
		) );

		$count = count( $vouchers );
		$is_verified = ( $count >= 3 );

		return array(
			'target_user_id' => (int) $user_id,
			'scope_id'       => (int) $scope_id,
			'vouch_count'    => $count,
			'vouchers'       => array_map( 'intval', $vouchers ),
			'is_verified'    => $is_verified,
			'required_count' => 3,
		);
	}
}
