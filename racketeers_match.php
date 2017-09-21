<?php
/**
 **  This file contains all the support functions for the table match.
 **  Matches can only be created by hosts (aka match organizers).
 **  The match table is created with the SQL below.
 **  Players are users.
 **
 **/

/**
 * racketeers_match_create_table ( $match_table_name)
 * creates a match table for this plugin.
 * called by wordpress init.  
 */

function racketeers_match_create_table ( $match_table_name) {
	
	$sql = 	"CREATE TABLE $match_table_name(
		racketeers_match_id   int not null auto_increment,
		racketeers_match_organizer_ID int,
		racketeers_match_date date,
		racketeers_match_host int,
		racketeers_match_player_1 int,
		racketeers_match_player_2 int,
		racketeers_match_player_3 int,
		racketeers_host_status ENUM('confirmed', 'unconfirmed', 'needsub'),
		racketeers_match_player_1_status ENUM('confirmed', 'unconfirmed', 'needsub'),
		racketeers_match_player_2_status ENUM('confirmed', 'unconfirmed', 'needsub'),
		racketeers_match_player_3_status ENUM('confirmed', 'unconfirmed', 'needsub'),
		PRIMARY KEY( racketeers_match_id )
	) engine = InnoDB;";
    dbDelta( $sql );

}
/**  racketeers_match_delete_table()
 ** deletes match table for this plugin.
 *   called by wordpress.
 */
function racketeers_match_delete_table( $match_table_name ) {
	global $wpdb; 

    $sql = "DROP TABLE IF EXISTS $match_table_name;";
    $wpdb->query( $sql );
}

/** racketeers_organizer_hub
 *  This is the main entry for the organizer account.
 *  The group form is displayed, and the organizer can manage
 *  matches (add, update, delete).
 *
 *  KBL TODO - what is the return val for display group form
 *
 **/
function racketeers_organizer_hub() {

		racketeers_handle_form();
		racketeers_display_group_form();
		racketeers_display_matches();
}
/** 
 * Display all the matches in the match table.
 * KBL todo - fix for group support
 *
 **/
function racketeers_display_matches( ) {

	global $debug;
	global $wpdb;

	$match_table_name = $wpdb->prefix . constant( "MATCH_TABLE_NAME" );
 
 	//kbltodo - maybe add where organizer is the current user?
 	// or maybe where current user is subscribed to 
 	$query = "SELECT * FROM $match_table_name";
	$allmatches = $wpdb->get_results( $query );

	//racketeers_create_match_table_header(); 
	//racketeers_create_match_add_row( $thisgroup );

	if ( $allmatches ) {
		foreach ( $allmatches as $thismatch ) {
			racketeers_create_match_table_row( $thismatch );
		}
	} else { 
		?><h3>No matches.  Add one!</h3><?php
	}
			
	//racketeers_create_match_table_footer(); // end the table
}

/**
* this handles all racketeers organizer action.
* update the group info
* create matches
* delete matches
**/
function racketeers_handle_form(  ) { 


	if ( ! isset( $_POST['racketeers_action'] ) ) return;


	global $debug;
	if (  $debug ){
			echo "[racketeers_handle_form] ";
			echo "<pre>"; print_r( $_POST ); echo "</pre>";
	}

	switch ( $_POST[ 'racketeers_action' ] ) {
		case "Update Group":
			racketeers_update_group_info(  );
			break;

		case "Delete Group Information":
			racketeers_delete_group_info(  );
			break;
			
		case "Delete Match":
			// kbl todo - add error checking for match_id ??
			racketeers_delete_match( $_POST['racketeers_match_id'] );
			break;
			
		case "Add Match":
			racketeers_add_match( );
			break;
			
		default:
			echo "[racketeers_handle_form]: bad action";
	}
} 


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
		</form>
		</form>
	</fieldset>
	<?php
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

/** racketeers_add_match
 *   adds an empty match to the match table.  
 *   users add themselves later
 */
