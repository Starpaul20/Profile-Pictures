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
if(my_strpos($_SERVER['PHP_SELF'], 'usercp.php'))
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'usercp_profilepic,usercp_profilepic_current,usercp_profilepic_description,usercp_profilepic_upload,usercp_nav_profilepic';
}

if(my_strpos($_SERVER['PHP_SELF'], 'private.php'))
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'usercp_nav_profilepic';
}

if(my_strpos($_SERVER['PHP_SELF'], 'member.php'))
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'member_profile_profilepic,member_profile_profilepic_description';
}

if(my_strpos($_SERVER['PHP_SELF'], 'modcp.php'))
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'modcp_editprofile_profilepic,modcp_editprofile_profilepic_description';
}

// Tell MyBB when to run the hooks
$plugins->add_hook("usercp_start", "profilepic_run");
$plugins->add_hook("usercp_menu_built", "profilepic_nav");
$plugins->add_hook("member_profile_end", "profilepic_profile");
$plugins->add_hook("fetch_wol_activity_end", "profilepic_online_activity");
$plugins->add_hook("build_friendly_wol_location_end", "profilepic_online_location");
$plugins->add_hook("modcp_do_editprofile_start", "profilepic_removal");
$plugins->add_hook("modcp_editprofile_start", "profilepic_removal_lang");

$plugins->add_hook("admin_user_users_delete_commit", "profilepic_user_delete");
$plugins->add_hook("admin_formcontainer_end", "profilepic_usergroup_permission");
$plugins->add_hook("admin_user_groups_edit_commit", "profilepic_usergroup_permission_commit");

// The information that shows up on the plugin manager
function profilepic_info()
{
	return array(
		"name"				=> "Profile Picture",
		"description"		=> "Allows users to upload a picture to display in their profile.",
		"website"			=> "http://galaxiesrealm.com/index.php",
		"author"			=> "Starpaul20",
		"authorsite"		=> "http://galaxiesrealm.com/index.php",
		"version"			=> "1.1.1",
		"guid"				=> "6933b5e447616dcf957439a9945b24a8",
		"compatibility"		=> "16*"
	);
}

