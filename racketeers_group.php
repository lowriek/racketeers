<?php
/**
 **  This file contains all the support functions for group players.
 **  Matches can only be created by hosts (aka match organizers).
 **  The match table is created with the SQL below.
 **  Players are users.
 **
 **/


/**************************/

/** racketeers_update_group_info()
 *
 * All the group info is save in the authors meta data.  Update is the same as add.
 **/
function racketeers_update_group_info( ) {
 
 	$current_user_id = get_current_user_id();

	if ( ! racketeers_is_user_organizer( $current_user_id ) ) {
		if ( ! $debug ){
			echo "[racketeers_update_group_info] user not organizer";
			return false;
		}
	}
 	
	$day = 	$_POST[ 'racketeers_day' ];
	$timeslot_number =  $_POST[ 'racketeers_time' ] ;
	$match_duration  = 	$_POST[ 'racketeers_match_duration' ] ;


 	/* do the updates */
	update_user_meta( $current_user_id, "racketeers_day",    			$day  );
	update_user_meta( $current_user_id, "racketeers_time",      		$timeslot_number );
	update_user_meta( $current_user_id, "racketeers_match_duration", 	$match_duration  );

	// change last match timestamp to now.  Then when we add a match, it will be the next day after now.
	update_user_meta( $current_user_id, "racketeers_last_match_timestamp", 	time()  );
	// if ( get_user_meta($current_user_id,  'racketeers_last_match_timestamp', true ) != $next_match_unix_timestamp )
	// 	wp_die('An error occurred');

	return true;

}

/** racketeers_display_group_form
 * This form allows meta data for the group to be updated.
 * KBL todo - maybe matches should not be displayed here.
 *
 **/
function racketeers_display_group_form( ){

	$current_user_id = get_current_user_id();

	global $debug;
	$debug = false;
	if ( $debug ) {
		echo "[racketeers_display_group_form] user_id is $current_user_id</br>";
	}

	if ( ! racketeers_is_user_organizer( $current_user_id ) ) {
		echo "[racketeers_display_group_form] Sorry, you are not an organizer!</br>";
		return;
	}

	/** get group data from user and user meta **/
	$user_info  = get_userdata( $current_user_id );
    $username   = $user_info->user_login;
    $first_name = $user_info->first_name;
    $last_name  = $user_info->last_name;

	$day 		= get_user_meta( $current_user_id, "racketeers_day", true );
	$time 		= get_user_meta( $current_user_id, "racketeers_time", true );
	$duration 	= get_user_meta( $current_user_id, "racketeers_match_duration", true );

	/* display the group data, and allow modification, and add match button **/
	?>
	<fieldset>
		<legend>Group Information for <?php echo "$first_name $last_name"; ?></legend>
		<form method="post">	
			<?php 
				racketeers_create_day_menu( "racketeers_day" , $day ); 
		 		racketeers_create_timeslot_menu( "racketeers_time", $time ); 
				racketeers_create_match_duration_menu( "racketeers_match_duration", $duration );
			?>
			<input type="submit" name="racketeers_action" value="Update Group" />
			<input type="submit" name="racketeers_action" value="Delete Group Information" />
			<input type="submit" name="racketeers_action" value="Add Match" />
			</br></br>
			<input type="submit" name="racketeers_action" value="Manage Group Players" />

		</form>
		</form>
	</fieldset>
	<?php
	$debug = true;
}


/** racketeers_delete_group_info
 * delete group info from the current user (if the current user is an organizer)
 * delete matches because matches can't exists without group info.
 *
 * returns true if successful, false on failure.
 */
