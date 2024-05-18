<?php
/**
 * Profile Picture
 * Copyright 2010 Starpaul20
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Neat trick for caching our custom template(s)
if(defined('THIS_SCRIPT'))
{
	if(THIS_SCRIPT == 'usercp.php')
	{
		global $templatelist;
		if(isset($templatelist))
		{
			$templatelist .= ',';
		}
		$templatelist .= 'usercp_profilepicture,usercp_profilepicture_auto_resize_auto,usercp_profilepicture_auto_resize_user,usercp_profilepicture_current,usercp_profilepicture_description,usercp_profilepicture_remote,usercp_profilepicture_remove,usercp_profilepicture_upload,usercp_nav_profilepicture';
	}

	if(THIS_SCRIPT == 'private.php')
	{
		global $templatelist;
		if(isset($templatelist))
		{
			$templatelist .= ',';
		}
		$templatelist .= 'usercp_nav_profilepicture';
	}

	if(THIS_SCRIPT == 'usercp2.php')
	{
		global $templatelist;
		if(isset($templatelist))
		{
			$templatelist .= ',';
		}
		$templatelist .= 'usercp_nav_profilepicture';
	}

	if(THIS_SCRIPT == 'member.php')
	{
		global $templatelist;
		if(isset($templatelist))
		{
			$templatelist .= ',';
		}
		$templatelist .= 'member_profile_profilepicture,member_profile_profilepicture_description,member_profile_profilepicture_profilepicture';
	}

	if(THIS_SCRIPT == 'modcp.php')
	{
		global $templatelist;
		if(isset($templatelist))
		{
			$templatelist .= ',';
		}
		$templatelist .= 'modcp_editprofile_profilepicture,modcp_editprofile_profilepicture_description';
	}
}

// Tell MyBB when to run the hooks
$plugins->add_hook("global_start", "profilepic_header_cache");
$plugins->add_hook("global_intermediate", "profilepic_header");
$plugins->add_hook("usercp_start", "profilepic_run");
$plugins->add_hook("usercp_menu_built", "profilepic_nav");
$plugins->add_hook("member_profile_end", "profilepic_profile");
$plugins->add_hook("fetch_wol_activity_end", "profilepic_online_activity");
$plugins->add_hook("build_friendly_wol_location_end", "profilepic_online_location");
$plugins->add_hook("modcp_do_editprofile_start", "profilepic_removal");
$plugins->add_hook("modcp_editprofile_start", "profilepic_removal_lang");
$plugins->add_hook("datahandler_user_delete_content", "profilepic_user_delete");

$plugins->add_hook("admin_user_users_edit_graph_tabs", "profilepic_user_options");
$plugins->add_hook("admin_user_users_edit_graph", "profilepic_user_graph");
$plugins->add_hook("admin_user_users_edit_commit_start", "profilepic_user_commit");
$plugins->add_hook("admin_formcontainer_end", "profilepic_usergroup_permission");
$plugins->add_hook("admin_user_groups_edit_commit", "profilepic_usergroup_permission_commit");
$plugins->add_hook("admin_tools_system_health_output_chmod_list", "profilepic_chmod");

// The information that shows up on the plugin manager
function profilepic_info()
{
	global $lang;
	$lang->load("profilepicture", true);

	return array(
		"name"				=> $lang->profilepic_info_name,
		"description"		=> $lang->profilepic_info_desc,
		"website"			=> "http://galaxiesrealm.com/index.php",
		"author"			=> "Starpaul20",
		"authorsite"		=> "http://galaxiesrealm.com/index.php",
		"version"			=> "1.4",
		"codename"			=> "profilepic",
		"compatibility"		=> "18*"
	);
}

// This function runs when the plugin is installed.
function profilepic_install()
{
	global $db, $cache;
	profilepic_uninstall();

	switch($db->type)
	{
		case "pgsql":
			$db->add_column("users", "profilepic", "varchar(200) NOT NULL default ''");
			$db->add_column("users", "profilepicdimensions", "varchar(10) NOT NULL default ''");
			$db->add_column("users", "profilepictype", "varchar(10) NOT NULL default ''");
			$db->add_column("users", "profilepicdescription", "varchar(255) NOT NULL default ''");

			$db->add_column("usergroups", "canuseprofilepic", "smallint NOT NULL default '1'");
			$db->add_column("usergroups", "canuploadprofilepic", "smallint NOT NULL default '1'");
			$db->add_column("usergroups", "profilepicmaxsize", "int NOT NULL default '40'");
			$db->add_column("usergroups", "profilepicmaxdimensions", "varchar(10) NOT NULL default '200x200'");
			break;
		case "sqlite":
			$db->add_column("users", "profilepic", "varchar(200) NOT NULL default ''");
			$db->add_column("users", "profilepicdimensions", "varchar(10) NOT NULL default ''");
			$db->add_column("users", "profilepictype", "varchar(10) NOT NULL default ''");
			$db->add_column("users", "profilepicdescription", "varchar(255) NOT NULL default ''");

			$db->add_column("usergroups", "canuseprofilepic", "tinyint(1) NOT NULL default '1'");
			$db->add_column("usergroups", "canuploadprofilepic", "tinyint(1) NOT NULL default '1'");
			$db->add_column("usergroups", "profilepicmaxsize", "int NOT NULL default '40'");
			$db->add_column("usergroups", "profilepicmaxdimensions", "varchar(10) NOT NULL default '200x200'");
			break;
		default:
			$db->add_column("users", "profilepic", "varchar(200) NOT NULL default ''");
			$db->add_column("users", "profilepicdimensions", "varchar(10) NOT NULL default ''");
			$db->add_column("users", "profilepictype", "varchar(10) NOT NULL default ''");
			$db->add_column("users", "profilepicdescription", "varchar(255) NOT NULL default ''");

			$db->add_column("usergroups", "canuseprofilepic", "tinyint(1) NOT NULL default '1'");
			$db->add_column("usergroups", "canuploadprofilepic", "tinyint(1) NOT NULL default '1'");
			$db->add_column("usergroups", "profilepicmaxsize", "int unsigned NOT NULL default '40'");
			$db->add_column("usergroups", "profilepicmaxdimensions", "varchar(10) NOT NULL default '200x200'");
			break;
	}

	$cache->update_usergroups();
}

// Checks to make sure plugin is installed
function profilepic_is_installed()
{
	global $db;
	if($db->field_exists("profilepic", "users"))
	{
		return true;
	}
	return false;
}

// This function runs when the plugin is uninstalled.
function profilepic_uninstall()
{
	global $db, $cache;

	if($db->field_exists("profilepic", "users"))
	{
		$db->drop_column("users", "profilepic");
	}

	if($db->field_exists("profilepicdimensions", "users"))
	{
		$db->drop_column("users", "profilepicdimensions");
	}

	if($db->field_exists("profilepictype", "users"))
	{
		$db->drop_column("users", "profilepictype");
	}

	if($db->field_exists("profilepicdescription", "users"))
	{
		$db->drop_column("users", "profilepicdescription");
	}

	if($db->field_exists("canuseprofilepic", "usergroups"))
	{
		$db->drop_column("usergroups", "canuseprofilepic");
	}

	if($db->field_exists("canuploadprofilepic", "usergroups"))
	{
		$db->drop_column("usergroups", "canuploadprofilepic");
	}

	if($db->field_exists("profilepicmaxsize", "usergroups"))
	{
		$db->drop_column("usergroups", "profilepicmaxsize");
	}

	if($db->field_exists("profilepicmaxdimensions", "usergroups"))
	{
		$db->drop_column("usergroups", "profilepicmaxdimensions");
	}

	$cache->update_usergroups();
}

// This function runs when the plugin is activated.
function profilepic_activate()
{
	global $db;

	// Insert settings
	$insertarray = array(
		'name' => 'profilepicture',
		'title' => 'Profile Picture Settings',
		'description' => 'Various option related to profile pictures can be managed and set here.',
		'disporder' => 42,
		'isdefault' => 0,
	);
	$gid = $db->insert_query("settinggroups", $insertarray);

	$insertarray = array(
		'name' => 'profilepictureuploadpath',
		'title' => 'Profile Picture Upload Path',
		'description' => 'This is the path where profile pictures will be uploaded to. This directory <strong>must be chmod 777</strong> (writable) for uploads to work.',
		'optionscode' => 'text',
		'value' => './uploads/profilepics',
		'disporder' => 1,
		'gid' => (int)$gid
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'profilepictureresizing',
		'title' => 'Profile Picture Resizing Mode',
		'description' => 'If you wish to automatically resize all large profile pictures, provide users the option of resizing their profile picture, or not resize profile pictures at all you can change this setting.',
		'optionscode' => 'select
auto=Automatically resize large profile pictures
user=Give users the choice of resizing large profile pictures
disabled=Disable this feature',
		'value' => 'auto',
		'disporder' => 2,
		'gid' => (int)$gid
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'profilepicturedescription',
		'title' => 'Profile Picture Description',
		'description' => 'If you wish allow your users to enter an optional description for their profile picture, set this option to yes.',
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 3,
		'gid' => (int)$gid
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'userprofilepicturerating',
		'title' => 'Gravatar Rating',
		'description' => 'Allows you to set the maximum rating for Gravatars if a user chooses to use one. If a user profile picture is higher than this rating no profile picture will be used. The ratings are:
<ul>
<li><strong>G</strong>: suitable for display on all websites with any audience type</li>
<li><strong>PG</strong>: may contain rude gestures, provocatively dressed individuals, the lesser swear words or mild violence</li>
<li><strong>R</strong>: may contain such things as harsh profanity, intense violence, nudity or hard drug use</li>
<li><strong>X</strong>: may contain hardcore sexual imagery or extremely disturbing violence</li>
</ul>',
		'optionscode' => 'select
g=G
pg=PG
r=R
x=X',
		'value' => 'g',
		'disporder' => 4,
		'gid' => (int)$gid
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'allowremoteprofilepictures',
		'title' => 'Allow Remote Profile Pictures',
		'description' => $db->escape_string('Whether to allow the usage of profile pictures from remote servers. Having this enabled can expose your server\'s IP address.'),
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 5,
		'gid' => (int)$gid
	);
	$db->insert_query("settings", $insertarray);

	rebuild_settings();

	// Insert templates
	$insert_array = array(
		'title'		=> 'usercp_profilepicture',
		'template'	=> $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->change_profile_picture}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
	{$usercpnav}
	<td valign="top">
		{$profilepicture_error}
		<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
			<tr>
				<td class="thead" colspan="2"><strong>{$lang->change_profile_picture}</strong></td>
			</tr>
			<tr>
				<td class="trow1" colspan="2">
					<table cellspacing="0" cellpadding="0" width="100%">
						<tr>
							<td>{$lang->profile_picture_note}{$profilepicturemsg}
							{$currentprofilepicture}
							</td>
						</tr>
					</table>
				</td>
			</tr>
			<tr>
				<td class="tcat" colspan="2"><strong>{$lang->custom_profile_picture}</strong></td>
			</tr>
			<form enctype="multipart/form-data" action="usercp.php" method="post">
			<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
			{$profilepictureupload}
			{$profilepicture_remote}
			{$profilepicturedescription}
		</table>
		<br />
		<div align="center">
			<input type="hidden" name="action" value="do_profilepicture" />
			<input type="submit" class="button" name="submit" value="{$lang->change_picture}" />
			{$removeprofilepicture}
		</div>
	</td>
</tr>
</table>
</form>
{$footer}
</body>
</html>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'usercp_profilepicture_auto_resize_auto',
		'template'	=> $db->escape_string('<br /><span class="smalltext">{$lang->profile_picture_auto_resize_note}</span>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'usercp_profilepicture_auto_resize_user',
		'template'	=> $db->escape_string('<br /><span class="smalltext"><input type="checkbox" name="auto_resize" value="1" checked="checked" id="auto_resize" /> <label for="auto_resize">{$lang->profile_picture_auto_resize_option}</label></span>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'usercp_profilepicture_current',
		'template'	=> $db->escape_string('<td width="150" align="right"><img src="{$userprofilepicture[\'image\']}" alt="{$lang->profile_picture_mine}" title="{$lang->profile_picture_mine}" {$userprofilepicture[\'width_height\']} /></td>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'usercp_profilepicture_description',
		'template'	=> $db->escape_string('<tr>
	<td class="trow1" width="40%">
		<strong>{$lang->profile_picture_description}</strong>
		<br /><span class="smalltext">{$lang->profile_picture_description_note}</span>
	</td>
	<td class="trow1" width="60%">
		<textarea name="profilepicturedescription" id="profilepicturedescription" rows="4" cols="80" maxlength="255">{$description}</textarea>
	</td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'usercp_profilepicture_remote',
		'template'	=> $db->escape_string('<tr>
	<td class="trow2" width="40%">
		<strong>{$lang->profile_picture_url}</strong>
		<br /><span class="smalltext">{$lang->profile_picture_url_note}</span>
	</td>
	<td class="trow2" width="60%">
		<input type="text" class="textbox" name="profilepictureurl" size="45" value="{$profilepictureurl}" />
		<br /><span class="smalltext">{$lang->profile_picture_url_gravatar}</span>
	</td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'usercp_profilepicture_remove',
		'template'	=> $db->escape_string('<input type="submit" class="button" name="remove" value="{$lang->remove_picture}" />'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'usercp_profilepicture_upload',
		'template'	=> $db->escape_string('<tr>
	<td class="trow1" width="40%">
		<strong>{$lang->profile_picture_upload}</strong>
		<br /><span class="smalltext">{$lang->profile_picture_upload_note}</span>
	</td>
	<td class="trow1" width="60%">
		<input type="file" name="profilepictureupload" size="25" class="fileupload" />
		{$auto_resize}
	</td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'member_profile_profilepicture',
		'template'	=> $db->escape_string('<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
	<tr>
		<td class="thead"><strong>{$lang->users_profile_picture}</strong></td>
	</tr>
	<tr>
		<td class="trow1" align="center">{$profilepicture_img}<br />
		{$description}</td>
	</tr>
</table>
<br />'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'member_profile_profilepicture_description',
		'template'	=> $db->escape_string('<span class="smalltext"><em>{$memprofile[\'profilepicdescription\']}</em></span>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'member_profile_profilepicture_profilepicture',
		'template'	=> $db->escape_string('<img src="{$userprofilepicture[\'image\']}" alt="" {$userprofilepicture[\'width_height\']} />'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'usercp_nav_profilepicture',
		'template'	=> $db->escape_string('<div><a href="usercp.php?action=profilepicture" class="usercp_nav_item" style="padding-left:40px; background:url(\'images/profilepicture.png\') no-repeat left center;">{$lang->ucp_nav_change_profile_picture}</a></div>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'modcp_editprofile_profilepicture',
		'template'	=> $db->escape_string('<tr><td colspan="3"><span class="smalltext"><label><input type="checkbox" class="checkbox" name="remove_profilepicture" value="1" /> {$lang->remove_profile_picture}</label></span></td></tr>{$profilepicturedescription}'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'modcp_editprofile_profilepicture_description',
		'template'	=> $db->escape_string('<tr>
	<td colspan="3"><span class="smalltext">{$lang->profile_picture_description}</span></td>
</tr>
<tr>
	<td colspan="3"><textarea name="profilepicturedescription" id="profilepicturedescription" rows="4" cols="40" maxlength="255">{$user[\'profilepicdescription\']}</textarea></td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'global_remote_profile_picture_notice',
		'template'	=> $db->escape_string('<div class="red_alert"><a href="{$mybb->settings[\'bburl\']}/usercp.php?action=profilepicture">{$lang->remote_profile_picture_disabled}</a></div>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("header", "#".preg_quote('{$bannedwarning}')."#i", '{$remote_profile_picture_notice}{$bannedwarning}');
	find_replace_templatesets("usercp_nav_profile", "#".preg_quote('{$changesigop}')."#i", '{$changesigop}<!-- profilepicture -->');
	find_replace_templatesets("member_profile", "#".preg_quote('{$profilefields}')."#i", '{$profilefields}{$profilepicture}');
	find_replace_templatesets("modcp_editprofile", "#".preg_quote('{$lang->remove_avatar}</label></span></td>
										</tr>')."#i", '{$lang->remove_avatar}</label></span></td>
										</tr>{$profilepicture}');
}

// This function runs when the plugin is deactivated.
function profilepic_deactivate()
{
	global $db;
	$db->delete_query("settings", "name IN('profilepictureuploadpath','profilepictureresizing','profilepicturedescription','userprofilepicturerating','allowremoteprofilepictures')");
	$db->delete_query("settinggroups", "name IN('profilepicture')");
	$db->delete_query("templates", "title IN('usercp_profilepicture','usercp_profilepicture_auto_resize_auto','usercp_profilepicture_auto_resize_user','usercp_profilepicture_current','member_profile_profilepicture','member_profile_profilepicture_description','member_profile_profilepicture_profilepicture','usercp_profilepicture_description','usercp_profilepicture_remote','usercp_profilepicture_remove','usercp_profilepicture_upload','usercp_nav_profilepicture','modcp_editprofile_profilepicture','modcp_editprofile_profilepicture_description','global_remote_profile_picture_notice')");
	rebuild_settings();

	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("header", "#".preg_quote('{$remote_profile_picture_notice}')."#i", '', 0);
	find_replace_templatesets("member_profile", "#".preg_quote('{$profilepicture}')."#i", '', 0);
	find_replace_templatesets("usercp_nav_profile", "#".preg_quote('<!-- profilepicture -->')."#i", '', 0);
	find_replace_templatesets("modcp_editprofile", "#".preg_quote('{$profilepicture}')."#i", '', 0);
}

// User CP Nav link
function profilepic_nav()
{
	global $mybb, $lang, $templates, $usercpnav;
	$lang->load("profilepicture");

	if($mybb->usergroup['canuseprofilepic'] == 1)
	{
		eval("\$profilepicture_nav = \"".$templates->get("usercp_nav_profilepicture")."\";");
		$usercpnav = str_replace("<!-- profilepicture -->", $profilepicture_nav, $usercpnav);
	}
}

// Cache the profile picture remote warning template
function profilepic_header_cache()
{
	global $templatelist;

	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'global_remote_profile_picture_notice';
}

// Profile picture remote warning
function profilepic_header()
{
	global $mybb, $templates, $lang, $remote_profile_picture_notice;
	$lang->load("profilepicture");

	$remote_profile_picture_notice = '';
	if(($mybb->user['profilepictype'] === 'remote' || $mybb->user['profilepictype'] === 'gravatar') && !$mybb->settings['allowremoteprofilepictures'])
	{
		eval('$remote_profile_picture_notice = "'.$templates->get('global_remote_profile_picture_notice').'";');
	}
}

// The UserCP profile picture page
function profilepic_run()
{
	global $db, $mybb, $lang, $templates, $theme, $headerinclude, $usercpnav, $header, $footer;
	$lang->load("profilepicture");
	require_once MYBB_ROOT."inc/functions_profilepicture.php";

	if($mybb->input['action'] == "do_profilepicture" && $mybb->request_method == "post")
	{
		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		if($mybb->usergroup['canuseprofilepic'] == 0)
		{
			error_no_permission();
		}

		$profilepicture_error = "";

		if(!empty($mybb->input['remove'])) // remove profile picture
		{
			$updated_profilepicture = array(
				"profilepic" => "",
				"profilepicdimensions" => "",
				"profilepictype" => "",
				"profilepicdescription" => ""
			);
			$db->update_query("users", $updated_profilepicture, "uid='{$mybb->user['uid']}'");
			remove_profilepicture($mybb->user['uid']);
		}
		elseif($_FILES['profilepictureupload']['name']) // upload profile picture
		{
			if($mybb->usergroup['canuploadprofilepic'] == 0)
			{
				error_no_permission();
			}

			// See if profile picture description is too long
			if(my_strlen($mybb->input['profilepicturedescription']) > 255)
			{
				$profilepicture_error = $lang->error_descriptiontoobig;
			}

			$profilepicture = upload_profilepicture();
			if(!empty($profilepicture['error']))
			{
				$profilepicture_error = $profilepicture['error'];
			}
			else
			{
				if($profilepicture['width'] > 0 && $profilepicture['height'] > 0)
				{
					$profilepicture_dimensions = $profilepicture['width']."|".$profilepicture['height'];
				}
				$updated_profilepicture = array(
					"profilepic" => $profilepicture['profilepicture'].'?dateline='.TIME_NOW,
					"profilepicdimensions" => $profilepicture_dimensions,
					"profilepictype" => "upload",
					"profilepicdescription" => $db->escape_string($mybb->input['profilepicturedescription'])
				);
				$db->update_query("users", $updated_profilepicture, "uid='{$mybb->user['uid']}'");
			}
		}
		elseif($mybb->input['profilepictureurl'] && $mybb->settings['allowremoteprofilepictures']) // remote profile picture
		{
			$mybb->input['profilepictureurl'] = trim($mybb->get_input('profilepictureurl'));
			if(validate_email_format($mybb->input['profilepictureurl']) != false)
			{
				// Gravatar
				$mybb->input['profilepictureurl'] = my_strtolower($mybb->input['profilepictureurl']);

				// If user image does not exist, or is a higher rating, use the mystery man
				$email = md5($mybb->input['profilepictureurl']);

				$s = '';
				if(!$mybb->usergroup['profilepicmaxdimensions'])
				{
					$mybb->usergroup['profilepicmaxdimensions'] = '200x200'; // Hard limit of 200 if there are no limits
				}

				// Because Gravatars are square, hijack the width
				list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->usergroup['profilepicmaxdimensions']));
				$maxheight = (int)$maxwidth;

				// Rating?
				$types = array('g', 'pg', 'r', 'x');
				$rating = $mybb->settings['userprofilepicturerating'];

				if(!in_array($rating, $types))
				{
					$rating = 'g';
				}

				$s = "?s={$maxheight}&r={$rating}&d=mm";

				// See if profile picture description is too long
				if(my_strlen($mybb->input['profilepicturedescription']) > 255)
				{
					$profilepicture_error = $lang->error_descriptiontoobig;
				}

				$updated_profilepicture = array(
					"profilepic" => "https://www.gravatar.com/avatar/{$email}{$s}",
					"profilepicdimensions" => "{$maxheight}|{$maxheight}",
					"profilepictype" => "gravatar",
					"profilepicdescription" => $db->escape_string($mybb->input['profilepicturedescription'])
				);

				$db->update_query("users", $updated_profilepicture, "uid = '{$mybb->user['uid']}'");
			}
			else
			{
				$mybb->input['profilepictureurl'] = preg_replace("#script:#i", "", $mybb->input['profilepictureurl']);
				$ext = get_extension($mybb->input['profilepictureurl']);

				// Copy the profile picture to the local server (work around remote URL access disabled for getimagesize)
				$file = fetch_remote_file($mybb->input['profilepictureurl']);
				if(!$file)
				{
					$profilepicture_error = $lang->error_invalidprofilepictureurl;
				}
				else
				{
					$tmp_name = $mybb->settings['profilepictureuploadpath']."/remote_".md5(random_str());
					$fp = @fopen($tmp_name, "wb");
					if(!$fp)
					{
						$profilepicture_error = $lang->error_invalidprofilepictureurl;
					}
					else
					{
						fwrite($fp, $file);
						fclose($fp);
						list($width, $height, $type) = @getimagesize($tmp_name);
						@unlink($tmp_name);
						if(!$type)
						{
							$profilepicture_error = $lang->error_invalidprofilepictureurl;
						}
					}
				}

				// See if profile picture description is too long
				if(my_strlen($mybb->input['profilepicturedescription']) > 255)
				{
					$profilepicture_error = $lang->error_descriptiontoobig;
				}

				if(empty($profilepicture_error))
				{
					if($width && $height && $mybb->usergroup['profilepicmaxdimensions'] != "")
					{
						list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->usergroup['profilepicmaxdimensions']));
						if(($maxwidth && $width > $maxwidth) || ($maxheight && $height > $maxheight))
						{
							$lang->error_profilepicturetoobig = $lang->sprintf($lang->error_profilepicturetoobig, $maxwidth, $maxheight);
							$profilepicture_error = $lang->error_profilepicturetoobig;
						}
					}
				}

				if(empty($profilepicture_error))
				{
					if($width > 0 && $height > 0)
					{
						$profilepicture_dimensions = (int)$width."|".(int)$height;
					}
					$updated_profilepicture = array(
						"profilepic" => $db->escape_string($mybb->input['profilepictureurl'].'?dateline='.TIME_NOW),
						"profilepicdimensions" => $profilepicture_dimensions,
						"profilepictype" => "remote",
						"profilepicdescription" => $db->escape_string($mybb->input['profilepicturedescription'])
					);
					$db->update_query("users", $updated_profilepicture, "uid='{$mybb->user['uid']}'");
					remove_profilepicture($mybb->user['uid']);
				}
			}
		}
		elseif(isset($mybb->input['profilepicturedescription']) && $mybb->input['profilepicturedescription'] != $mybb->user['profilepicdescription']) // just updating profile picture description
		{
			// See if profile picture description is too long
			if(my_strlen($mybb->input['profilepicturedescription']) > 255)
			{
				$profilepicture_error = $lang->error_descriptiontoobig;
			}

			if(empty($profilepicture_error))
			{
				$updated_profilepicture = array(
					"profilepicdescription" => $db->escape_string($mybb->input['profilepicturedescription'])
				);
				$db->update_query("users", $updated_profilepicture, "uid='{$mybb->user['uid']}'");
			}
		}
		else
		{
			$profilepicture_error = $lang->error_remote_profile_picture_not_allowed;
		}

		if(empty($profilepicture_error))
		{
			redirect("usercp.php?action=profilepicture", $lang->redirect_profile_picture_updated);
		}
		else
		{
			$mybb->input['action'] = "profilepicture";
			$profilepicture_error = inline_error($profilepicture_error);
		}
	}

	if($mybb->input['action'] == "profilepicture")
	{
		add_breadcrumb($lang->nav_usercp, "usercp.php");
		add_breadcrumb($lang->change_profile_picture, "usercp.php?action=profilepicture");

		// Show main profile picture page
		if($mybb->usergroup['canuseprofilepic'] == 0)
		{
			error_no_permission();
		}

		$profilepicturemsg = $profilepictureurl = '';

		if($mybb->user['profilepictype'] == "upload" || stristr($mybb->user['profilepic'], $mybb->settings['profilepictureuploadpath']))
		{
			$profilepicturemsg = "<br /><strong>".$lang->already_uploaded_profile_picture."</strong>";
		}
		elseif($mybb->user['profilepictype'] == "remote" || my_validate_url($mybb->user['profilepic']))
		{
			$profilepicturemsg = "<br /><strong>".$lang->using_remote_profile_picture."</strong>";
			$profilepictureurl = htmlspecialchars_uni($mybb->user['profilepic']);
		}

		$currentprofilepicture = '';
		if(!empty($mybb->user['profilepic']) && ((($mybb->user['profilepictype'] == 'remote' || $mybb->user['profilepictype'] == 'gravatar') && $mybb->settings['allowremoteprofilepictures'] == 1) || $mybb->user['profilepictype'] == "upload"))
		{
			$userprofilepicture = format_profile_picture(htmlspecialchars_uni($mybb->user['profilepic']), $mybb->user['profilepicdimensions'], '200x200');
			eval("\$currentprofilepicture = \"".$templates->get("usercp_profilepicture_current")."\";");
		}

		if($mybb->usergroup['profilepicmaxdimensions'] != "")
		{
			list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->usergroup['profilepicmaxdimensions']));
			$lang->profile_picture_note .= "<br />".$lang->sprintf($lang->profile_picture_note_dimensions, $maxwidth, $maxheight);
		}
		if($mybb->usergroup['profilepicmaxsize'])
		{
			$maxsize = get_friendly_size($mybb->usergroup['profilepicmaxsize']*1024);
			$lang->profile_picture_note .= "<br />".$lang->sprintf($lang->profile_picture_note_size, $maxsize);
		}

		$auto_resize = '';
		if($mybb->settings['profilepictureresizing'] == "auto")
		{
			eval("\$auto_resize = \"".$templates->get("usercp_profilepicture_auto_resize_auto")."\";");
		}
		else if($mybb->settings['profilepictureresizing'] == "user")
		{
			eval("\$auto_resize = \"".$templates->get("usercp_profilepicture_auto_resize_user")."\";");
		}

		$profilepictureupload = '';
		if($mybb->usergroup['canuploadprofilepic'] == 1)
		{
			eval("\$profilepictureupload = \"".$templates->get("usercp_profilepicture_upload")."\";");
		}

		$profilepicture_remote = '';
		if($mybb->settings['allowremoteprofilepictures'] == 1)
		{
			eval("\$profilepicture_remote = \"".$templates->get("usercp_profilepicture_remote")."\";");
		}

		$description = htmlspecialchars_uni($mybb->user['profilepicdescription']);

		$profilepicturedescription = '';
		if($mybb->settings['profilepicturedescription'] == 1)
		{
			eval("\$profilepicturedescription = \"".$templates->get("usercp_profilepicture_description")."\";");
		}

		$removeprofilepicture = '';
		if(!empty($mybb->user['profilepic']))
		{
			eval("\$removeprofilepicture = \"".$templates->get("usercp_profilepicture_remove")."\";");
		}

		if(!isset($profilepicture_error))
		{
			$profilepicture_error = '';
		}

		eval("\$profilepicture = \"".$templates->get("usercp_profilepicture")."\";");
		output_page($profilepicture);
	}
}

// Profile Picture display in profile
function profilepic_profile()
{
	global $mybb, $templates, $lang, $theme, $memprofile, $profilepicture, $parser;
	$lang->load("profilepicture");
	require_once MYBB_ROOT."inc/functions_profilepicture.php";

	$lang->users_profile_picture = $lang->sprintf($lang->users_profile_picture, $memprofile['username']);

	$profilepicture = $profilepicture_img = '';
	if($memprofile['profilepic'] && ((($memprofile['profilepictype'] == 'remote' || $memprofile['profilepictype'] == 'gravatar') && $mybb->settings['allowremoteprofilepictures'] == 1) || $memprofile['profilepictype'] == "upload"))
	{
		$memprofile['profilepic'] = htmlspecialchars_uni($memprofile['profilepic']);
		$userprofilepicture = format_profile_picture($memprofile['profilepic'], $memprofile['profilepicdimensions']);
		eval("\$profilepicture_img = \"".$templates->get("member_profile_profilepicture_profilepicture")."\";");

		$description = '';
		if(!empty($memprofile['profilepicdescription']) && $mybb->settings['profilepicturedescription'] == 1)
		{
			$memprofile['profilepicdescription'] = htmlspecialchars_uni($parser->parse_badwords($memprofile['profilepicdescription']));
			eval("\$description = \"".$templates->get("member_profile_profilepicture_description")."\";");
		}

		eval("\$profilepicture = \"".$templates->get("member_profile_profilepicture")."\";");
	}
}

// Online location support
function profilepic_online_activity($user_activity)
{
	global $user;
	if(my_strpos($user['location'], "usercp.php?action=profilepicture") !== false)
	{
		$user_activity['activity'] = "usercp_profilepicture";
	}

	return $user_activity;
}

function profilepic_online_location($plugin_array)
{
	global $lang;
	$lang->load("profilepicture");

	if($plugin_array['user_activity']['activity'] == "usercp_profilepicture")
	{
		$plugin_array['location_name'] = $lang->changing_profile_picture;
	}

	return $plugin_array;
}

// Mod CP removal function
function profilepic_removal()
{
	global $mybb, $db, $user;
	require_once MYBB_ROOT."inc/functions_profilepicture.php";

	if(!empty($mybb->input['remove_profilepicture']))
	{
		$updated_profilepicture = array(
			"profilepic" => "",
			"profilepicdimensions" => "",
			"profilepictype" => ""
		);
		remove_profilepicture($user['uid']);

		$db->update_query("users", $updated_profilepicture, "uid='{$user['uid']}'");
	}

	// Update description if active
	if($mybb->settings['profilepicturedescription'] == 1)
	{
		$updated_profilepicture = array(
			"profilepicdescription" => $db->escape_string($mybb->input['profilepicturedescription'])
		);
		$db->update_query("users", $updated_profilepicture, "uid='{$user['uid']}'");
	}
}

// Mod CP language
function profilepic_removal_lang()
{
	global $mybb, $lang, $user, $templates, $profilepicture;
	$lang->load("profilepicture");

	$user['profilepicdescription'] = htmlspecialchars_uni($user['profilepicdescription']);

	$profilepicturedescription = '';
	if($mybb->settings['profilepicturedescription'] == 1)
	{
		eval("\$profilepicturedescription = \"".$templates->get("modcp_editprofile_profilepicture_description")."\";");
	}

	eval("\$profilepicture = \"".$templates->get("modcp_editprofile_profilepicture")."\";");
}

// Delete profile picture if user is deleted
function profilepic_user_delete($delete)
{
	global $db;

	// Remove any of the user(s) uploaded profile pictures
	$query = $db->simple_select('users', 'profilepic', "uid IN({$delete->delete_uids}) AND profilepictype='upload'");
	while($profilepicture = $db->fetch_field($query, 'profilepic'))
	{
		$profilepicture = substr($profilepicture, 2, -20);
		@unlink(MYBB_ROOT.$profilepicture);
	}

	return $delete;
}

// Edit user options in Admin CP
function profilepic_user_options($tabs)
{
	global $lang;
	$lang->load("profilepicture", true);

	$tabs['profilepicture'] = $lang->profile_picture;
	return $tabs;
}

function profilepic_user_graph()
{
	global $lang, $form, $mybb, $user, $errors;
	$lang->load("profilepicture", true);

	$profile_picture_dimensions = explode("|", $user['profilepicdimensions']);
	if($user['profilepic'] && (my_strpos($user['profilepic'], '://') === false || $mybb->settings['allowremoteprofilepictures']))
	{
		if($user['profilepicdimensions'])
		{
			require_once MYBB_ROOT."inc/functions_image.php";
			list($width, $height) = explode("|", $user['profilepicdimensions']);
			$scaled_dimensions = scale_image($width, $height, 200, 200);
		}
		else
		{
			$scaled_dimensions = array(
				"width" => 200,
				"height" => 200
			);
		}
		if(!my_validate_url($user['profilepic']))
		{
			$user['profilepic'] = "../{$user['profilepic']}\n";
		}
	}
	else
	{
		$user['profilepic'] = "../".$mybb->settings['useravatar'];
		$scaled_dimensions = array(
			"width" => 200,
			"height" => 200
		);
	}
	$profile_picture_top = ceil((206-$scaled_dimensions['height'])/2);

	echo "<div id=\"tab_profilepicture\">\n";
	$table = new Table;
	$table->construct_header($lang->current_profile_picture, array('colspan' => 2));

	$table->construct_cell("<div style=\"width: 206px; height: 206px;\" class=\"user_avatar\"><img src=\"".htmlspecialchars_uni($user['profilepic'])."\" width=\"{$scaled_dimensions['width']}\" style=\"margin-top: {$profile_picture_top}px\" height=\"{$scaled_dimensions['height']}\" alt=\"\" /></div>", array('width' => 1));

	$profilepicture_url = '';
	if($user['profilepictype'] == "upload" || stristr($user['profilepic'], $mybb->settings['profilepictureuploadpath']))
	{
		$current_profile_picture_msg = "<br /><strong>{$lang->user_current_using_uploaded_profile_picture}</strong>";
	}
	elseif($user['profilepictype'] == "remote" || my_validate_url($user['profilepic']))
	{
		$current_profile_picture_msg = "<br /><strong>{$lang->user_current_using_remote_profile_picture}</strong>";
		$profilepicture_url = $user['profilepic'];
	}

	$profilepicture_description = '';
	if(!empty($user['profilepicdescription']))
	{
		$profilepicture_description = htmlspecialchars_uni($user['profilepicdescription']);
	}

	if($errors)
	{
		$profilepicture_description = htmlspecialchars_uni($mybb->input['profilepicture_description']);
		$profilepicture_url = htmlspecialchars_uni($mybb->input['profilepicture_url']);
	}

	$user_permissions = user_permissions($user['uid']);

	if($user_permissions['profilepicmaxdimensions'] != "")
	{
		list($max_width, $max_height) = explode("x", my_strtolower($user_permissions['profilepicmaxdimensions']));
		$max_size = "<br />{$lang->profile_picture_max_dimensions_are} {$max_width}x{$max_height}";
	}

	if($user_permissions['profilepicmaxsize'])
	{
		$maximum_size = get_friendly_size($user_permissions['profilepicmaxsize']*1024);
		$max_size .= "<br />{$lang->profile_picture_max_size} {$maximum_size}";
	}

	if($user['profilepic'])
	{
		$remove_profilepicture = "<br /><br />".$form->generate_check_box("remove_profilepicture", 1, "<strong>{$lang->remove_profile_picture_admin}</strong>");
	}

	$table->construct_cell($lang->profile_picture_desc."{$remove_profilepicture}<br /><small>{$max_size}</small>");
	$table->construct_row();

	$table->output($lang->profile_picture.": ".htmlspecialchars_uni($user['username']));

	// Custom profile picture
	if($mybb->settings['profilepictureresizing'] == "auto")
	{
		$auto_resize = $lang->profile_picture_auto_resize;
	}
	else if($mybb->settings['profilepictureresizing'] == "user")
	{
		$auto_resize = "<input type=\"checkbox\" name=\"auto_resize\" value=\"1\" checked=\"checked\" id=\"auto_resize\" /> <label for=\"auto_resize\">{$lang->attempt_to_auto_resize_profile_picture}</label></span>";
	}
	$form_container = new FormContainer($lang->specify_custom_profile_picture);
	$form_container->output_row($lang->upload_profile_picture, $auto_resize, $form->generate_file_upload_box('profilepicture_upload', array('id' => 'profilepicture_upload')), 'profilepicture_upload');
	if($mybb->settings['allowremoteprofilepictures'])
	{
		$form_container->output_row($lang->or_specify_profile_picture_url, "", $form->generate_text_box('profilepicture_url', $profilepicture_url, array('id' => 'profilepicture_url')), 'profilepicture_url');
	}
	$form_container->output_row($lang->profile_picture_description, "", $form->generate_text_area('profilepicture_description', $profilepicture_description, array('id' => 'profilepicture_description', 'maxlength' => '255')), 'profilepicture_description');
	$form_container->end();
	echo "</div>\n";
}

function profilepic_user_commit()
{
	global $db, $extra_user_updates, $mybb, $errors, $user;

	require_once MYBB_ROOT."inc/functions_profilepicture.php";
	$user_permissions = user_permissions($user['uid']);

	// Are we removing a profile picture from this user?
	if($mybb->input['remove_profilepicture'])
	{
		$extra_user_updates = array(
			"profilepic" => "",
			"profilepicdimensions" => "",
			"profilepictype" => ""
		);
		remove_profilepicture($user['uid']);
	}

	// Are we uploading a new profile picture?
	if($_FILES['profilepicture_upload']['name'])
	{
		$profilepicture = upload_profilepicture($_FILES['profilepicture_upload'], $user['uid']);
		if($profilepicture['error'])
		{
			$errors = array($profilepicture['error']);
		}
		else
		{
			if($profilepicture['width'] > 0 && $profilepicture['height'] > 0)
			{
				$profilepicture_dimensions = $profilepicture['width']."|".$profilepicture['height'];
			}
			$extra_user_updates = array(
				"profilepic" => $profilepicture['profilepicture'].'?dateline='.TIME_NOW,
				"profilepicdimensions" => $profilepicture_dimensions,
				"profilepictype" => "upload"
			);
		}
	}
	// Are we setting a new profile picture from a URL?
	else if($mybb->input['profilepicture_url'] && $mybb->input['profilepicture_url'] != $user['profilepic'])
	{
		if(!$mybb->settings['allowremoteprofilepictures'])
		{
			$errors = array($lang->error_remote_profile_picture_not_allowed);
		}
		else
		{
			if(filter_var($mybb->input['profilepicture_url'], FILTER_VALIDATE_EMAIL) !== false)
			{
				// Gravatar
				$email = md5(strtolower(trim($mybb->input['profilepicture_url'])));

				$s = '';
				if(!$user_permissions['profilepicmaxdimensions'])
				{
					$user_permissions['profilepicmaxdimensions'] = '200x200'; // Hard limit of 200 if there are no limits
				}

				// Because Gravatars are square, hijack the width
				list($maxwidth, $maxheight) = explode("x", my_strtolower($user_permissions['profilepicmaxdimensions']));

				$s = "?s={$maxwidth}";
				$maxheight = (int)$maxwidth;

				$extra_user_updates = array(
					"profilepic" => "https://www.gravatar.com/avatar/{$email}{$s}",
					"profilepicdimensions" => "{$maxheight}|{$maxheight}",
					"profilepictype" => "gravatar"
				);
			}
			else
			{
				$mybb->input['profilepicture_url'] = preg_replace("#script:#i", "", $mybb->input['profilepicture_url']);
				$ext = get_extension($mybb->input['profilepicture_url']);

				// Copy the profile picture to the local server (work around remote URL access disabled for getimagesize)
				$file = fetch_remote_file($mybb->input['profilepicture_url']);
				if(!$file)
				{
					$profilepicture_error = $lang->error_invalidprofilepictureurl;
				}
				else
				{
					$tmp_name = "../".$mybb->settings['profilepictureuploadpath']."/remote_".md5(random_str());
					$fp = @fopen($tmp_name, "wb");
					if(!$fp)
					{
						$profilepicture_error = $lang->error_invalidprofilepictureurl;
					}
					else
					{
						fwrite($fp, $file);
						fclose($fp);
						list($width, $height, $type) = @getimagesize($tmp_name);
						@unlink($tmp_name);
						echo $type;
						if(!$type)
						{
							$profilepicture_error = $lang->error_invalidprofilepictureurl;
						}
					}
				}

				if(empty($profilepicture_error))
				{
					if($width && $height && $user_permissions['profilepicmaxdimensions'] != "")
					{
						list($maxwidth, $maxheight) = explode("x", my_strtolower($user_permissions['profilepicmaxdimensions']));
						if(($maxwidth && $width > $maxwidth) || ($maxheight && $height > $maxheight))
						{
							$lang->error_profilepicturetoobig = $lang->sprintf($lang->error_profilepicturetoobig, $maxwidth, $maxheight);
							$profilepicture_error = $lang->error_profilepicturetoobig;
						}
					}
				}

				if(empty($profilepicture_error))
				{
					if($width > 0 && $height > 0)
					{
						$profilepicture_dimensions = (int)$width."|".(int)$height;
					}
					$extra_user_updates = array(
						"profilepic" => $db->escape_string($mybb->input['profilepicture_url'].'?dateline='.TIME_NOW),
						"profilepicdimensions" => $profilepicture_dimensions,
						"profilepictype" => "remote"
					);
					remove_profilepicture($user['uid']);
				}
				else
				{
					$errors = array($profilepicture_error);
				}
			}
		}
	}

	if($mybb->input['profilepicture_description'] != $user['profilepicdescription'])
	{
		$profilepicture_description = my_substr($mybb->input['profilepicture_description'], 0, 255);

		$extra_user_updates = array(
			"profilepicdescription" => $db->escape_string($profilepicture_description)
		);
	}
}

// Admin CP permission control
function profilepic_usergroup_permission()
{
	global $mybb, $lang, $form, $form_container, $run_module, $page;
	$lang->load("profilepicture", true);

	if($run_module == 'user' && $page->active_action == 'groups' && !empty($form_container->_title) & !empty($lang->misc) & $form_container->_title == $lang->misc)
	{
		$profilepicture_options = array(
			$form->generate_check_box('canuseprofilepic', 1, $lang->can_use_profile_picture, array("checked" => $mybb->input['canuseprofilepic'])),
			$form->generate_check_box('canuploadprofilepic', 1, $lang->can_upload_profile_picture, array("checked" => $mybb->input['canuploadprofilepic'])),
			"{$lang->profile_picture_size}<br /><small>{$lang->profile_picture_size_desc}</small><br />".$form->generate_numeric_field('profilepicmaxsize', $mybb->input['profilepicmaxsize'], array('id' => 'profilepicmaxsize', 'class' => 'field50', 'min' => 0)). "KB",
			"{$lang->profile_picture_dims}<br /><small>{$lang->profile_picture_dims_desc}</small><br />".$form->generate_text_box('profilepicmaxdimensions', $mybb->input['profilepicmaxdimensions'], array('id' => 'profilepicmaxdimensions', 'class' => 'field'))
		);
		$form_container->output_row($lang->profile_picture, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $profilepicture_options)."</div>");
	}
}

function profilepic_usergroup_permission_commit()
{
	global $db, $mybb, $updated_group;
	$updated_group['canuseprofilepic'] = $mybb->get_input('canuseprofilepic', MyBB::INPUT_INT);
	$updated_group['canuploadprofilepic'] = $mybb->get_input('canuploadprofilepic', MyBB::INPUT_INT);
	$updated_group['profilepicmaxsize'] = $mybb->get_input('profilepicmaxsize', MyBB::INPUT_INT);
	$updated_group['profilepicmaxdimensions'] = $db->escape_string($mybb->input['profilepicmaxdimensions']);
}

// Check to see if CHMOD for profile pictures is writable
function profilepic_chmod()
{
	global $mybb, $lang, $table;
	$lang->load("profilepicture", true);

	if(is_writable('../'.$mybb->settings['profilepictureuploadpath']))
	{
		$message_profile_picture = "<span style=\"color: green;\">{$lang->writable}</span>";
	}
	else
	{
		$message_profile_picture = "<strong><span style=\"color: #C00\">{$lang->not_writable}</span></strong><br />{$lang->please_chmod_777}";
		++$errors;
	}

	$table->construct_cell("<strong>{$lang->profile_picture_upload_dir}</strong>");
	$table->construct_cell($mybb->settings['profilepictureuploadpath']);
	$table->construct_cell($message_profile_picture);
	$table->construct_row();
}
