<?php
/**
 * Profile Picture
 * Copyright 2010 Starpaul20
 */

/**
 * Remove any matching profile picture for a specific user ID
 *
 * @param int $uid The user ID
 * @param string $exclude A file name to be excluded from the removal
 */
function remove_profilepicture($uid, $exclude="")
{
	global $mybb;

	if(defined('IN_ADMINCP'))
	{
		$profilepicturepath = '../'.$mybb->settings['profilepictureuploadpath'];
	}
	else
	{
		$profilepicturepath = $mybb->settings['profilepictureuploadpath'];
	}

	$dir = opendir($profilepicturepath);
	if($dir)
	{
		while($file = @readdir($dir))
		{
			if(preg_match("#profilepic_".$uid."\.#", $file) && is_file($profilepicturepath."/".$file) && $file != $exclude)
			{
				require_once MYBB_ROOT."inc/functions_upload.php";
				delete_uploaded_file($profilepicturepath."/".$file);
			}
		}

		@closedir($dir);
	}
}

/**
 * Upload a new profile picture in to the file system
 *
 * @param array $profilepicture incoming FILE array, if we have one - otherwise takes $_FILES['profilepictureupload']
 * @param int $uid User ID this profile picture is being uploaded for, if not the current user
 * @return array Array of errors if any, otherwise filename if successful.
 */
function upload_profilepicture($profilepicture=array(), $uid=0)
{
	global $db, $mybb, $lang;

	$ret = array();
	require_once MYBB_ROOT."inc/functions_upload.php";

	if(!$uid)
	{
		$uid = $mybb->user['uid'];
	}

	if(empty($profilepicture['name']) || !$profilepicture['tmp_name'])
	{
		$profilepicture = $_FILES['profilepictureupload'];
	}

	if(!is_uploaded_file($profilepicture['tmp_name']))
	{
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}

	// Check we have a valid extension
	$ext = get_extension(my_strtolower($profilepicture['name']));
	if(!preg_match("#^(gif|jpg|jpeg|jpe|bmp|png)$#i", $ext))
	{
		$ret['error'] = $lang->error_profilepicturetype;
		return $ret;
	}

	if(defined('IN_ADMINCP'))
	{
		$profilepicturepath = '../'.$mybb->settings['profilepictureuploadpath'];
		$lang->load("messages", true);
	}
	else
	{
		$profilepicturepath = $mybb->settings['profilepictureuploadpath'];
	}

	$filename = "profilepic_".$uid.".".$ext;
	$file = upload_file($profilepicture, $profilepicturepath, $filename);
	if(!empty($file['error']))
	{
		delete_uploaded_file($profilepicturepath."/".$filename);
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}

	// Lets just double check that it exists
	if(!file_exists($profilepicturepath."/".$filename))
	{
		$ret['error'] = $lang->error_uploadfailed;
		delete_uploaded_file($profilepicturepath."/".$filename);
		return $ret;
	}

	// Check if this is a valid image or not
	$img_dimensions = @getimagesize($profilepicturepath."/".$filename);
	if(!is_array($img_dimensions))
	{
		delete_uploaded_file($profilepicturepath."/".$filename);
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}

	// Check profile picture dimensions
	if($mybb->usergroup['profilepicmaxdimensions'] != '')
	{
		list($maxwidth, $maxheight) = @explode("x", $mybb->usergroup['profilepicmaxdimensions']);
		if(($maxwidth && $img_dimensions[0] > $maxwidth) || ($maxheight && $img_dimensions[1] > $maxheight))
		{
			// Automatic resizing enabled?
			if($mybb->settings['profilepictureresizing'] == "auto" || ($mybb->settings['profilepictureresizing'] == "user" && $mybb->input['auto_resize'] == 1))
			{
				require_once MYBB_ROOT."inc/functions_image.php";
				$thumbnail = generate_thumbnail($profilepicturepath."/".$filename, $profilepicturepath, $filename, $maxheight, $maxwidth);
				if(!$thumbnail['filename'])
				{
					$ret['error'] = $lang->sprintf($lang->error_profilepicturetoobig, $maxwidth, $maxheight);
					$ret['error'] .= "<br /><br />".$lang->error_profilepictureresizefailed;
					delete_uploaded_file($profilepicturepath."/".$filename);
					return $ret;
				}
				else
				{
					// Copy scaled image to CDN
					copy_file_to_cdn($profilepicturepath . '/' . $thumbnail['filename']);
					// Reset filesize
					$profilepicture['size'] = filesize($profilepicturepath."/".$filename);
					// Reset dimensions
					$img_dimensions = @getimagesize($profilepicturepath."/".$filename);
				}
			}
			else
			{
				$ret['error'] = $lang->sprintf($lang->error_profilepicturetoobig, $maxwidth, $maxheight);
				if($mybb->settings['profilepictureresizing'] == "user")
				{
					$ret['error'] .= "<br /><br />".$lang->error_profilepictureuserresize;
				}
				delete_uploaded_file($profilepicturepath."/".$filename);
				return $ret;
			}
		}
	}

	// Next check the file size
	if($profilepicture['size'] > ($mybb->usergroup['profilepicmaxsize']*1024) && $mybb->usergroup['profilepicmaxsize'] > 0)
	{
		delete_uploaded_file($profilepicturepath."/".$filename);
		$ret['error'] = $lang->error_uploadsize;
		return $ret;
	}

	// Check a list of known MIME types to establish what kind of profile picture we're uploading
	switch(my_strtolower($profilepicture['type']))
	{
		case "image/gif":
			$img_type =  1;
			break;
		case "image/jpeg":
		case "image/x-jpg":
		case "image/x-jpeg":
		case "image/pjpeg":
		case "image/jpg":
			$img_type = 2;
			break;
		case "image/png":
		case "image/x-png":
			$img_type = 3;
			break;
		case "image/bmp":
		case "image/x-bmp":
		case "image/x-windows-bmp":
			$img_type = 6;
			break;
		default:
			$img_type = 0;
	}

	// Check if the uploaded file type matches the correct image type (returned by getimagesize)
	if($img_dimensions[2] != $img_type || $img_type == 0)
	{
		$ret['error'] = $lang->error_uploadfailed;
		delete_uploaded_file($profilepicturepath."/".$filename);
		return $ret;
	}
	// Everything is okay so lets delete old profile pictures for this user
	remove_profilepicture($uid, $filename);

	$ret = array(
		"profilepicture" => $mybb->settings['profilepictureuploadpath']."/".$filename,
		"width" => (int)$img_dimensions[0],
		"height" => (int)$img_dimensions[1]
	);

	return $ret;
}

