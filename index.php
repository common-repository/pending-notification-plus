<?php 
/*
Plugin Name: WP Pending Post Notifier Plus
Plugin URI: http://www.fixwordpress.net/?p=491
Version: 1.0
Authors: larry Ngaosi, Robin Wylie, David Morning
Author URI: http://www.fixwordpress.net

Description: This plugin will email a notification when a post has been submitted for review, pending publication. Useful for moderated multi-author blogs. [<a href="http://www.fixwordpress.net/?p=491">WP Pending Post Notifier</a>]

*/
 
 /*  Copyright YEAR  Larry Ngaosi  (email : ngaosi.larry@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
 register_activation_hook( __FILE__, 'ppn_activate_plus' );
 register_deactivation_hook( __FILE__, 'ppn_deactivation_plus' );
 
 // Hook for adding admin menus
add_action('admin_menu', 'sn_add_option_page_notification_plus');
add_action('admin_head', 'add_my_stylesheet');
// action function for above hook
function sn_add_option_page_notification_plus() {
    // Add a new submenu under options:
    add_options_page('Pending Post Notifications Plus', 'Pending Plus', 'edit_themes', 'status_notifier', 'sn_options_page_notification_plus');
}

function add_my_stylesheet() {
		$url = get_option('siteurl');
   		$dir = $url . '/wp-content/plugins/pending-plus/css/';
   		echo '<link rel="stylesheet" type="text/css" href="' . $dir . 'style.css" />';
    }

function sn_options_page_notification_plus() {
	//add_my_stylesheet();
	echo "<div class='wrap'><h2>Pending Post Notifications</h2>";
	$emailArray = getEmails();
	echo_html_form($emailArray);

}


add_filter('transition_post_status', 'notify_status_plus',10,3);

function notify_status_plus($new_status, $old_status, $post) {
    global $current_user;
	$contributor = get_userdata($post->post_author);
    if ($old_status != 'pending' && $new_status == 'pending') {
      $emails=get_option('notificationemails');
      $emails=str_replace("#",",",$emails);
      if(strlen($emails)) {
	  
      	////////////////////////////////////////////////
		/////////////////Robin's code///////////////////
		////////////////////////////////////////////////
		$notificationList = preg_split ('#\([^\)]+\)[,*]#' , $emails, -1, PREG_SPLIT_NO_EMPTY);
		$mailingList = Array();

		$emailRecipients = "";
		$errorlog .=  "notification list:\n";
		$errorlog .= print_r($notificationList, TRUE);
		
		foreach ($notificationList as $value) {
			
			$contribGroup = Array(); 
			
			$errorlog .=  "Checking for whitelist of (contributors) for each notification list entry...\n";
			
			if (strpos($value, "(")) { //if it's got a parenthesis in it it's got a list of permitted contributors...
			
				$errorlog .=  "item has list of permitted contribs\n";
				
				//$users = Array();
				//preg_match_all (",", $value, $users);

				$temp2 = str_replace(")", "", $value);
				$temp = explode("(" , $temp2);
				

				$notificationReceiver = trim($temp[0]);
				$permittedSenders = explode("," , $temp[1]);
				$permittedSenders = array_map('trim', $permittedSenders);
				
				//$output = preg_grep ("#\(.*\)#", $input);
				
				$errorlog .=  "notificationReceiver:\n";
				$errorlog .=  $notificationReceiver."\n";
				
				$errorlog .=  "permittedSenders:\n";
				$errorlog .= print_r($permittedSenders, TRUE);

				if (in_array($contributor->user_email, $permittedSenders)) {
					$emailRecipients .= $notificationReceiver . ",";
				}
				
			
			}
			
		}

		//Ends here
		
        $subject='['.get_option('blogname').'] "'.$post->post_title.'" pending review';
        $message="A new post by {$contributor->display_name} is pending review.\n\n";
        $message.="Author   : {$contributor->user_login} {$contributor->user_email} (IP: {$_SERVER['REMOTE_ADDR']})\n";
        $message.="Title    : {$post->post_title}\n";
		$category = get_the_category($post->ID);
		if(isset($category[0])) 
			$message.="Category : {$category[0]->name}\n";;
        $message.="Review it: ".get_option('siteurl')."/wp-admin/post.php?action=edit&post={$post->ID}\n\n\n";
 
		$queried_post = get_post($post->ID);
 
	
		
		
        $message.="Title: ".$queried_post->post_title."\n";
        $message.="Content: \n".$queried_post->post_content."\n\n\n";
		
		//additional error logging
	
		$errorlog .=  "email addresses to send to:\n";
		$errorlog .=  $emailRecipients."\n";
		
		error_log($errorlog);
		
		//end
		
        wp_mail( $emailRecipients, $subject, $message);
		//wp_mail( "robin.wylie+dummyuser@gmail.com,robin.wylie+dummyuser2@gmail.com,", $subject, $message);
		

		
      }
	} elseif ($old_status == 'pending' && $new_status == 'publish' && $current_user->ID!=$contributor->ID) {
      if(get_option('approvednotification')=='yes') {
        $subject='['.get_option('blogname').'] "'.$post->post_title.'" approved';
        $message="{$contributor->display_name},\n\nYour post has been approved and published at ".get_permalink($post->ID)." .\n\n";
        $message.="By {$current_user->display_name} <{$current_user->user_email}>\n\n\n";
        wp_mail( $contributor->user_email, $subject, $message);
      }
	} elseif ($old_status == 'pending' && $new_status == 'draft' && $current_user->ID!=$contributor->ID) {
      if(get_option('declinednotification')=='yes') {
        $subject='['.get_option('blogname').'] "'.$post->post_title.'" declined';
        $message="{$contributor->display_name},\n\nYour post has not been approved. You can edit the post at ".get_option('siteurl')."/wp-admin/post.php?action=edit&post={$post->ID} .\n\n";
        $message.="By {$current_user->display_name} <{$current_user->user_email}>\n\n\n";
        wp_mail( $contributor->user_email, $subject, $message);
      }
	}
}

function ppn_activate_plus() {
  add_option('notificationemails',get_option('admin_email'));
    add_option('approvednotification','yes');
    add_option('declinednotification','yes');
}
function ppn_deactivation_plus() {
 delete_option('notificationemails');
 delete_option('approvednotification');
 delete_option('declinednotification');
 }
 
 
//Deletes an editor from the group array, along with contributors linked to them
function delete_editor($groupArray){
	for ($i=0;$i<count($groupArray);$i++){
		if($i != $_POST['delete_editor']){
			$revisedArray[] = $groupArray[$i];
		}else{
			for($j=0;$j<count($groupArray[$i]);$j++){
				echo "<p>".$groupArray[$i][$j]." Deleted</p>";
			}
		}
	}
	return $revisedArray;
}

//Adds an editor to the group array
function add_editor($groupArray){
	$emailString = $_POST['new_global_moderator_email'];
	if(!strpos($emailString, ")") AND !strpos($emailString, "(") AND !strpos($emailString, ",")){
		$tempArray[] = $emailString;
		$groupArray[] = $tempArray;
	}else{
		echo "Whoops, mistake in the email";
	}
	return $groupArray;
}

//Deletes a specific contributor from the group array based on the indices passed by the front end form
function delete_contributor($groupArray){
	$contributorIndices = explode("-",$_POST['delete_contributor_index']);
	for($i=0;$i<count($groupArray);$i++){
		$tempArray = Array();
		$revisedGroupArray[] = $tempArray;
		for($j=0;$j<count($groupArray[$i]);$j++){
			if($i != $contributorIndices[0] OR $j != $contributorIndices[1]){
				$revisedGroupArray[$i][] = $groupArray[$i][$j];
			}
		}
	}
	$contributorIndices = NULL;
	return $revisedGroupArray;
}

//Adds a new contributor to the group requested
function add_contributor($groupArray){
	$groupArray[$_POST['new_contributor_group']][] = $_POST['new_contributor_email'];
	return $groupArray;
}

//Creates a new group and appends it to the array of groups
//Returns array of emails
function create_group($groupArray){
	$newGroup = Array($_POST['new_editor_email'],$_POST['new_member_email']);
	$groupArray[] = $newGroup;
	return $groupArray;
}

//Allows the user to edit the email address of a group moderator
function edit_moderator($groupArray){
	for ($i=0;$i<count($groupArray);$i++){
		if($i == $_POST['edited_moderator_index'] AND $_POST['edited_moderator_email'] != NULL){
			$groupArray[$i][0] = $_POST['edited_moderator_email'];
		}
	}
	return $groupArray;
}

//Function checks if any updates have been made to the user base and edits accordingly. Sets error variables if there are any problems
//Returns array of arrays
function check_updates($groupArray){
	
	if($_POST['new_contributor_email'] != NULL){
		if(findErrors($_POST['new_contributor_email'])){
			$groupArray = add_contributor($groupArray);
			update_option('notificationemails',encode_user_groups($groupArray));
		}
		
	}elseif($_POST['new_global_moderator_email'] != NULL){
		if(findErrors($_POST['new_global_moderator_email'])){
			$groupArray = add_editor($groupArray);
			update_option('notificationemails',encode_user_groups($groupArray));
		}
		
	}elseif($_POST['delete_contributor_index'] != NULL){
		$groupArray = delete_contributor($groupArray);
		update_option('notificationemails',encode_user_groups($groupArray));
		
	}elseif($_POST['new_editor_email'] AND $_POST['new_member_email']){ 
		if(findErrors($_POST['new_member_email']) AND findErrors($_POST['new_editor_email'])){
			$groupArray = create_group($groupArray);
			update_option('notificationemails',encode_user_groups($groupArray));
		}
		
	}elseif($_POST['new_editor_email'] XOR $_POST['new_member_email']){
		$_SESSION['error_message'] = "<div class='pendingError'>Creating a new group requires both a group moderator email address and a group member email address</div>";
		$_SESSION['error_group'] = "group";
		
	}elseif($_POST['delete_editor'] != NULL){
		$groupArray = delete_editor($groupArray);
		update_option('notificationemails',encode_user_groups($groupArray));
		
	}elseif($_POST['edited_moderator_email'] != NULL){
		if(findErrors($_POST['edited_moderator_email'])){
			$groupArray = edit_moderator($groupArray);
			update_option('notificationemails',encode_user_groups($groupArray));
		}
	}
	return $groupArray;
}

//Checks for errors in an email address (contains an @ symbol and doesn't contain commas, brackets or hashes)
//Returns boolean
function findErrors($email){
	if(preg_match("/,|#|\(|\)/", $email) OR !strpos($email,"@")){
		$_SESSION['error_message'] = "<div class='pendingError'>Invalid Email Address</div>";
		$_SESSION['error_group'] = intval($_POST['new_contributor_group']);
		return false;
	}else{
		return true;
	}
}

//Function converts the string containing email accounts in the database into an array of emails
//Returns an array of arrays
function extract_user_groups($userString){
$tempArray = explode(",",$userString);
	for($i=0;$i<count($tempArray);$i++){
		if (strpos($tempArray[$i],'(')){
			$firstElement = preg_split ('#\([^\)]+\)#' , $tempArray[$i], -1, PREG_SPLIT_NO_EMPTY);
			$startBracket = strpos($tempArray[$i],"(");
			$endBracket = strpos($tempArray[$i],")");
			$bracketContents = substr($tempArray[$i],$startBracket+1,-1);
			$bracketArray = explode("#",$bracketContents);
			$singleGroup = array_merge($firstElement,$bracketArray);
		}else{
			$arrayElement = Array();
			$arrayElement[] = $tempArray[$i];
			if (strlen($arrayElement[0]) > 0){
				$singleGroup = $arrayElement;
			}else{
				$singleGroup = NULL;
			}
		}
		if($singleGroup){
			$groupArray[] = $singleGroup;
		}
	}
	return $groupArray;
}

//Function converts the array of users, like the one created by extractUserGroups, into a string compatable with the pending notification plugin
//Each array element is a single group (or a global moderator if there is only one email in that element)
//Returns nothing
function encode_user_groups($userArray){
	for($i=0;$i<count($userArray);$i++){
		if(count($userArray[$i])!=1){
			$emailString .=  $userArray[$i][0]."(";
			for ($j=1;$j<count($userArray[$i]);$j++){
				$emailString .= $userArray[$i][$j]."#";
			}
			$emailString = substr($emailString,0,-1)."),";
		}else{
			if(strlen($userArray[$i][0]) > 0){
				$emailString .= $userArray[$i][0].",";
			}
		}
	}
	return substr($emailString,0,-1);
}

//Loads email addresses for groups into an array of arrays and loads any updates made to the database
//Returns array
function getEmails(){
	$emailString = get_option('notificationemails');
	return check_updates(extract_user_groups($emailString));
}


//Echos HTML for the options page forms. 
//Returns nothing
function echo_html_form($groupArray){

	//Sets the contributorIndeces variable to the group index where the deleted contributor is being stored (if one has been deleted)
	if ($_POST['delete_contributor_index'] != NULL){
		$contributorIndices = explode("-",$_POST['delete_contributor_index']);
	}
	
	echo "<div id='postNotificationForm'>\n";
	echo "	<div class='postNotificationBlock' id='group0'>\n";
	echo "	<h2>Global Moderators</h2>";
	
	//Echos out a list of global moderators and a form button which can delete them
	for($i=0;$i<count($groupArray);$i++){
		if (count($groupArray[$i]) == 1){
				echo "	<form class='groupElement' name='site' action='#group0' method='post' id='notifier'>\n";
				echo "		<div>\n";
				echo "			".$groupArray[$i][0]."\n";
				echo "			<input type='hidden' value='$i' name='delete_editor' />\n";
				echo "			<input type='submit' name='submit' value='Delete' />\n";
				echo "		</div>\n";
				echo "	</form>\n";
			}
		}
	echo "</div>";
	
	//Echos out each of the groups stored in the database
	$groupNumbering = 1;
	for($i=0;$i<count($groupArray);$i++){
		if (count($groupArray[$i]) > 1){
			echo "	<div class='postNotificationBlock' id='group".$groupNumbering."'>\n";
			echo "	<h2>Group ".$groupNumbering."</h2>";
			if ($contributorIndices[0]==$i AND $contributorIndices[0] != NULL){
				echo "<div class='pendingError'>".$_POST['delete_contributor_email']." Deleted</div>";
			}
			if ($_SESSION['error_message'] != NULL AND $_SESSION['error_group'] == $i AND !is_string($_SESSION['error_group'])){
				echo $_SESSION['error_message'];
			}
			
			//Either displays moderator email with an edit button or a form to edit the email with a submit button based on whether someone has pressed the 'edit' button
			echo "		<h5>Moderator</h5>";
			if ($i==$_POST['moderator_index'] AND $_POST['edit_moderator'] != NULL){
				echo "		<p>Old Email: ".$groupArray[$i][0]."</p>\n";
				echo "		<form action='#group".($i+1)."' method=post id='notifier'>";
				echo "			<input type='hidden' value='$i'  name='edited_moderator_index' />";
				echo "			New Email:\n";
				echo "			<input type='text' name='edited_moderator_email' value='' class='textInput'/>\n";
				echo "			<input type='submit' value='Submit' name='edited_moderator' class='floatRight' /><br />";
				echo "		</form><br />";				
			}else{
				echo "		<form class='groupElement' action='#group".($i+1)."' method=post id='notifier'>";
				echo "			".$groupArray[$i][0]."\n";
				echo "			<input type='hidden' value='$i' class=notificationSubmit' name='moderator_index' />";
				echo "			<input type='submit' value='Edit' class=notificationSubmit' name='edit_moderator' />";
				echo "		</form>";
			}
			
			//Displays each member of the group
			echo "		<h5>Members</h5>";
			for ($j=1;$j<count($groupArray[$i]);$j++){
				echo "	<form class='groupElement' name='site' action='#group".$groupNumbering."' method='post' id='notifier'>\n";
				echo "		<div>\n";
				echo "			".$groupArray[$i][$j]."\n";
				echo "			<input type='hidden' value='$i-$j' name='delete_contributor_index' />\n";
				echo "			<input type='hidden' value='".$groupArray[$i][$j]."' name='delete_contributor_email' />\n";
				echo "			<input type='submit' name='submit' value='Delete' class='notificationSubmit' />\n";
				echo "		</div>\n";
				echo "	</form>\n";
			}
			
			//Displays form for adding a new member to the group
			echo "		<form name='site' action='#group".$groupNumbering."' method='post' id='notifier'>\n";
			echo "			<h5>New Group ".($i+1)." Member</h5>\n";
			echo "			<input type='text' name='new_contributor_email' class='textInput' />\n";
			echo "			<input type='hidden' name='new_contributor_group' value='$i' />\n";
			echo "			<input type='submit' class='notificationSubmit'/>\n";
			echo "		</form>\n";
			echo "	<br />\n";
			
			//Displays a delete form button at the bottom of the group box
			echo "		<form name='site' action='' method='post' id='notifier'>\n";
			echo "			<input type='hidden' value='$i' name='delete_editor' />\n";
			echo "			<input type='submit' name='submit' value='Delete Group' class='notificationSubmit' />\n";
			echo "		</form>\n";
			echo "</div>";
			
			$groupNumbering++;
		}
	}
	
	echo "	<br />";
	
	//Displays errors which occurred in adding a new global moderator email
	if($_SESSION['error_group'] == 'global' AND $_SESSION['error_group'] != 0){
		echo $_SESSION['error_message'];
	}
	
	//Form for adding a new global moderator
	echo "	<form name='site' action='#global' method='post' id='global' class='newGroup'>\n";
	echo "		<h3>New Global Moderator</h3>\n";
	echo "		<input type='text' name='new_global_moderator_email' value='' class='textInput' /><br />\n";
	echo "		<input type='submit' class='notificationSubmit' name='new_global_moderator'/>\n";
	echo "	</form><br />\n";

	//Displays errors which occured in adding a new group to the database
	if($_SESSION['error_group'] == 'group' AND $_SESSION['error_group'] != 0){
		echo $_SESSION['error_message'];
	}
	
	//Form for adding a new group to the database
	echo "	<form name='site' action='#group' method='post' id='group' class='newGroup'>\n";
	echo "		<h3>New Group</h3>\n";
	echo "		<h5>Group Moderator</h5>";
	echo "		<input type='text' name='new_editor_email' value='' class='textInput' />\n";
	echo "		<h5>Group Member</h5>";
	echo "		<input type='text' name='new_member_email' value='' class='textInput' /><br />\n";
	echo "		<input type='submit' class='notificationSubmit' name='new_group'/>\n";
	echo "	</form>\n";
	echo "</div>\n";
	echo "</div>\n";
	
	print_r($_SESSION);
	
	$_SESSION['error_group'] = NULL;
	$_SESSION['error_message'] = NULL;
}

?>