function racketeers_add_match(  ) {
	global $wpdb;
	global $debug;

	if ( $debug ){
			echo "[racketeers_add_match] </br>";
	}

	$current_user_id = get_current_user_id();

	if ( ! racketeers_is_user_organizer( $current_user_id ) ) {
		if ( $debug ){
			echo "[racketeers_add_match] user not organizer</br>";
			return 0;   // no rows affected
		}
	}

	$day = get_user_meta( $current_user_id, "racketeers_day", true );
	$lasttimestamp = get_user_meta( $current_user_id, "racketeers_last_match_timestamp", true );
	$nexttimestamp = racketeers_get_next_match_timestamp( $day, $lasttimestamp);

	if ( $debug ) {
		echo "[racketeers_add_match] day is $day</br>";
		echo "[racketeers_add_match] lasttimestamp is ". date( "Y-m-d", $lasttimestamp ) ."</br>";
		echo "[racketeers_add_match] nexttimestamp is ". date( "Y-m-d", $nexttimestamp ) ."</br>";
	}

	update_user_meta( $current_user_id, "racketeers_last_match_timestamp", $nexttimestamp);

	$datestr   = date( "Y-m-d", $nexttimestamp );
	$thismatch = array( 
					'racketeers_match_date' => $datestr,
					'racketeers_match_organizer_ID' => $current_user_id
				 );

	$table_name = $wpdb->prefix . constant( "MATCH_TABLE_NAME" );
	$rows_affected = $wpdb->insert( $table_name, $thismatch );
	
	if ( 0 == $rows_affected ) {
		echo "INSERT ERROR for " . $thismatch[ 'racketeers_match_date' ] ;
		if ( $debug ){
			echo "[racketeers_add_match] Fail </br>";
			echo "<pre>"; print_r( $_POST ); echo "</pre></br>";
		}
	}

	return $rows_affected;

} 

/** racketeers_get_next_match_timestamp
 * returns the unix timestamp of the next occurance of the day of the week
 * after the current timestamp
 * The day of the week comes from MySQL
 * (0=Monday, 1=Tuesday, 2=Wednesday, 3=Thursday, 4=Friday, 5=Saturday, 6=Sunday) 
 * 
 */
function racketeers_get_next_match_timestamp ( $day, $starttime ) {

	global $debug;
	if ( $debug ){
		echo "[racketeers_get_next_match_day] day: $day starttime: $starttime</br>";
	}

	switch ( $day ){
		case 0:	
			$next_match_unix_timestamp = strtotime( "next Monday",    $starttime); 
			break;
		case 1:	
			$next_match_unix_timestamp = strtotime( "next Tuesday",   $starttime); 
			break;
		case 2:	
			$next_match_unix_timestamp = strtotime( "next Wednesday", $starttime); 
			break;
		case 3:	
			$next_match_unix_timestamp = strtotime( "next Thursday",  $starttime); 
			break;
		case 4:	
			$next_match_unix_timestamp = strtotime( "next Friday",    $starttime); 
			break;
		case 5:	
			$next_match_unix_timestamp = strtotime( "next Saturday",  $starttime); 
			break;
		case 6:	
			$next_match_unix_timestamp = strtotime( "next Sunday",    $starttime); 
			break;
		default: 
			echo "[racketeers_get_next_match_day: bad input </br>";
			break;
	}

	return $next_match_unix_timestamp ;
}

/** racketeers_update_match
 *  updates match data - will be used later by subscribers
 * 
 */
function racketeers_update_match( $thismatch ) {
	global $wpdb;
	
	$table_name = $wpdb->prefix . constant( "MATCH_TABLE_NAME" );
	$where = array( 'racketeers_match_id' => $thismatch[ 'racketeers_match_id' ] );
	$rows_affected = $wpdb->update( $table_name, $thismatch, $where );
	return $rows_affected;

} 

/** racketeers_delete_match
 *  deletes a specific match (id passed)
 * 
 */
function racketeers_delete_match( $match_id ) {
	global $wpdb;
	
	$table_name = $wpdb->prefix . constant( "MATCH_TABLE_NAME" );
	$where = array( 'racketeers_match_id' => $match_id );

	$rows_affected = $wpdb->delete( $table_name, $where );

	return $rows_affected;
} 