// This function runs when the plugin is installed.
function profilepic_install()
{
	global $db, $cache;
	profilepic_uninstall();

	$db->add_column("users", "profilepic", "varchar(200) NOT NULL default ''");
	$db->add_column("users", "profilepicdimensions", "varchar(10) NOT NULL default ''");
	$db->add_column("users", "profilepictype", "varchar(10) NOT NULL default ''");
	$db->add_column("users", "profilepicdescription", "varchar(255) NOT NULL default ''");

	$db->add_column("usergroups", "canuseprofilepic", "int(1) NOT NULL default '1'");
	$db->add_column("usergroups", "canuploadprofilepic", "int(1) NOT NULL default '1'");
	$db->add_column("usergroups", "profilepicmaxsize", "bigint(30) NOT NULL default '40'");
	$db->add_column("usergroups", "profilepicmaxdimensions", "varchar(50) NOT NULL default '200x200'");

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

	// Upgrade support (from 1.1.x to 1.2)
	if(!$db->field_exists("profilepicdescription", "users"))
	{
		$db->add_column("users", "profilepicdescription", "varchar(255) NOT NULL default ''");
	}

	$query = $db->simple_select("settinggroups", "gid", "name='member'");
	$gid = intval($db->fetch_field($query, "gid"));

	$insertarray = array(
		'name' => 'profilepicuploadpath',
		'title' => 'Profile Picture Upload Path',
		'description' => 'This is the path where profile pictures will be uploaded to. This directory <strong>must be chmod 777</strong> (writable) for uploads to work.',
		'optionscode' => 'text',
		'value' => './uploads/profilepics',
		'disporder' => 36,
		'gid' => $gid
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'profilepicresizing',
		'title' => 'Profile Picture Resizing Mode',
		'description' => 'If you wish to automatically resize all large profile pictures, provide users the option of resizing their profile picture, or not resize profile pictures at all you can change this setting.',
		'optionscode' => 'select
auto=Automatically resize large profile pictures
user=Give users the choice of resizing large profile pictures
disabled=Disable this feature',
		'value' => 'auto',
		'disporder' => 37,
		'gid' => $gid
	);
	$db->insert_query("settings", $insertarray);

	$insertarray = array(
		'name' => 'profilepicdescription',
		'title' => 'Profile Picture Description',
		'description' => 'If you wish allow your users to enter an optional description for their profile picture, set this option to yes.',
		'optionscode' => 'yesno',
		'value' => 1,
		'disporder' => 38,
		'gid' => intval($gid)
	);
	$db->insert_query("settings", $insertarray);

	rebuild_settings();

	$insert_array = array(
		'title'		=> 'usercp_profilepic',
		'template'	=> $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->change_profilepicture}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
{$usercpnav}
<td valign="top">
{$profilepic_error}
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="2"><strong>{$lang->change_profilepicture}</strong></td>
</tr>
<tr>
<td class="trow1" colspan="2">
<table cellspacing="0" cellpadding="0" width="100%"><tr>
<td>{$lang->profilepic_note}{$profilepicmsg}
</td>
{$currentprofilepic}
</tr></table>
</td>
</tr>
<tr>
<td class="tcat" colspan="2"><strong>{$lang->custom_profile_pic}</strong></td>
</tr>
<form enctype="multipart/form-data" action="usercp.php" method="post">
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
{$profilepicupload}
<tr>
<td class="trow2" width="40%"><strong>{$lang->profilepic_url}</strong></td>
<td class="trow2" width="60%"><input type="text" class="textbox" name="profilepicurl" size="45" value="{$profilepicurl}" /></td>
</tr>
{$profilepicdescription}
</table>
<br />
<div align="center">
<input type="hidden" name="action" value="do_profilepic" />
<input type="submit" class="button" name="submit" value="{$lang->change_picture}" />
<input type="submit" class="button" name="remove" value="{$lang->remove_picture}" />
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
		'title'		=> 'usercp_profilepic_current',
		'template'	=> $db->escape_string('<td class="trow1" width="150" align="right"><img src="{$urltoprofilepic}" alt="{$lang->profile_picture}" title="{$lang->profile_picture}" {$profilepic_width_height} /></td>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'member_profile_profilepic',
		'template'	=> $db->escape_string('<br />
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead"><strong>{$lang->users_profilepic}</strong></td>
</tr>
<tr>
<td class="trow1" align="center">{$profilepic_img}<br />
{$description}</td>
</tr>
</table>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'member_profile_profilepic_description',
		'template'	=> $db->escape_string('<span class="smalltext"><em>{$memprofile[\'profilepicdescription\']}</em></span>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'usercp_profilepic_description',
		'template'	=> $db->escape_string('<tr>
<td class="trow1" width="40%"><strong>{$lang->profilepic_description}</strong></td>
<td class="trow1" width="60%"><input type="text" class="textbox" name="profilepicdescription" size="100" value="{$description}" /></td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'usercp_profilepic_upload',
		'template'	=> $db->escape_string('<tr>
<td class="trow1" width="40%"><strong>{$lang->profilepic_upload}</strong></td>
<td class="trow1" width="60%">
<input type="file" name="profilepicupload" size="25" class="fileupload" />
{$auto_resize}
</td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'usercp_nav_profilepic',
		'template'	=> $db->escape_string('<div><a href="usercp.php?action=profilepic" class="usercp_nav_item" style="padding-left:40px; background:url(\'images/usercp/profilepic.gif\') no-repeat left center;">{$lang->ucp_nav_change_profilepic}</a></div>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'modcp_editprofile_profilepic',
		'template'	=> $db->escape_string('<tr><td colspan="3"><span class="smalltext"><label><input type="checkbox" class="checkbox" name="remove_profilepic" value="1" /> {$lang->remove_profile_picture}</label></span></td></tr>{$profilepicdescription}'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'modcp_editprofile_profilepic_description',
		'template'	=> $db->escape_string('<tr>
<td colspan="3"><span class="smalltext">{$lang->profilepic_description}</span></td>
</tr>
<tr>
<td colspan="3"><textarea name="profilepicdescription" id="profilepicdescription" rows="4" cols="30">{$user[\'profilepicdescription\']}</textarea></td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("usercp_nav_profile", "#".preg_quote('{$changesigop}')."#i", '{$changesigop}<!-- profilepic -->');
	find_replace_templatesets("member_profile", "#".preg_quote('{$profilefields}')."#i", '{$profilefields}{$profilepic}');
	find_replace_templatesets("modcp_editprofile", "#".preg_quote('{$lang->remove_avatar}</label></span></td>
										</tr>')."#i", '{$lang->remove_avatar}</label></span></td>
										</tr>{$profilepic}');
}

// This function runs when the plugin is deactivated.
function profilepic_deactivate()
{
	global $db;
	$db->delete_query("settings", "name IN('profilepicuploadpath','profilepicresizing','profilepicdescription')");
	$db->delete_query("templates", "title IN('usercp_profilepic','usercp_profilepic_current','member_profile_profilepic','member_profile_profilepic_description','usercp_profilepic_description','usercp_profilepic_upload','usercp_nav_profilepic','modcp_editprofile_profilepic','modcp_editprofile_profilepic_description')");
	rebuild_settings();

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("member_profile", "#".preg_quote('{$profilepic}')."#i", '', 0);
	find_replace_templatesets("usercp_nav_profile", "#".preg_quote('<!-- profilepic -->')."#i", '', 0);
	find_replace_templatesets("modcp_editprofile", "#".preg_quote('{$profilepic}')."#i", '', 0);
}

// User CP Nav link
function profilepic_nav()
{
	global $db, $mybb, $lang, $templates, $usercpnav;
	$lang->load("profilepic");

	if($mybb->usergroup['canuseprofilepic'] == 1)
	{
		eval("\$profilepic_nav = \"".$templates->get("usercp_nav_profilepic")."\";");
		$usercpnav = str_replace("<!-- profilepic -->", $profilepic_nav, $usercpnav);
	}
}

// The UserCP profile picture page
function profilepic_run()
{
	global $db, $mybb, $lang, $templates, $theme, $headerinclude, $usercpnav, $header, $profilepic, $footer;
	$lang->load("profilepic");

	if($mybb->input['action'] == "do_profilepic" && $mybb->request_method == "post")
	{
		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if($mybb->usergroup['canuseprofilepic'] == 0)
		{
			error_no_permission();
		}

		require_once MYBB_ROOT."inc/functions_profilepic.php";
		$profilepic_error = "";

		if($mybb->input['remove']) // remove profile picture
		{
			$updated_profilepic = array(
				"profilepic" => "",
				"profilepicdimensions" => "",
				"profilepictype" => "",
				"profilepicdescription" => ""
			);
			$db->update_query("users", $updated_profilepic, "uid='{$mybb->user['uid']}'");
			remove_profilepic($mybb->user['uid']);
		}
		elseif($_FILES['profilepicupload']['name']) // upload profile picture
		{
			if($mybb->usergroup['canuploadprofilepic'] == 0)
			{
				error_no_permission();
			}

			// See if profile picture description is too long
			if(my_strlen($mybb->input['profilepicdescription']) > 255)
			{
				$profilepic_error = $lang->error_descriptiontoobig;
			}

			$profilepic = upload_profilepic();
			if($profilepic['error'])
			{
				$profilepic_error = $profilepic['error'];
			}
			else
			{
				if($profilepic['width'] > 0 && $profilepic['height'] > 0)
				{
					$profilepic_dimensions = $profilepic['width']."|".$profilepic['height'];
				}
				$updated_profilepic = array(
					"profilepic" => $profilepic['profilepic'].'?dateline='.TIME_NOW,
					"profilepicdimensions" => $profilepic_dimensions,
					"profilepictype" => "upload",
					"profilepicdescription" => $db->escape_string($mybb->input['profilepicdescription'])
				);
				$db->update_query("users", $updated_profilepic, "uid='{$mybb->user['uid']}'");
			}
		}
		elseif($mybb->input['profilepicurl']) // remote profile picture
		{
			$mybb->input['profilepicurl'] = preg_replace("#script:#i", "", $mybb->input['profilepicurl']);
			$ext = get_extension($mybb->input['profilepicurl']);

			// Copy the profile picture to the local server (work around remote URL access disabled for getimagesize)
			$file = fetch_remote_file($mybb->input['profilepicurl']);
			if(!$file)
			{
				$profilepic_error = $lang->error_invalidprofilepicurl;
			}
			else
			{
				$tmp_name = $mybb->settings['profilepicuploadpath']."/remote_".md5(uniqid(rand(), true));
				$fp = @fopen($tmp_name, "wb");
				if(!$fp)
				{
					$profilepic_error = $lang->error_invalidprofilepicurl;
				}
				else
				{
					fwrite($fp, $file);
					fclose($fp);
					list($width, $height, $type) = @getimagesize($tmp_name);
					@unlink($tmp_name);
					if(!$type)
					{
						$profilepic_error = $lang->error_invalidprofilepicurl;
					}
				}
			}

			// See if profile picture description is too long
			if(my_strlen($mybb->input['profilepicdescription']) > 255)
			{
				$profilepic_error = $lang->error_descriptiontoobig;
			}

			if(empty($profilepic_error))
			{
				if($width && $height && $mybb->usergroup['profilepicmaxdimensions'] != "")
				{
					list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->usergroup['profilepicmaxdimensions']));
					if(($maxwidth && $width > $maxwidth) || ($maxheight && $height > $maxheight))
					{
						$lang->error_profilepictoobig = $lang->sprintf($lang->error_profilepictoobig, $maxwidth, $maxheight);
						$profilepic_error = $lang->error_profilepictoobig;
					}
				}
			}

			if(empty($profilepic_error))
			{
				if($width > 0 && $height > 0)
				{
					$profilepic_dimensions = intval($width)."|".intval($height);
				}
				$updated_profilepic = array(
					"profilepic" => $db->escape_string($mybb->input['profilepicurl'].'?dateline='.TIME_NOW),
					"profilepicdimensions" => $profilepic_dimensions,
					"profilepictype" => "remote",
					"profilepicdescription" => $db->escape_string($mybb->input['profilepicdescription'])
				);
				$db->update_query("users", $updated_profilepic, "uid='{$mybb->user['uid']}'");
				remove_profilepic($mybb->user['uid']);
			}
		}
		else // just updating profile picture description
		{
			// See if profile picture description is too long
			if(my_strlen($mybb->input['profilepicdescription']) > 255)
			{
				$profilepic_error = $lang->error_descriptiontoobig;
			}

			if(empty($profilepic_error))
			{
				$updated_profilepic = array(
					"profilepicdescription" => $db->escape_string($mybb->input['profilepicdescription'])
				);
				$db->update_query("users", $updated_profilepic, "uid='{$mybb->user['uid']}'");
			}
		}

		if(empty($profilepic_error))
		{
			redirect("usercp.php", $lang->redirect_profilepicupdated);
		}
		else
		{
			$mybb->input['action'] = "profilepic";
			$profilepic_error = inline_error($profilepic_error);
		}
	}

	if($mybb->input['action'] == "profilepic")
	{
		add_breadcrumb($lang->nav_usercp, "usercp.php");
		add_breadcrumb($lang->change_profilepicture, "usercp.php?action=profilepic");

		// Show main profile picture page
		if($mybb->usergroup['canuseprofilepic'] == 0)
		{
			error_no_permission();
		}

		if($mybb->user['profilepictype'] == "upload" || stristr($mybb->user['profilepic'], $mybb->settings['profilepicuploadpath']))
		{
			$profilepicmsg = "<br /><strong>".$lang->already_uploaded_profilepic."</strong>";
		}
		elseif($mybb->user['profilepictype'] == "remote" || my_strpos(my_strtolower($mybb->user['profilepic']), "http://") !== false)
		{
			$profilepicmsg = "<br /><strong>".$lang->using_remote_profilepic."</strong>";
			$profilepicurl = htmlspecialchars_uni($mybb->user['profilepic']);
		}
		$urltoprofilepic = htmlspecialchars_uni($mybb->user['profilepic']);
		if($mybb->user['profilepic'])
		{
			$profilepic_dimensions = explode("|", $mybb->user['profilepicdimensions']);
			if($profilepic_dimensions[0] && $profilepic_dimensions[1])
			{
				$profilepic_width_height = "width=\"{$profilepic_dimensions[0]}\" height=\"{$profilepic_dimensions[1]}\"";
			}
			eval("\$currentprofilepic = \"".$templates->get("usercp_profilepic_current")."\";");
			$colspan = 1;
		}
		else
		{
			$colspan = 2;
		}

		if($mybb->usergroup['profilepicmaxdimensions'] != "")
		{
			list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->usergroup['profilepicmaxdimensions']));
			$lang->profilepic_note .= "<br />".$lang->sprintf($lang->profilepic_note_dimensions, $maxwidth, $maxheight);
		}
		if($mybb->usergroup['profilepicmaxsize'])
		{
			$maxsize = get_friendly_size($mybb->usergroup['profilepicmaxsize']*1024);
			$lang->profilepic_note .= "<br />".$lang->sprintf($lang->profilepic_note_size, $maxsize);
		}
		if($mybb->settings['profilepicresizing'] == "auto")
		{
			$auto_resize = "<br /><span class=\"smalltext\">{$lang->profilepic_auto_resize_note}</span>\n";
		}
		else if($mybb->settings['profilepicresizing'] == "user")
		{
			$auto_resize = "<br /><span class=\"smalltext\"><input type=\"checkbox\" name=\"auto_resize\" value=\"1\" checked=\"checked\" id=\"auto_resize\" /> <label for=\"auto_resize\">{$lang->profilepic_auto_resize_option}</label></span>";
		}
		if($mybb->usergroup['canuploadprofilepic'] == 1)
		{
			eval("\$profilepicupload = \"".$templates->get("usercp_profilepic_upload")."\";");
		}

		$description = htmlspecialchars_uni($mybb->user['profilepicdescription']);

		if($mybb->settings['profilepicdescription'] == 1)
		{
			eval("\$profilepicdescription = \"".$templates->get("usercp_profilepic_description")."\";");
		}

		eval("\$profilepicture = \"".$templates->get("usercp_profilepic")."\";");
		output_page($profilepicture);
	}
}

// Profile Picture display in profile
function profilepic_profile()
{
	global $mybb, $db, $templates, $lang, $theme, $memprofile, $profilepic, $description;
	$lang->load("profilepic");

	$lang->users_profilepic = $lang->sprintf($lang->users_profilepic, $memprofile['username']);

	if($memprofile['profilepic'])
	{
		$memprofile['profilepic'] = htmlspecialchars_uni($memprofile['profilepic']);
		$profilepic_dimensions = explode("|", $memprofile['profilepicdimensions']);
		if($profilepic_dimensions[0] && $profilepic_dimensions[1])
		{
			$profilepic_width_height = "width=\"{$profilepic_dimensions[0]}\" height=\"{$profilepic_dimensions[1]}\"";
		}
		$profilepic_img = "<img src=\"{$memprofile['profilepic']}\" alt=\"\" {$profilepic_width_height} />";

		if($memprofile['profilepicdescription'] && $mybb->settings['profilepicdescription'] == 1)
		{
			$memprofile['profilepicdescription'] = htmlspecialchars_uni($memprofile['profilepicdescription']);
			eval("\$description = \"".$templates->get("member_profile_profilepic_description")."\";");
		}

		eval("\$profilepic = \"".$templates->get("member_profile_profilepic")."\";");
	}
}

// Online location support
function profilepic_online_activity($user_activity)
{
	global $user;
	if(my_strpos($user['location'], "usercp.php?action=profilepic") !== false)
	{
		$user_activity['activity'] = "usercp_profilepic";
	}

	return $user_activity;
}

function profilepic_online_location($plugin_array)
{
    global $db, $mybb, $lang, $parameters;
	$lang->load("profilepic");

	if($plugin_array['user_activity']['activity'] == "usercp_profilepic")
	{
		$plugin_array['location_name'] = $lang->changing_profilepic;
	}

	return $plugin_array;
}

// Mod CP removal function
function profilepic_removal()
{
	global $mybb, $db, $user;
	require_once MYBB_ROOT."inc/functions_profilepic.php";

	if($mybb->input['remove_profilepic'])
	{
		$updated_profilepic = array(
			"profilepic" => "",
			"profilepicdimensions" => "",
			"profilepictype" => ""
		);
		remove_profilepic($user['uid']);

		$db->update_query("users", $updated_profilepic, "uid='{$user['uid']}'");
	}

	// Update description if active
	if($mybb->settings['profilepicdescription'] == 1)
	{
		$updated_profilepic = array(
			"profilepicdescription" => $db->escape_string($mybb->input['profilepicdescription'])
		);
		$db->update_query("users", $updated_profilepic, "uid='{$user['uid']}'");
	}
}

// Mod CP language
function profilepic_removal_lang()
{
	global $mybb, $lang, $user, $templates, $profilepicdescription, $profilepic;
	$lang->load("profilepic");

	$user['profilepicdescription'] = htmlspecialchars_uni($user['profilepicdescription']);

	if($mybb->settings['profilepicdescription'] == 1)
	{
		eval("\$profilepicdescription = \"".$templates->get("modcp_editprofile_profilepic_description")."\";");
	}

	eval("\$profilepic = \"".$templates->get("modcp_editprofile_profilepic")."\";");
}

// Delete profile picture if user is deleted
function profilepic_user_delete()
{
	global $db, $mybb, $user;

	if($user['profilepictype'] == "upload")
	{
		// Removes the ./ at the beginning the timestamp on the end...
		@unlink("../".substr($user['profilepic'], 2, -20));
	}
}

// Admin CP permission control
function profilepic_usergroup_permission()
{
	global $mybb, $lang, $form, $form_container, $run_module;
	$lang->load("profilepic", true);

	if($run_module == 'user' && !empty($form_container->_title) & !empty($lang->misc) & $form_container->_title == $lang->misc)
	{
		$profilepic_options = array(
	 		$form->generate_check_box('canuseprofilepic', 1, $lang->can_use_profilepic, array("checked" => $mybb->input['canuseprofilepic'])),
			$form->generate_check_box('canuploadprofilepic', 1, $lang->can_upload_profilepic, array("checked" => $mybb->input['canuploadprofilepic'])),
			"{$lang->profile_pic_size}<br /><small>{$lang->profile_pic_size_desc}</small><br />".$form->generate_text_box('profilepicmaxsize', $mybb->input['profilepicmaxsize'], array('id' => 'profilepicmaxsize', 'class' => 'field50')). "KB",
			"{$lang->profile_pic_dims}<br /><small>{$lang->profile_pic_dims_desc}</small><br />".$form->generate_text_box('profilepicmaxdimensions', $mybb->input['profilepicmaxdimensions'], array('id' => 'profilepicmaxdimensions', 'class' => 'field'))
		);
		$form_container->output_row($lang->profile_picture, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $profilepic_options)."</div>");
	}
}

function profilepic_usergroup_permission_commit()
{
	global $db, $mybb, $updated_group;
	$updated_group['canuseprofilepic'] = intval($mybb->input['canuseprofilepic']);
	$updated_group['canuploadprofilepic'] = intval($mybb->input['canuploadprofilepic']);
	$updated_group['profilepicmaxsize'] = intval($mybb->input['profilepicmaxsize']);
	$updated_group['profilepicmaxdimensions'] = $db->escape_string($mybb->input['profilepicmaxdimensions']);
}

?>