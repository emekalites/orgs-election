<?php
/*
Plugin Name: Organisation Election
Description: Allow registered users to elect executives
Author: Chukwuemeka Ihedoro
Text Domain: orgs-election
Version: 1.0.0
License: GPL2
*/


// No direct access
if ( !defined( 'ABSPATH' ) ) exit;

define ('B_ELECTION_VERSION' , '1.0.0');

// add plugin upgrade notification
add_action('in_plugin_update_message-orgs-election/orgs-election.php', 'bElectionshowUpgradeNotification', 10, 2);
function bElectionshowUpgradeNotification($currentPluginMetadata, $newPluginMetadata){
	// check "upgrade_notice"
	if (isset($newPluginMetadata->upgrade_notice) && strlen(trim($newPluginMetadata->upgrade_notice)) > 0){
		echo '<p style="background-color: #d54e21; padding: 10px; color: #f9f9f9; margin-top: 10px"><strong>Upgrade Notice:</strong> ';
		echo esc_html($newPluginMetadata->upgrade_notice) . '</p>';
	}
}

// Add Settings and Donate next to the plugin on the plugins page
add_filter('plugin_action_links', 'b_election_plugin_action_links', 10, 2);
function b_election_plugin_action_links($links, $file) {
	static $this_plugin;

	if (!$this_plugin) {
		$this_plugin = plugin_basename(__FILE__);
	}

	if ($file == $this_plugin) {
		// The "page" query string value must be equal to the slug
		// of the Settings admin page we defined earlier, which in
		// this case equals "myplugin-settings".
		$settings_link = '<a href="https://www.buymeacoffee.com/emekaihedoro">Donate</a>';
		array_unshift($links, $settings_link);
		$settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/options-general.php?page=orgs_election">Settings</a>';
		array_unshift($links, $settings_link);
	}

	return $links;
}

//Modify Users Admin page
add_filter('manage_users_columns', 'b_elect_add_user_id_column');
function b_elect_add_user_id_column($columns) {
	$columns['vote_allowed'] = 'Voting Allowed';
	return $columns;
}
add_action('manage_users_custom_column',  'b_elect_show_user_id_column_content', 10, 3);
function b_elect_show_user_id_column_content($value, $column_name, $user_id) {
	$vote_allowed = get_user_meta( $user_id, 'b-elect-allowed', true );
	if ( 'vote_allowed' == $column_name )
		return $vote_allowed;
	return $value;
}
add_action( 'admin_footer-users.php', 'b_elect_add_bulk_actions' );
function b_elect_add_bulk_actions() {
	?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('<option>').val('voteallow').text('Vote Allow')
                .appendTo("select[name='action'], select[name='action2']");
            $('<option>').val('votedisable').text('Vote Disable')
                .appendTo("select[name='action'], select[name='action2']");
        });
    </script>
	<?php
}
add_action( 'admin_action_voteallow', 'b_elect_bulk_voteallow' );
function b_elect_bulk_voteallow() {
	// security: make sure we start from bulk posts
	//check_admin_referer('voteallow');

	foreach ($_REQUEST['users'] as $userid) {
		update_user_meta( $userid, 'b-elect-allowed', 'yes');
	}
	//wp_die( '<pre>' . print_r( $_REQUEST, true ) . '</pre>' ); 
}
add_action( 'admin_action_votedisable', 'b_elect_bulk_votedisable' );
function b_elect_bulk_votedisable() {
	// security: make sure we start from bulk posts
	//check_admin_referer('votedisable');

	foreach ($_REQUEST['users'] as $userid) {
		delete_user_meta( $userid, 'b-elect-allowed');
	}
}

