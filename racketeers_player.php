<?php
/**
 **  This file contains all the support functions for players.
 **  Players are users with subscriber role.
 **  Groups are users with author role.
 **
 **/

/** racketeers_is_user_player
 * figure out if the insert is for a known organizer  
 * A user with the role author is an organizer. 
 * A user with the role subscriber can subscribe to a group.
 * and a user with the role subscriber is player.
 * admins can do anything.
 */

/** racketeers_player_hub
 *  This is the main entry for the organizer account.
 *  The group form is displayed, and the organizer can manage
 *  matches (add, update, delete).
 *
 *  KBL TODO - what is the return val for display group form
 *
 **/
function racketeers_player_hub() {

	$current_user_id = get_current_user_id();


	if ( racketeers_is_user_player( $current_user_id ) ) {
		
		racketeers_handle_player_form();
		racketeers_display_player_form();

	}

}


/** racketeers_display_player_form
 *
 *  Foreach group, see if the current user is subscribed.  If so, display the match forms for that group.
 *  Note that the current player can only modify their own participation, so that's matches they are 
 *  currently players for, and matches that have available slots.
 */
function racketeers_display_player_form () {

	$groups = racketeers_get_all_groups();

	foreach ( $groups as $g ) {
		if (racketeers_player_is_a_member( $g->ID ) ) {
			racketeers_display_matches_by_group( $g->ID, $g->display_name);
		}
	}
}

function racketeers_handle_player_form() {

	if ( ! isset( $_POST['player_action'] ) ) {
		return;
	}

	global $debug;
	if ( $debug) {
		echo "[racketeers_handle_player_form] </br>";
		echo "<pre>"; print_r ( $_POST ); echo "</pre>";
	}
	$current_user_id = get_current_user_id();
	$thismatch = racketeers_get_match_by_id( $_POST['racketeers_match_id'] );

	echo "<pre>"; print_r ( $thismatch); echo "</pre>";



	switch ( $_POST['player_action'] ) {
		case "Add me as host":
			$thismatch['racketeers_match_host'] = $current_user_id;
			$thismatch['racketeers_match_host_status'] = "unconfirmed";
			break;
		case "Add me as player 1":
			$thismatch['racketeers_match_player_1'] = $current_user_id;
			$thismatch['racketeers_match_player_1_status'] = "unconfirmed";
			break;
		case "Add me as player 2":
			$thismatch['racketeers_match_player_2'] = $current_user_id;
			$thismatch['racketeers_match_player_2_status'] = "unconfirmed";
			break;		
		case "Add me as player 3":
			$thismatch['racketeers_match_player_3'] = $current_user_id;
			$thismatch['racketeers_match_player_2_status'] = "unconfirmed";
			break;	

		case "I need a sub":
			if ( $current_user_id == $thismatch['racketeers_match_host']) {
				$thismatch['racketeers_match_host_status'] = "needsub";
			} else if ( $current_user_id == $thismatch['racketeers_match_player_1']) {
				$thismatch['racketeers_match_player_1_status'] = "needsub";
			} else if ( $current_user_id == $thismatch['racketeers_match_player_2']) {
				$thismatch['racketeers_match_player_2_status'] = "needsub";
			} else if ( $current_user_id == $thismatch['racketeers_match_player_3']) {
				$thismatch['racketeers_match_player_3_status'] = "needsub";
			}
			break;
		default:
			echo "bad action";
	}
 
	racketeers_update_match ( $thismatch );
}


function racketeers_is_user_player( $current_user_id ){

	global $debug;

	$user_info = get_userdata( $current_user_id );

	if ( ! $user_info ) {
		echo "[racketeers_is_user_player] can't get user data!?!";
		return false;
	}

	// if ( $debug ) {
	// 	echo "[racketeers_is_user_player] roles are: ";
	// 	echo implode(', ', $user_info->roles)."</br>";
	// }

	$user_role = $user_info->roles;

	if 	( in_array ( 'subscriber', $user_role )){
		return true;
	}

	return false;
}

/** racketeers_player_is_a_member
 *  returns true if the current user is a member of the group with id $group_id
 *  returns false otherwise.
 *
 *  get all the members of the group, and see if the current user is one of them.
 */
function racketeers_player_is_a_member( $group_id ){

	$players_reg = get_user_meta( $group_id, 'group_member', false );
	$current_user_id = get_current_user_id();

	return in_array( $current_user_id, $players_reg );
}

/** 
 * Display all the matches in the match table.
 *
 **/