function racketeers_delete_group_info ( ) {

	$current_user_id = get_current_user_id();

	global $debug;
	if ( $debug ) {
		echo "[racketeers_delete_group] user_id is $current_user_id </br> ";
	}

	if ( ! racketeers_is_user_organizer( $current_user_id ) ){
		if ( $debug ) {
			echo "[racketeers_delete_group] delete_group_info failed, user is not an organizer </br> ";
		}
		return false;
	}

	/* now we know the user is an organizer, so delete the group meta data **/
	if ( ! delete_user_meta( $current_user_id, "racketeers_name" )  ||
		 ! delete_user_meta( $current_user_id, "racketeers_day" ) ||
		 ! delete_user_meta( $current_user_id, "racketeers_time" ) ||
		 ! delete_user_meta( $current_user_id, "racketeers_match_duration" ) ||
		 ! delete_user_meta( $current_user_id, "racketeers_last_match_timestamp" ) ){
		if ( $debug ) {
			echo "[racketeers_delete_group] delete_group_info failed - can't delete user meta </br> ";
		}
		return false;
	}

	/* delete all matches from the current group - maybe we should do this first? */
	global $wpdb;
	
	$table_name = $wpdb->prefix . constant( "MATCH_TABLE_NAME" );
	$where = array( 'racketeers_match_organizer_ID' => $current_user_id );
	$rows_affected = $wpdb->delete( $table_name, $where,  '%d' );

	return $rows_affected;
}

/** racketeers_is_user_organizer
 * figure out if the insert is for a known organizer  
 * A user with the role author is an organizer. 
 * A user with the role subscriber can subscribe to a group.
 * and a user with the role subscriber is player.
 * admins can do anything.
 */
function racketeers_is_user_organizer( $current_user_id ){

	global $debug;
	if ( $debug ) {
		echo "[racketeers_is_user_organizer] user_id is $current_user_id </br> ";
	}

	$user_info = get_userdata( $current_user_id );

	if ( ! $user_info ) {
		echo "[racketeers_is_user_organizer] can't get user data!?!";
		return false;
	}

	if ( $debug ) {
		echo "[racketeers_is_user_organizer] roles are: ";
		echo implode(', ', $user_info->roles)."</br>";
	}

	$user_role = $user_info->roles;

	if 	( in_array ( 'author'        , 	$user_role )){
		return true;
	}

	return false;
}

/**
 *  racketeers_manage_players_form()
 *  adds and deletes players from group meta data
 */
function racketeers_manage_players_form(  ) {
	?>
	<fieldset>
		<legend>Manage Players</legend>
		<form method="post">	
			<?php 
				$all_players = racketeers_get_all_players();
				$subscribed_players = racketeers_get_subscribed_players();
				racketeers_create_player_menu( $all_players, "all_players[]");
				echo "</br>";
				racketeers_create_player_menu( $subscribed_players, "subscribed_players[]");
			?>
			<input type="submit" name="racketeers_action" value="Add Players" />
			<input type="submit" name="racketeers_action" value="Remove Players" />
			<input type="submit" name="racketeers_action" value="Remove ALL Players" />
			<input type="submit" name="racketeers_action" value="Manage Group Complete" />
		</form>
		</form>
	</fieldset>
	<?php
}

function racketeers_remove_all_players() {
	$current_user_id = get_current_user_id();
	delete_user_meta($current_user_id, 'group_member');

}
/** racketeers_get_all_players()
 *  Get all the users who are subscribers.  Save their id and email in an array.
 **/
function racketeers_get_all_players() {
	global $debug;

	$users = get_users( array( 'role' => 'subscriber' ) );

	$player = array();
	foreach ( $users as $user ) {
		$player[ $user->ID ] =  $user->user_email ;

	}

	return $player;
}

/** racketeers_get_subscribed_players()
 *  Get all the users who are subscribed to the current group.  Save their id and email in an array.
 *  Users who are subscribed have their id in the user meta from the current user
 **/
function racketeers_get_subscribed_players(  ){
	global $debug;

	$current_user_id = get_current_user_id();
	if ( $debug ) {
		echo "[racketeers_get_subscribed_players]</br>";
	}
	$players_reg = get_user_meta( $current_user_id, 'group_member', false );

	$players = array();
	foreach ( $players_reg as $id ) {
		if ( ! $user_info = get_userdata( $id ) )  
			return;
		$players[ $id ] =  $user_info->user_email;
	}

	return $players;
}