/**
 * Formats a profile picture to a certain dimension
 *
 * @param string $profilepicture The profile picture file name
 * @param string $dimensions Dimensions of the profile picture, width x height (e.g. 44|44)
 * @param string $max_dimensions The maximum dimensions of the formatted profile picture
 * @return array Information for the formatted profile picture
 */
function format_profile_picture($profilepicture, $dimensions = '', $max_dimensions = '')
{
	global $mybb;
	static $profilepictures;

	if(!isset($profilepictures))
	{
		$profilepictures = array();
	}

	if(my_strpos($profilepicture, '://') !== false && !$mybb->settings['allowremoteprofilepictures'])
	{
		// Remote profile picture, but remote profile pictures are disallowed.
		$profilepicture = null;
	}

	if(!$profilepicture)
	{
		// Default profile picture
		$profilepicture = '';
		$dimensions = '';
	}

	if(!$max_dimensions)
	{
		$max_dimensions = $mybb->usergroup['profilepicmaxdimensions'];
	}

	// An empty key wouldn't work so we need to add a fall back
	$key = $dimensions;
	if(empty($key))
	{
		$key = 'default';
	}
	$key2 = $max_dimensions;
	if(empty($key2))
	{
		$key2 = 'default';
	}

	if(isset($profilepictures[$profilepicture][$key][$key2]))
	{
		return $profilepictures[$profilepicture][$key][$key2];
	}

	$profilepicture_width_height = '';

	if($dimensions)
	{
		$dimensions = explode("|", $dimensions);

		if($dimensions[0] && $dimensions[1])
		{
			list($max_width, $max_height) = explode('x', $max_dimensions);

			if(!empty($max_dimensions) && ($dimensions[0] > $max_width || $dimensions[1] > $max_height))
			{
				require_once MYBB_ROOT."inc/functions_image.php";
				$scaled_dimensions = scale_image($dimensions[0], $dimensions[1], $max_width, $max_height);
				$profilepicture_width_height = "width=\"{$scaled_dimensions['width']}\" height=\"{$scaled_dimensions['height']}\"";
			}
			else
			{
				$profilepicture_width_height = "width=\"{$dimensions[0]}\" height=\"{$dimensions[1]}\"";
			}
		}
	}

	$profilepictures[$profilepicture][$key][$key2] = array(
		'image' => htmlspecialchars_uni($mybb->get_asset_url($profilepicture)),
		'width_height' => $profilepicture_width_height
	);

	return $profilepictures[$profilepicture][$key][$key2];
}