function racketeers_display_matches_by_group( $group_id, $group_name ) {

	global $debug;
	global $wpdb;

	$match_table_name = $wpdb->prefix . constant( "MATCH_TABLE_NAME" );
 
 	$query = "SELECT * FROM $match_table_name WHERE racketeers_match_organizer_ID=$group_id";
	$allmatches = $wpdb->get_results( $query );

	//racketeers_create_match_table_header(); 
	//racketeers_create_match_add_row( $thisgroup );

	if ( $allmatches ) {
		echo "Matches for the $group_name";
		foreach ( $allmatches as $thismatch ) {
			racketeers_create_player_match_table_row( $thismatch );
		}
	} else { 
		echo "<h3>No matches for $group_name</h3>";
	}
}
/**
 ** create match_table_row
 ** this function creates one row of the list of matches.
 ** Dump the match passed with options to change match details.
 ** KBL TODO - we may dump players here
 **/
function racketeers_create_player_match_table_row( $thismatch ) {
	?>
		<div class="nttablerow">
			<form method="post" class="selectMatchForm">
				<div class="matchtablecellnarrow">
					<?php echo $thismatch->racketeers_match_date; ?>
					<input type="hidden" name="racketeers_match_id" 
							value="<?php echo $thismatch->racketeers_match_id; ?>" />
				</div>		
				<?php racketeers_show_match_players_choice ( $thismatch ); ?>
			</form>
		</div>
	<?php
}

/** racketeers_show_match_players_choice
 *
 *  show an input control for the current user to choose to sign up for a match.
 *  Note - they should only be able to sign up for one slot.
 *  If they are alrady signed up, they can only be removed by requesting a sub.  
 *  KBL todo - add sub support
 */
function racketeers_show_match_players_choice ( $thismatch )
{
	global $debug;

	// if ( $debug ){
	// 	echo "<pre>"; 
	// 	print_r ( $thismatch );
	// 	echo "</pre>"; 
	// }

	$placed_button = false;


	$current_user_id = get_current_user_id();

	$current_user_is_playing = ( ( $current_user_id == $thismatch->racketeers_match_host ) ||
		 						 ( $current_user_id == $thismatch->racketeers_match_player_1 ) ||
		 						 ( $current_user_id == $thismatch->racketeers_match_player_2) ||
		 						 ( $current_user_id == $thismatch->racketeers_match_player_3) );

	if ( isset ( $thismatch->racketeers_match_host ) ) {
		$user_info = get_userdata( $thismatch->racketeers_match_host );
		echo "host: " . $user_info->data->display_name . " " . $thismatch->racketeers_match_host_status . "</br>";
		racketeers_add_sub_button( $current_user_id, $user_info->ID, $thismatch->racketeers_match_host_status ); 

	} else if  ( ! $current_user_is_playing ) {		
		echo ' <input type="submit" name="player_action" value="Add me as host" /></br>';
		$placed_button = true;
	}

	if ( isset ($thismatch->racketeers_match_player_1) ) {
		$user_info = get_userdata( $thismatch->racketeers_match_player_1 );
		echo "pla1: ". $user_info->data->display_name . " " . $thismatch->racketeers_match_player_1_status . "</br>";
		racketeers_add_sub_button( $current_user_id, $user_info->ID, $thismatch->racketeers_match_player_1_status ); 

	} else if ( ! $placed_button && ! $current_user_is_playing ) {
		echo ' <input type="submit" name="player_action" value="Add me as player 1" /></br>';
		$placed_button = true;

	} else {
		echo 'player 1 not signed up yet</br>';
	}

	if ( isset ($thismatch->racketeers_match_player_2) ) {
		$user_info = get_userdata( $thismatch->racketeers_match_player_2 );
		echo "pla2: ". $user_info->data->display_name . "   " . $thismatch->racketeers_match_player_2_status . "</br>";
		racketeers_add_sub_button( $current_user_id, $user_info->ID, $thismatch->racketeers_match_player_2_status ); 

	} else if ( ! $placed_button && ! $current_user_is_playing ) {
		echo ' <input type="submit" name="player_action" value="Add me as player 2" /></br>';
		$placed_button = true;

	} else {
		echo 'player 2 not signed up yet</br>';
	}

	if ( isset ($thismatch->racketeers_match_player_3) ) {
		$user_info = get_userdata( $thismatch->racketeers_match_player_3 );
		echo "pla3: ". $user_info->data->display_name. "   " . $thismatch->racketeers_match_player_3_status . "</br>";
		racketeers_add_sub_button( $current_user_id, $user_info->ID, $thismatch->racketeers_match_player_3_status ); 

	} else if ( ! $placed_button && ! $current_user_is_playing ) {
		echo ' <input type="submit" name="player_action" value="Add me as player 3" /></br>';
		$placed_button = true;
	} else {
		echo 'player 3 not signed up yet</br>';
	}
}

function racketeers_add_sub_button( $current_user_id, $player_id, $status ){

	if (( $current_user_id == $player_id ) && ( $status != "needsub")) {
		echo '<input type="submit" name="player_action" value="I need a sub" /></br>';
		echo "<input type='hidden' name='sub_for' value='$current_user_id' /></br>";
	}
}