function orgs_election_page() {
	// delete office
	if ( isset($_GET['office']) ) {
		if(!empty($_GET["office"])) {
			$d__election = get_option('b-election-cand-ids', '[]');
			$delection = json_decode($d__election, true);
			unset($delection[$_GET["office"]]);
			update_option('b-election-cand-ids', json_encode($delection));

			wp_redirect( admin_url('options-general.php?page=orgs_election') );
			exit;
		}
	}

	// reset settings
	if ( isset($_POST['resetopts']) ) {
		delete_option('b-election-cand-ids');

		$users_that_voted = get_users(array('meta_key' => 'b-elect-candidates',));
		foreach ($users_that_voted as $item) {
			delete_user_meta( $item->ID, 'b-elect-candidates');
		}
	}

	// create office
	if ( isset($_POST['officesave']) ) {
		if(!empty($_POST["b-election-office"])) {
			$__election = get_option('b-election-cand-ids', '[]');
			$election = json_decode($__election, true);
			$election[$_POST["b-election-office"]] = [];
			update_option('b-election-cand-ids', json_encode($election));
		}
	}

	// Check if options need to be saved, so if coming from form
	if ( isset($_POST['optssave']) ) {
		$election_ids = $_POST["b-election-cands"];
		if(count($election_ids) > 0) {
			update_option('b-election-cand-ids', json_encode($election_ids));
        }

		update_option('b-election-who-can-vote', $_POST["b-election-who-can-vote"]);
		update_option('b-election-support-email', $_POST["b-election-support-email"]);

		if( !empty($_POST["b-election-max-votes"]) ) {
			update_option('b-election-max-votes', $_POST["b-election-max-votes"]);
		} else {
			update_option('b-election-max-votes', '1');
		}

		if( !empty($_POST["b-election-end-time-tick"]) ) {
			update_option('b-election-end-time-tick', 'activate');
		} else {
			delete_option('b-election-end-time-tick');
		}

		if( !empty($_POST["b-election-show-wpml"]) ) {
			update_option('b-election-show-wpml', 'activate');
		} else {
			delete_option('b-election-show-wpml');
		}

		$date_value = $_POST["end_year"] . '-' . $_POST["end_month"] . '-' . $_POST["end_day"] . ' ' . $_POST["end_hour"] . ':' . $_POST["end_min"] . ':00';
		update_option('b-election-end-time', strtotime($date_value));
	}

	$b_election_support_email = get_option('b-election-support-email', false);
	if (!$b_election_support_email) { $b_election_support_email = get_option( 'admin_email', 'No email' ); }
	$b_election_show_wpml = get_option('b-election-show-wpml', false);
	$b_election_who_can_vote = get_option('b-election-who-can-vote', false);

	// retrieve candidate ids
	$__b_election_cand_ids = get_option('b-election-cand-ids', '[]');
	$b_election_cand_ids = json_decode($__b_election_cand_ids, true);

	$b_election_max_votes = get_option('b-election-max-votes', false);
	if (!$b_election_max_votes) { $b_election_max_votes = '1'; }
	$b_election_end_time_tick = get_option('b-election-end-time-tick', false);
	$b_election_end_time = get_option('b-election-end-time', false);
	if (!$b_election_end_time) { $b_election_end_time = strtotime("+1 week"); }
	$endtime = array();
	$endtime["day"] = date('d', $b_election_end_time);
	$endtime["month"] = date('n', $b_election_end_time);
	$endtime["year"] = date('Y', $b_election_end_time);
	$endtime["hour"] = date('H', $b_election_end_time);
	$endtime["min"] = date('i', $b_election_end_time);

	echo '<h1>Organisation Election</h1>';
	echo '<table border="0"><tr><td>';

	echo '<form action="" method="post">';
	echo '<p><label>Office Name: </label>';
	echo '<input type="text" name="b-election-office" id="b-election-office" value="" size="100" /><br>';
	echo '<label>&nbsp;&nbsp;&nbsp;(Enter the office name)</label></p>';
	echo '<input type="submit" name="officesave" value="Add Office" />';
	echo '</form>';

	echo '<form action="" method="post">';
	foreach ($b_election_cand_ids as $key => $cand_id) {
		echo '<div class="card" style="width: 100%;min-width: 100%;max-width: 100%;">';
		echo '<p><label>Name of Office: </label> <strong>'.$key.'</strong> <a class="mce-btn" href="'.admin_url('options-general.php?page=orgs_election&office='.$key).'">Remove Office</a></p>';
		echo '<p><label>Candidate IDs, separated with a comma : </label>';
		echo '<input type="text" name="b-election-cands['.$key.'][]" id="b-election-cand-ids" value="' . join(',', $cand_id) . '" size="100" /><br>';
		echo '<label>&nbsp;&nbsp;&nbsp;(Enter the media IDs like: 12560,17852,11456,15845)</label></p>';
		echo '</p>';
		echo '</div>';
    }

	echo '<br><br>';

	if (!$b_election_who_can_vote) { $b_election_who_can_vote = 'All'; }
	echo '<p><label>Who is allowed to vote : </label>';
	echo '<select name="b-election-who-can-vote">';
	echo '<option value="All" ';
	if ($b_election_who_can_vote == 'All') { echo 'selected'; }
	echo '>All registered users</option>';
	echo '<option value="List" ';
	if ($b_election_who_can_vote == 'List') { echo 'selected'; }
	echo '>Only subset of users</option>';
	echo '</select></p>';

	echo '<p><label>Maximum number of votes per user : </label>';
	echo '<input type="text" name="b-election-max-votes" id="b-election-max-votes" value="' . $b_election_max_votes . '" size="3" /></p>';

	echo '<p><label>Email address for support questions : </label>';
	echo '<input type="text" name="b-election-support-email" id="b-election-support-email" value="' . $b_election_support_email . '" size="50" /></p>';

	echo '<p><input type="checkbox" name="b-election-end-time-tick" id="b-election-end-time-tick" value="b-election-end-time-tick" ';
	if ($b_election_end_time_tick) { echo 'checked'; }
	echo '><label for="b-election-end-time-tick">Block voting on </label>';
	echo '<select name="end_day">';
	for ($i = 1; $i <= 31; $i++) {
		if ($i < 10) {
			$d = '0' . $i;
		} else {
			$d = $i;
		}
		if ($d == $endtime["day"]) {
			$thisone = 'selected';
		} else {
			$thisone = '';
		}
		echo '<option value="' . $i . '" '. $thisone . '>' . $d . '</option>';
	}
	echo '</select>';

	echo '<select name="end_month">';
	for ($i = 1; $i <= 12; $i++) {
		if ($i == $endtime["month"]) {
			$thisone = 'selected';
		} else {
			$thisone = '';
		}
		$t = '22-' . $i . '-2000 00:00:00';
		echo '<option value="' . $i . '" ' . $thisone . '>' . strftime('%B', strtotime($t)) . '</option>';
	}
	echo '</select>';

	echo '<select name="end_year">';
	for ($i = 2015; $i <= 2030; $i++) {
		if ($i == $endtime["year"]) {
			$thisone = 'selected';
		} else {
			$thisone = '';
		}
		echo '<option value="' . $i . '" ' . $thisone . '>' . $i . '</option>';
	}
	echo '</select>';

	echo '&nbsp;&nbsp;<label>at </label>';
	echo '<select name="end_hour">';
	for ($i = 0; $i <= 23; $i++) {
		if ($i < 10) {
			$d = '0' . $i;
		} else {
			$d = $i;
		}
		if ($d == $endtime["hour"]) {
			$thisone = 'selected';
		} else {
			$thisone = '';
		}
		echo '<option value="' . $i . '" ' . $thisone . '>' . $d . '</option>';
	}
	echo '</select>';

	echo '<select name="end_min">';
	for ($i = 0; $i <= 59; $i += 5) {
		if ($i < 10) {
			$d = '0' . $i;
		} else {
			$d = $i;
		}
		if ($d == $endtime["min"]) {
			$thisone = 'selected';
		} else {
			$thisone = '';
		}
		echo '<option value="' . $i . '" ' . $thisone . '>' . $d . '</option>';
	}
	echo '</select>';

	echo '</p>';


	if ( function_exists('icl_object_id') ) {
		echo '<p><input type="checkbox" name="b-election-show-wpml" id="b-election-show-wpmlk" value="b-election-show-wpml" ';
		if ($b_election_show_wpml) { echo 'checked'; }
		echo '><label for="b-election-show-wpml">Show WPML language selector.</label><p>';
	}

	echo '<input type="submit" name="optssave" value="Save settings" />';
	echo '  ';
	echo '<input type="submit" name="resetopts" value="Reset elections" />';
	echo '</form>';

	echo '<p>&nbsp;</p>';
	echo '<h3>Usage</h3>';
	echo '<p><b>Create a page and enter the [orgs_election] shortcode.</b></p>';
	echo '<p>Final version should have a "select" button from the Media Gallery directly, for now enter the media library IDs like: 12560,17852,11456,15845<br>';
	echo 'Update the images in the gallery: Image title = Candidate Name; Caption = Candidate competence field; Description = Candidate resume; Picture should be square (or slightly wider)</p>';
	echo '<p>Permissions are set by using the bulk options in "Users > All Users" where you can allow or disable voting, only works using the TOP box for now</p>';


	echo '</td><td style="text-align: left;vertical-align: top;padding: 35px;">';
	echo '<table style="border: 1px solid green;">';
	echo '<tr><td style="vertical-align:top;text-align:center;padding:15px;">Is this plugin helpful ?<br><a href="https://www.buymeacoffee.com/emekaihedoro" target="_blank"><img src="https://www.paypalobjects.com/webstatic/en_US/btn/btn_donate_cc_147x47.png" height="40"></a></td></tr>';
	echo '<tr><td style="vertical-align:top;text-align:center;padding:15px;">Just 1 or 2 USD for a coffee<br>is very much appreciated!</td></tr>';
	echo '</table>';

	echo '</td></tr></table>';

	echo '<br>----------------<br><br>';
	$users_that_voted = get_users(array('meta_key' => 'b-elect-candidates',));
	echo '<h2>REPORT 1: Users that voted online (' . count($users_that_voted) . ' in total)</h2>';
	echo '<table border="0">';
	echo '<tr><th style="padding:8px;">ID</th><th style="padding:8px;">Name</th><th style="padding:8px;">email</th><th style="padding:8px;">username</th></tr>';
	foreach ($users_that_voted as $item) {
		echo '<tr><td style="padding:8px;">' . $item->ID . '</td><td style="padding:8px;">' . $item->display_name . '</td><td style="padding:8px;">' . $item->user_email . '</td><td style="padding:8px;">' . $item->user_nicename . '</td></tr>';
	}
	echo '</table>';
	echo '<br>----------------<br><br>';
	echo '<h2>REPORT 2: Votes received per candidate</h2>';

	// Fill the array
	$AllCandVotes = array();
	foreach ($b_election_cand_ids as $key => $cand_id) {
		$votes = [];
		$tie = [];
		foreach ( $users_that_voted as $item ) {
			$aUserVotes = json_decode( get_user_meta( $item->ID, 'b-elect-candidates', true ) );
            foreach ($aUserVotes as $s) {
    			if ($s->office == strtolower(str_replace(' ', '_', $key))) {
    			    $tie[] = $s->cand_id;
    			    $votes[] = $s->cand_id;
    			}
            }
		}
		$votes = array_count_values($votes);
		arsort($votes);

		$v = (object) null;
		$v->tie = array_unique($tie);
		$v->num_votes = count($users_that_voted);
		$v->votes = $votes;

		$AllCandVotes[$key] = $v;
	}

	// error_log(json_encode($AllCandVotes));
	// Now print it
	foreach ($AllCandVotes as $s=>$v) {
	    echo '<div class="card">';
	    echo '<table border="0">';
	    echo '<tr><td style="padding:8px;" colspan="2"><h4>'.$s.'</h4></td></tr>';
	    echo '<tr><th style="padding:8px;text-align: left;">Name</th><th style="padding:8px;text-align: left;">votes</th></tr>';
	    $i = 0;
	    foreach ($v->votes as $k => $count){
	        $i++;
		    $cpost = get_post($k);
		    $winner = $i == 1 ? (count($v->tie) == $v->num_votes ? '' : ' ------> <strong>winner</strong>') : '';
		    echo '<tr><td style="padding:8px;">' . $cpost->post_title . '</td><td style="padding:8px;">'. $count.$winner .'</td></tr>';
        }
	    echo '</table>';
	    echo '</div>';
	}
	echo '<br>';

}

