<?php
/*
Plugin Name: Export User Data - Add BuddyPress Fields
Plugin URI: 
Description: Add BuddyPress fields to Q Studio's Export User Data plugin.
Version: 1.0.0
Author: dcavins
License: GPL2
Text Domain: export-user-data-include-bp
*/

namespace eudAddBp;

/**
 * Add filters only when BuddyPress is active.
 *
 * @since 1.0.0
 */
function set_filters() {

	if ( bp_is_active( 'xprofile' ) ) {
		// Add a BuddyPress Extended Profile fields select to the export selection form.
		add_filter( 'q/eud/api/admin/fields',  __NAMESPACE__ . '\\add_xprofile_admin_fields' );

		// Let the main plugin know that we'll be appending BP data.
		add_filter( 'q/eud/export/fields', __NAMESPACE__ . '\\export_declare_bp_xprofile_fields' );

		 // Find the value for BuddyPress extended profile fields.
		add_filter( 'q/eud/export/field_value_before_formatting', __NAMESPACE__ . '\\provide_xprofile_value', 10, 3 );
	}

	// If Member Types have been registered, offer those as well.
	$member_types = bp_get_member_types();
	if ( ! empty( $member_types ) ) {
		// Add a BuddyPress Extended Profile fields select to the export selection form.
		add_filter( 'q/eud/api/admin/fields',  __NAMESPACE__ . '\\add_member_type_admin_field' );

		// Let the main plugin know that we'll be appending BP data.
		add_filter( 'q/eud/export/fields', __NAMESPACE__ . '\\export_declare_bp_member_type_field' );

		 // Find the value for BuddyPress extended profile fields.
		add_filter( 'q/eud/export/field_value_before_formatting', __NAMESPACE__ . '\\provide_member_type_value', 10, 3 );
	}
}
add_action( 'bp_init',  __NAMESPACE__ . '\\set_filters' );

/* Extended Profile Fields *********************************************/

/**
 * Add a BuddyPress Extended Profile fields select to the export selection form.
 *
 * @since 1.0.0
 *
 * @param array $form_fields   Array of field parameters.
 *
 * @return array $form_fields   Array of field parameters.
 */
function add_xprofile_admin_fields( $form_fields ) {
	$groups = bp_xprofile_get_groups( array( 'fetch_fields' => true ) );
	$fields = array();
	foreach ( $groups as $group ) {
		$fields = array_merge( $fields, $group->fields );
	}
	$options_obj = array();
	// Use the field name and add the group and field ID
	// so we don't have to find look them up again later from the name.
	foreach ( $fields as $f ) {
		$opt = array(
			'id' => 'bp_' . $f->group_id . '_' . $f->id,
			'title' => $f->name,
		);
		// Export Users expects objects
		$options_obj[] = (object) $opt;
	}

	$form_fields[] = array(
		'title' => 'BuddyPress Profile Fields', // string ## used for left-hand column
        'label' => '_bp_fields[]', // lowercase string ## used for name and id of select
        'type' => 'select', // Only supported option at the moment
        'options' => $options_obj,
        'options_ID' => 'id', // which index to use in the options array
        'options_title' => 'title', // which index to use in the options array
        'label_select' => '',
        'multiselect' => true,
        'toggleable' => false,
	);
	return $form_fields;
}

/**
 * Let the main plugin know that we'll be appending BP data.
 *
 * @since 1.0.0
 *
 * @param array $fields   Array of field IDs.
 *
 * @return array $fields   Array of field IDs.
 */
function export_declare_bp_xprofile_fields( $fields ) {
	$bp_fields = get_requested_profile_field_names();
	return array_merge( $fields, $bp_fields );
}

/**
 * Using the POSTed field IDs, get the profile field name.
 *
 * @since 1.0.0
 *
 * @return Fields array.
 */
function get_requested_profile_field_names() {
	// Use post data to calculate requested field names.
	$retval = array();
	if ( isset( $_POST['_bp_fields'] ) && is_array( $_POST['_bp_fields'] ) ) {
		foreach ( $_POST['_bp_fields'] as $id ) {
			$retval[] = get_field_name_from_id( $id, true );
		}
	}
	return $retval;
}

/**
 * Given a BP extended profile field ID, return a field name.
 *
 * @since 1.0.0
 *
 * @param string $id         The "id" of the field, as used in the export plugin.
 * @param bool   $include_id Whether to append the field group id and field id
 *                           to the end of the returned string.
 *
 * @return string The calculated field name.
 */
function get_field_name_from_id( $id, $include_id = false ) {
	// $id is of form xprofile_group_id . _ . xprofile_field_id
	$field_id = substr( $id, strrpos( $id, '_') + 1 );
	$field = xprofile_get_field( $field_id, null, false );

	if ( ! empty( $field->name ) ) {
		$retval = $field->name;
		if ( $include_id ) {
			$retval .= " | " . $id;
		}
	} else {
		$retval = $id;
	}
	return $retval;
}

/**
 * Find the value for BuddyPress extended profile fields.
 *
 * @since 1.0.0
 *
 * @param string  $value The current value.
 * @param bool    $field The field name/id to fetch the value for.
 * @param WP_User $user  The user to fetch the value for.
 *
 * @return string The calculated value.
 */
function provide_xprofile_value( $value, $field, $user ) {
	$bp_names = get_requested_profile_field_names();
	if ( in_array( $field, $bp_names ) ) {
		// get field id
		$field_id = substr( $field, strrpos( $field, '_' ) + 1 );
		$field_obj = xprofile_get_field( $field_id, $user->ID );
		if ( isset( $field_obj->data->value ) ) {
			$value = $field_obj->data->value;
		}
	}
	return $value;
}

/* Member Types ********************************************************/

/**
 * Add an option to include BP Member Types in the output.
 *
 * @since 1.0.0
 *
 * @param array $form_fields   Array of field parameters.
 *
 * @return array $form_fields   Array of field parameters.
 */
function add_member_type_admin_field( $form_fields ) {
	$options = array(
		(object) array(
			'id' => 'yes',
			'title' => 'Yes',
		),
		(object) array(
			'id' => 'no',
			'title' => 'No',
		),
	);

	$form_fields[] = array(
		'title' => 'Include BuddyPress Member Types in Output', // string ## used for left-hand column
        'label' => '_bp_member_type', // lowercase string ## used for name and id of select
        'type' => 'select', // Only supported option at the moment
        'options' => $options,
        'options_ID' => 'id', // which index to use in the options array
        'options_title' => 'title', // which index to use in the options array
        'label_select' => '',
        'multiselect' => false,
        'toggleable' => false,
	);
	return $form_fields;
}

/**
 * Let the main plugin know that we'll be appending BP data.
 *
 * @since 1.0.0
 *
 * @param array $fields   Array of field IDs.
 *
 * @return array $fields   Array of field IDs.
 */
function export_declare_bp_member_type_field( $fields ) {
	if ( isset( $_POST['_bp_member_type'] ) && 'yes' === $_POST['_bp_member_type'] ) {
		$fields[] = 'BP Member Type';
	}

	return $fields;
}

/**
 * Find the value for BuddyPress member type.
 *
 * @since 1.0.0
 *
 * @param string  $value The current value.
 * @param bool    $field The field name/id to fetch the value for.
 * @param WP_User $user  The user to fetch the value for.
 *
 * @return string The calculated value.
 */
function provide_member_type_value( $value, $field, $user ) {
	if ( 'BP Member Type' === $field ) {
		$current_type = (array) bp_get_member_type( $user->ID, false );
		$value = implode( ', ' , $current_type );
	}
	return $value;
}