/* racketeers_timeslot support *********************/
global $racketeers_timeslots;
$racketeers_timeslots = array (
    	0=>"7am",
    	"8am",
    	"9am",
    	"10am",
    	"11am",
    	"12pm",
    	"1:30pm",
    	"2:30pm",
    	"3:30pm",
    	"4:30pm",
    	"5:30pm",
    	"6:30pm",
    	"7:00pm",
    	"7:30pm",
    	"8:00pm",
    	"8:30pm",
    	"9:00pm",
    	"9:30pm"
    );
function racketeers_create_timeslot_menu( $name, $selected = 0 ){
	global $racketeers_timeslots;
	echo " Time: ";
	$size = 10;
	racketeers_create_menu ( $name, $racketeers_timeslots, $selected, $size ); 

}
function racketeers_get_timeslot ( $timeslot_number ) { 
	global $racketeers_timeslots;
	return $racketeers_timeslots[ $timeslot_number ];
}
/* END racketeers_timeslot support ******************/



/* racketeers_dayofweek support *********************/
global $racketeers_day_of_week;
$racketeers_day_of_week = array (
    	 0=>"Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"
    );
function racketeers_create_day_menu( $name, $selected = 0 ){
	// corresponds to MySQL day of the week
	global $racketeers_day_of_week;
	echo " Day :";
	$size = 7;
	racketeers_create_menu ( $name, $racketeers_day_of_week, $selected, $size ); 
}
function racketeers_get_day ( $day_number ) { 
	global $racketeers_day_of_week;
	return $racketeers_day_of_week[ $day_number ];
}
/* END racketeers_dayofweek support *********************/


/* racketeers_match_duration support *********************/
global $racketeers_match_duration;
$racketeers_match_duration = array (
    	60=>"60 Minutes", 90=>"90 Minutes"
    );
function racketeers_create_match_duration_menu( $name,  $selected = 60 ){
	global $racketeers_match_duration;
	echo " Duration: ";
	$size = 2;
	racketeers_create_menu ( $name, $racketeers_match_duration , $selected, $size ); 
}
function racketeers_get_match_duration ( $match_duration_number ) { 
	global $racketeers_match_duration;
	return $racketeers_match_duration[ $match_duration_number ];
}
/* END   racketeers_match_duration support *********************/

function racketeers_create_menu( $name, $contents, $selected, $multiple = false, $size = 1)
{
	if ( $multiple ) {
		echo "<select name='$name' multiple size= 'size' >";
	} else {
		echo "<select name='$name' size= 'size'>";
	}
    foreach ( $contents as $key => $value ) {
    	if ( $selected == $key )
    		echo "<option value='$key' selected > $value </option>\n";
    	else
			echo "<option value='$key'> $value </option>\n";
	}
	echo "</select>";
}

/* player menu ***********************/
function racketeers_create_player_menu( $players, $menu_name ){
	echo " $menu_name: ";
	racketeers_create_menu ( $menu_name, $players , 0 , true, 10); 
}
/* END   racketeers_match_duration support *********************/


function racketeers_add_players_to_group(){
	global $debug;
	
	if ( ! isset( $_POST['all_players'] ) ) {
		return;
	}
	$allplayers = $_POST['all_players'];

	$current_user_id = get_current_user_id();
	foreach ( $allplayers as $id ) {
		add_user_meta ( $current_user_id, 'group_member', $id);
	}
}
function racketeers_delete_players_from_group() {
	global $debug;

	if ( $debug ) {
		echo "[racketeers_delete_players_from_group] Post Vars <pre>";
		print_r ( $_POST );
		echo "</pre>";
	}
	if ( ! isset( $_POST['subscribed_players'] ) ) {
		return;
	}
	$subscribed_players = $_POST['subscribed_players'];
	if ( $debug ) {
		echo "[racketeers_delete_players_from_group] subcribed_players <pre>";
		print_r ( $subscribed_players );
		echo "</pre>";
	}
	$current_user_id = get_current_user_id();
	foreach ( $subscribed_players as $id ) {
		if ( $debug ) {
			echo "[subscribed_players] loop $id </br>";
		}
		delete_user_meta ( $current_user_id, 'group_member', $id);
	}
}