function b_election_add_admin_menu() {
	$confHook = add_options_page('Organisation Election', 'Organisation Election', 'activate_plugins', 'orgs_election', 'orgs_election_page');
}
add_action('admin_menu', 'b_election_add_admin_menu');

function orgs_election_cookie() {
	setcookie('b-elect-candidates', '[]');
}
add_action( 'init', 'orgs_election_cookie');

add_shortcode( 'orgs_election', 'shortcode_b_election' );
function shortcode_b_election() {
	$b_election_support_email = get_option('b-election-support-email', false);
	if (!$b_election_support_email) { $b_election_support_email = get_option( 'admin_email', 'No email' ); }
	if ( function_exists('icl_object_id') ) {
		$b_election_show_wpml = get_option('b-election-show-wpml', false);
		if ($b_election_show_wpml) {
			do_action('icl_language_selector'); echo '<br>';
		}
	}

	if (!is_user_logged_in() ) {
		echo 'Sorry, you need to be logged-in to be able to vote.<br><br>';
		echo '<p>Troubles ? Just drop an email to <a href="mailto:' . $b_election_support_email . '">' . $b_election_support_email . '</a> .</p>';
		return;
	}

	$b_election_end_time_tick = get_option('b-election-end-time-tick', false);
	if ($b_election_end_time_tick) {
		$b_election_end_time_tick = get_option('b-election-end-time', false);
		if ($b_election_end_time_tick <= current_time( 'timestamp', 1 ) ) {
			// Time is up !
			echo 'Sorry, you can no longer vote because the deadline has passed.<br><br>';
			echo 'Deadline' . date( 'Y-m-d H:i:s', $b_election_end_time_tick ) . '<br>';
			return;
		}
	}

	global $current_user;
	$user_id = $current_user->ID;
	$b_elect_candidates = get_user_meta( $user_id, 'b-elect-candidates', true );
	if ($b_elect_candidates) {
		$b_elect_candidates_voted = json_decode($b_elect_candidates);
		if(count($b_elect_candidates_voted) > 0){
			echo 'Welcome back.<br><br>';
			echo 'You have already voted. Your candidates are:<br><br>';
			echo '<div class="container">';
			foreach ($b_elect_candidates_voted as $candidate) {
				echo '<div class="row">';
				echo '<div class="col-md-12">';
				echo '<h2>'.ucwords(str_replace('_', ' ', $candidate->office)).'\'s Office</h2>';
				$cpost = get_post($candidate->cand_id);
				echo '<div style="width:220px;height:270px;float:left;margin:20px;padding:5px;border:4px solid #454545">';
				echo '<img style="border-style: none;box-shadow: none;display: block;margin-left: auto;margin-right: auto;" src="' . $cpost->guid . '" width="150" alt="' . $cpost->post_content . '" title="' . $cpost->post_content . '" /><br>';
				echo '<b><center>' . $cpost->post_title . '</center></b><br><center>' . $cpost->post_excerpt . '</center><br>';
				echo '</div>';
				echo '</div>';
				echo '</div>';
			}
			echo '</div>';
			echo '<br><br>';
			return;
        }
	}

	$b_election_who_can_vote = get_option('b-election-who-can-vote', false);
	if ($b_election_who_can_vote == 'List') {
		$user_perm = get_user_meta( $user_id, 'b-elect-allowed', true );
		if ($user_perm == 'yes') {
			// ok, user is allowed to vote
		} else {
			echo 'Hi, it seems you have not been given the right to vote.<br><br>';
			echo 'If you think this is an error, please send an email to <a href="mailto:' . $b_election_support_email . '">' . $b_election_support_email . '</a> .';
			echo '<br><br>';
			return;
		}
	}


	if ( isset($_POST['submitvotes']) ) {
		if(!isset($_COOKIE['b-elect-candidates'])) {
			echo 'Something went wrong. Please close your browser, come back to this page and try again. If problems persist, contact the webmaster.';
		} else {
			$b_elect_candidates = json_decode(html_entity_decode(stripslashes($_COOKIE['b-elect-candidates'])));
		    update_user_meta( $user_id, 'b-elect-candidates', json_encode($b_elect_candidates) );

		    echo 'Thank you for voting.<br><br>The following votes have been registered for you:';
		    unset($_COOKIE['b-elect-candidates']);
			echo '<div class="container">';
			foreach ($b_elect_candidates as $candidate) {
				echo '<div class="row">';
				echo '<div class="col-md-12">';
				echo '<h2>'.ucwords(str_replace('_', ' ', $candidate->office)).'\'s Office</h2>';
				$cpost = get_post($candidate->cand_id);
				echo '<div style="width:220px;height:270px;float:left;margin:20px;padding:5px;border:4px solid #454545">';
				echo '<img style="border-style: none;box-shadow: none;display: block;margin-left: auto;margin-right: auto;" src="' . $cpost->guid . '" width="150" alt="' . $cpost->post_content . '" title="' . $cpost->post_content . '" /><br>';
				echo '<b><center>' . $cpost->post_title . '</center></b><br><center>' . $cpost->post_excerpt . '</center><br>';
				echo '</div>';
				echo '</div>';
				echo '</div>';
			}
			echo '</div>';
		    echo '<br><br>';
		}
	}
	else {
		$__b_election_cand_ids = get_option('b-election-cand-ids', '[]');
		$b_election_cand_ids = json_decode($__b_election_cand_ids, true);

		$b_election_max_votes = get_option('b-election-max-votes', false);
		if (!$b_election_max_votes) { $b_election_max_votes = '1'; }

		if ($b_election_max_votes == 1) {
			$sel_cand = 'Select your 1 candidate ';
		} else {
			$sel_cand = 'Select up to ' . $b_election_max_votes .' candidates ';
		}

		echo '<div style="padding-top: 20px;">' . $sel_cand . 'from the list below. When you have made your choice, click the "Vote" button.</div>';
		echo 'If during the selection you click on the wrong candidate, just click again to remove the vote.<br>';
		echo 'Once you click the "Submit my votes" button, your choice is stored and can no longer be changed.</div><br>';

		echo '<div class="container">';
		foreach ($b_election_cand_ids as $key=>$cand_id) {
			$candidates = explode(',' , join(',', $cand_id));
			echo '<div class="row">';
			echo '<div class="col-md-12">';
			echo '<h2>'.$key.'\'s Office</h2>';
			foreach ($candidates as $candidate) {
				if($candidate == ''){
				}
				else{
					$cpost = get_post($candidate);
					echo '<div style="width:220px;height:270px;float:left;margin:20px;padding:5px;border:4px solid #454545" onclick="clicked_vote(\'' . strtolower(str_replace(' ', '_', $key)) . '\',\'' . $cpost->ID . '\',\'' . $cpost->post_title . '\');" id="candidate-'.strtolower(str_replace(' ', '_', $key)).'-'. $cpost->ID .'">';
					echo '<img style="border-style: none;box-shadow: none;display: block;margin-left: auto;margin-right: auto;" src="' . $cpost->guid . '" width="150" alt="' . $cpost->post_content . '" title="' . $cpost->post_content . '" /><br>';
					echo '<b><center>' . $cpost->post_title . '</center></b><br><center>' . $cpost->post_excerpt . '</center><br>';
					echo '</div>';
				}
			}
			echo '</div>';
			echo '</div>';
		}
		echo '</div>';

		echo '<br><div class=""><form action="" method="post" style="display: inline;">';
		echo '<input type="submit" name="submitvotes" value="Submit my votes" style="padding:5px 15px; background:#ccc; border:0 none; cursor:pointer; -webkit-border-radius: 5px; border-radius: 5px; font-size:18px;" disabled=disabled />';
		echo '</form>';
		echo '</div>';


		?>
        <script type="text/javascript">
            var elections = [];
            function clicked_vote(office, cand_id, cand_name) {
                // console.log(office, cand_id,cand_name);
                // console.log(Cookies.get('b-elect-candidates'));
                var selected = elections.find(function (e) {
                    return e.cand_id === cand_id && e.office === office && e.cand_name === cand_name;
                });

                if(!selected){
                    var officeFound = elections.find(function (e) {
                        return e.office === office;
                    });

                    var officeIndex = elections.findIndex(function (e) {
                        return e.office === office;
                    });

                    if(officeFound){
                        jQuery("#candidate-" + officeFound.office + "-" + officeFound.cand_id).css("border", "4px solid #454545");
                        elections.splice(officeIndex, 1);
                        elections.push({office, cand_id, cand_name});
                        jQuery("#candidate-" + office + "-" + cand_id).css("border", "4px solid #ff0000");
                    }
                    else {
                        elections.push({office, cand_id, cand_name});
                        jQuery("#candidate-" + office + "-" + cand_id).css("border", "4px solid #ff0000");
                    }
                }
                else {
                    // console.log('selected: ', selected);
                    var index = elections.findIndex(function (e) {
                        return e.cand_id === cand_id && e.office === office && e.cand_name === cand_name;
                    });
                    // console.log(index);
                    elections.splice(index, 1);
                    jQuery("#candidate-" + office + "-" + cand_id).css("border", "4px solid #454545");
                }
                // console.log(JSON.stringify(elections) + " => " + elections.length);

                document.cookie = "b-elect-candidates=" + JSON.stringify(elections);

                if (elections.length < <?php echo count($b_election_cand_ids); ?> ) {
                    jQuery("input[type=submit]").prop("disabled", true);
                } else {
                    jQuery("input[type=submit]").prop("disabled", false);
                }
            }
        </script>

		<?php
	}
}

?>