/** racketeers_show_match_players
 *  dumps the players for a specific math
 * 
 */
function racketeers_show_match_players ( $match_id ) {
	global $wpdb;
	
	$table_name = $wpdb->prefix . constant( "MATCH_TABLE_NAME" );
	$where = array( 'racketeers_match_id' => $match_id );


	$thismatch = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE racketeers_match_id=%d", $match_id));

	echo "Player Host:  " . $thismatch[0]->racketeers_match_host . " ** Status: " . $thismatch[0]->racketeers_host_status . "</br>";
	echo "   Player 1:  " . $thismatch[0]->racketeers_match_player_1 . " ** Status: " . $thismatch[0]->racketeers_match_player_1 . "</br>";
	echo "   Player 2:  " . $thismatch[0]->racketeers_match_player_2 . " ** Status: " . $thismatch[0]->racketeers_match_player_2 . "</br>";
	echo "   Player 3:  " . $thismatch[0]->racketeers_match_player_3 . " ** Status: " . $thismatch[0]->racketeers_match_player_3 . "</br>";

}

/** 
 ** create_match_table_header()
 ** This function creates the header div for the list of matches.
 **/
function racketeers_create_match_table_header() {
	?>
		<div id="match_error"></div>
		<div class="nttable">
			<div class="nttablerow">
				<div class="nttablecellnarrow">Date</div>
				<div class="nttablecellnarrow">Title</div>
				<div class="nttablecellauto"></div>
			</div>
	<?php
}
function racketeers_create_match_table_footer() {
	?></div><?php
}

/**
 ** create match add row()
 ** This function creates a row in the table with a form to add a match
 **  When you add a match, you add data, time, title.  Players add themselves later.
 **  Each match must have a groupID as all matches must be associated with one group.
 **  KBL TODO - how to find groupID?
 **/
function racketeers_create_match_add_row( $thisgroup ) {
	?>
		<div class="ntaddrow">
			<form method="post" class="matchForm">
				<div class="nttablecellnarrow">
					Add/update group information
				<div class="nttablecellauto">
					Add next month's matches
					<input type="submit" name="racketeers_action" id="addMatchButton" value="Add Matches"/>
				</div>
			</form>
		</div><!-- end nttableaddrow -->
	<?php
}

/**
 ** create match_table_row
 ** this function creates one row of the list of matches.
 ** Dump the match passed with options to change match details.
 ** KBL TODO - we may dump players here
 **/
function racketeers_create_match_table_row( $thismatch ) {
	?>
		<div class="nttablerow">
			<form method="post" class="matchForm">
				<div class="matchtablecellnarrow">
					<input type="text" name="racketeers_match_date" class="datepicker" value="<?php echo $thismatch->racketeers_match_date; ?>" />
					<input type="hidden" name="racketeers_match_id" value="<?php echo $thismatch->racketeers_match_id; ?>" />
				</div>		
				<?php racketeers_show_match_players ( $thismatch->racketeers_match_id ); ?>
				<div class="nttablecellauto">
					<input type="submit" name="racketeers_action" value="Delete Match"/>
				</div>
			</form>
		</div>
	<?php
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
	racketeers_create_menu ( $name, $racketeers_timeslots, $selected ); 

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
	racketeers_create_menu ( $name, $racketeers_day_of_week, $selected ); 
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
	racketeers_create_menu ( $name, $racketeers_match_duration , $selected ); 
}
function racketeers_get_match_duration ( $match_duration_number ) { 
	global $racketeers_match_duration;
	return $racketeers_match_duration[ $match_duration_number ];
}
/* END   racketeers_match_duration support *********************/

function racketeers_create_menu( $name, $contents, $selected )
{
	echo "<select name=\"$name\">";
    foreach ( $contents as $key => $value ) {
    	if ( $selected == $key )
    		echo "<option value=\"$key\" selected > $value </option>\n";
    	else
			echo "<option value=\"$key\"> $value </option>\n";
	}
	echo "</select>";
}
