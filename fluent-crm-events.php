<?php

/**
 * Plugin Name: FluentCRM - Events
 * Description: Adds Events (or Activities) support to FluentCRM.
 * Plugin URI: https://github.com/verygoodplugins/fluent-crm-events/
 * Version: 1.0.0
 * Author: Very Good Plugins
 * Author URI: https://verygoodplugins.com/
*/

/**
 * @copyright Copyright (c) 2023. All rights reserved.
 *
 * @license   Released under the GPL license http://www.opensource.org/licenses/gpl-license.php
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * **********************************************************************
 */

// deny direct access.
if ( ! function_exists( 'add_action' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

define( 'FCRM_EVENTS_VERSION', '1.0.0' );

/**
 * Adds an event to the table.
 *
 * @since  1.0.0
 *
 * @param string $subscriber_id The subscriber ID or email address.
 * @param string $event_type    The event type.
 * @param string $event_title   The event title.
 * @param array  $event_value   The event value.
 * @param int    $timestamp     The timestamp in UTC.
 * @return bool   True if write was successful.
 */
function fcrm_events_add_event( $subscriber_id, $event_type, $event_title, $event_value = array(), $timestamp = 0 ) {

	global $wpdb;

	if ( is_email( $subscriber_id ) ) {

		$contact = FluentCrmApi( 'contacts' )->getContact( $subscriber_id );

		if ( $contact ) {
			$subscriber_id = $contact->id;
		} else {
			return false;
		}
	}

	if ( 0 === $timestamp ) {
		$timestamp = time();
	}

	$insert = array(
		'subscriber_id' => absint( $subscriber_id ),
		'type'          => $event_type,
		'title'         => $event_title,
		'value'         => wp_json_encode( $event_value ),
		'created_at'    => gmdate( 'Y-m-d H:i:s', $timestamp ),
	);

	$format = array(
		'%d',
		'%s',
		'%s',
		'%s',
		'%s',
	);

	$result = $wpdb->insert( "{$wpdb->prefix}fc_events", $insert, $format );

	return $result;

}

/**
 * Get the events from the database for a subscriber.
 *
 * @since 1.0.0
 *
 * @param int    $subscriber_id The subscriber ID.
 * @param string $event_type    The event type.
 */
function fcrm_events_get_events( $subscriber_id, $event_type = 'wp_fusion' ) {

	global $wpdb;

	$events = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}fc_events WHERE subscriber_id = %d AND type = %s ORDER BY created_at DESC",
			$subscriber_id,
			$event_type
		)
	);

	return $events;

}


/**
 * Register a new route for the REST API.
 *
 * @since 1.0.0
 */
function fcrm_events_register_rest_route() {

	register_rest_route( 'fluent-crm/v2', '/events', array(
		'methods'             => 'POST',
		'callback'            => 'fcrm_events_rest_callback',
		'permission_callback' => 'is_user_logged_in',
		'args'                => array(
			'subscriber_id'         => array(
				'required'          => false,
				'validate_callback' => function ( $param, $request, $key ) {
					return is_numeric( $param );
				}
			),
			'email' => array(
				'required'          => false,
				'validate_callback' => function ( $param, $request, $key ) {
					return is_email( $param );
				}
			),
			'title' => array(
				'required' => true,
			),
		),
	));
}

add_action( 'rest_api_init', 'fcrm_events_register_rest_route' );


/**
 * Handle the REST request.
 *
 * @since 1.0.0
 *
 * @param WP_Rest_Request $request The request.
 */
function fcrm_events_rest_callback( $request ) {

	$subscriber_id = (int) $request->get_param( 'subscriber_id' );

	// We can use email address as a fallback.
	if ( ! $subscriber_id && $request->get_param( 'email' ) ) {
		$subscriber_id = sanitize_email( $request->get_param( 'email' ) );
	}

	if ( ! FluentCrmApi( 'contacts' )->getContact( $subscriber_id ) ) {
		return new WP_REST_Response( array( 'success' => false, 'message' => 'Unknown or invalid contact ID.' ) );
	}

	$title = sanitize_text_field( $request->get_param( 'title' ) );

	if ( $request->get_param( 'type' ) ) {
		$type = sanitize_text_field( $request->get_param( 'type' ) );
	} else {
		$type = 'wp_fusion';
	}

	$value = array_filter( (array) $request->get_param( 'value' ) );
	$value = array_map( 'sanitize_text_field', $value );

	$result = fcrm_events_add_event( $subscriber_id, $type, $title, $value );

	if ( $result ) {
		return new WP_REST_Response( array( 'success' => true ) );
	} else {
		return new WP_REST_Response( array( 'success' => false ) );
	}

}

/**
 * Add the events into the subscriber sidebar.
 *
 * @since 1.0.0
 *
 * @param array                           $widgets    The sidebar widgets.
 * @param FluentCrm\App\Models\Subscriber $subscriber The subscriber.
 */
function fcrm_events_info_widget( $widgets, $subscriber ) {

	$events = fcrm_events_get_events( $subscriber->id );

	$html = '<ul class="fc_full_listed">';

	foreach ( $events as $event ) {

		$html .= '<li>';
		$html .= '<img src="' . plugins_url( 'assets/event-types/' . $event->type . '.svg', __FILE__ ) . '" style="width: 20px;height: 20px;vertical-align: middle;margin-right: 5px;" />';
		$html .= '<span style="font-weight: bold;">' . $event->title . '</span>';

		$value = json_decode( $event->value );

		if ( ! empty( $value ) ) {

			$html .= '<ul style="margin-top: 8px;font-size: 85%;color: #555;line-height: 1.3;">';

			foreach ( $value as $key => $val ) {

				if ( is_array( $val ) ) {
					$val = implode( ', ', $val );
				}

				$html .= '<li style="border-bottom: 1px solid #f3f1f1;padding-bottom: 4px;"><strong>' . $key . '</strong>: ' . $val . '</li>';

			}

			$html .= '</ul>';

		}

		$html .= '<p style="margin: 5px 0 0;font-size: 12px;color: #5e5d5d;">' . human_time_diff( strtotime( $event->created_at ) ) . ' ago</p>';
		$html .= '</li>';

	}

	$html .= '</ul>';

	$widgets[] = array(
		'title'   => 'Recent Activities',
		'content' => '<div class="max_height_550">' . $html . '</div>',
	);

	return $widgets;

}

add_filter( 'fluent_crm/subscriber_top_widgets', 'fcrm_events_info_widget', 8, 2 );

/**
 * Create or update the table on activation or version change.
 *
 * @since 1.0.0
 */
function fcrm_events_create_update_table() {

	global $wpdb;
	$table_name = $wpdb->prefix . 'fc_events';

	if ( $wpdb->get_var( "show tables like '$table_name'" ) !== $table_name ) {

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		$sql = 'CREATE TABLE ' . $table_name . " (
			event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			subscriber_id bigint(8) NOT NULL,
			type varchar(200) NOT NULL,
			title varchar(200) NOT NULL,
			value longtext NOT NULL,
			created_at timestamp NOT NULL,
			PRIMARY KEY (event_id)
		) $collate;";

		dbDelta( $sql );

	}

	update_option( 'fcrm_events_table_version', FCRM_EVENTS_VERSION, false );

}

/**
 * Runs when FluentCRM is ready.
 *
 * @since 1.0.0
 */
function fcrm_events_maybe_create_update_table( $app ) {

	$version = get_option( 'fcrm_events_table_version' );

	if ( ! $version ) {
		fcrm_events_create_update_table();
	}

}

add_action( 'fluentcrm_loaded', 'fcrm_events_maybe_create_update_table' );