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

		if ( true == racketeers_handle_form() ){
			racketeers_display_group_form();
		}

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

	global $debug;
	if (  $debug ){
			echo "[racketeers_handle_form] ";
			echo "<pre>"; print_r( $_POST ); echo "</pre>";
	}
	if ( ! isset( $_POST['racketeers_action'] ) ) return true;

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

		case "Manage Group Players":
			racketeers_manage_players_form( );
			return false;

		case "Manage Group Complete":
			return true;
	
		case "Add Players":
			racketeers_add_players_to_group( );
			racketeers_manage_players_form( );
			return false;
	
		case "Remove Players":
			racketeers_delete_players_from_group( );
			racketeers_manage_players_form( );
			return false;

		case "Remove ALL Players":
			racketeers_remove_all_players();
			racketeers_manage_players_form( );
			return false;

		default:
			echo "[racketeers_handle_form]: bad action";
	}
	return true;
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

	if ($day == "" || $lasttimestamp == "") {
		echo "update group info before adding match";
		return 0;
	}

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

