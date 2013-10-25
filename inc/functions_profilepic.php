<?php
/**
 * Profile Picture
 * Copyright 2010 Starpaul20
 */

/**
 * Remove any matching profile pic for a specific user ID
 *
 * @param int The user ID
 * @param string A file name to be excluded from the removal
 */
function remove_profilepic($uid, $exclude="")
{
	global $mybb;

	if(defined('IN_ADMINCP'))
	{
		$profilepicpath = '../'.$mybb->settings['profilepicuploadpath'];
	}
	else
	{
		$profilepicpath = $mybb->settings['profilepicuploadpath'];
	}

	$dir = opendir($profilepicpath);
	if($dir)
	{
		while($file = @readdir($dir))
		{
			if(preg_match("#profilepic_".$uid."\.#", $file) && is_file($profilepicpath."/".$file) && $file != $exclude)
			{
				@unlink($profilepicpath."/".$file);
			}
		}

		@closedir($dir);
	}
}

/**
 * Upload a new profile pic in to the file system
 *
 * @param srray incoming FILE array, if we have one - otherwise takes $_FILES['profilepicupload']
 * @param string User ID this profile pic is being uploaded for, if not the current user
 * @return array Array of errors if any, otherwise filename of successful.
 */
function upload_profilepic($profilepic=array(), $uid=0)
{
	global $db, $mybb, $lang;

	if(!$uid)
	{
		$uid = $mybb->user['uid'];
	}

	if(!$profilepic['name'] || !$profilepic['tmp_name'])
	{
		$profilepic = $_FILES['profilepicupload'];
	}

	if(!is_uploaded_file($profilepic['tmp_name']))
	{
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}

	// Check we have a valid extension
	$ext = get_extension(my_strtolower($profilepic['name']));
	if(!preg_match("#^(gif|jpg|jpeg|jpe|bmp|png)$#i", $ext)) 
	{
		$ret['error'] = $lang->error_profilepictype;
		return $ret;
	}

	if(defined('IN_ADMINCP'))
	{
		$profilepicpath = '../'.$mybb->settings['profilepicuploadpath'];
		$lang->load("messages", true);
	}
	else
	{
		$profilepicpath = $mybb->settings['profilepicuploadpath'];
	}

	$filename = "profilepic_".$uid.".".$ext;
	$file = upload_profilepicfile($profilepic, $profilepicpath, $filename);
	if($file['error'])
	{
		@unlink($profilepicpath."/".$filename);		
		$ret['error'] = $lang->error_uploadfailed;
		return $ret;
	}	

	// Lets just double check that it exists
	if(!file_exists($profilepicpath."/".$filename))
	{
		$ret['error'] = $lang->error_uploadfailed;
		@unlink($profilepicpath."/".$filename);
		return $ret;
	}

	// Check if this is a valid image or not
	$img_dimensions = @getimagesize($profilepicpath."/".$filename);
	if(!is_array($img_dimensions))
	{
		@unlink($profilepicpath."/".$filename);
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
			if($mybb->settings['profilepicresizing'] == "auto" || ($mybb->settings['profilepicresizing'] == "user" && $mybb->input['auto_resize'] == 1))
			{
				require_once MYBB_ROOT."inc/functions_image.php";
				$thumbnail = generate_thumbnail($profilepicpath."/".$filename, $profilepicpath, $filename, $maxheight, $maxwidth);
				if(!$thumbnail['filename'])
				{
					$ret['error'] = $lang->sprintf($lang->error_profilepictoobig, $maxwidth, $maxheight);
					$ret['error'] .= "<br /><br />".$lang->error_profilepicresizefailed;
					@unlink($profilepicpath."/".$filename);
					return $ret;				
				}
				else
				{
					// Reset filesize
					$profilepic['size'] = filesize($profilepicpath."/".$filename);
					// Reset dimensions
					$img_dimensions = @getimagesize($profilepicpath."/".$filename);
				}
			}
			else
			{
				$ret['error'] = $lang->sprintf($lang->error_profilepictoobig, $maxwidth, $maxheight);
				if($mybb->settings['profilepicresizing'] == "user")
				{
					$ret['error'] .= "<br /><br />".$lang->error_profilepicuserresize;
				}
				@unlink($profilepicpath."/".$filename);
				return $ret;
			}			
		}
	}

	// Next check the file size
	if($profilepic['size'] > ($mybb->usergroup['profilepicmaxsize']*1024) && $mybb->usergroup['profilepicmaxsize'] > 0)
	{
		@unlink($profilepicpath."/".$filename);
		$ret['error'] = $lang->error_uploadsize;
		return $ret;
	}	

	// Check a list of known MIME types to establish what kind of profile picture we're uploading
	switch(my_strtolower($profilepic['type']))
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
		default:
			$img_type = 0;
	}

	// Check if the uploaded file type matches the correct image type (returned by getimagesize)
	if($img_dimensions[2] != $img_type || $img_type == 0)
	{
		$ret['error'] = $lang->error_uploadfailed;
		@unlink($profilepicpath."/".$filename);
		return $ret;		
	}
	// Everything is okay so lets delete old profile picture for this user
	remove_profilepic($uid, $filename);

	$ret = array(
		"profilepic" => $mybb->settings['profilepicuploadpath']."/".$filename,
		"width" => intval($img_dimensions[0]),
		"height" => intval($img_dimensions[1])
	);
	return $ret;
}

/**
 * Actually move a file to the uploads directory
 *
 * @param array The PHP $_FILE array for the file
 * @param string The path to save the file in
 * @param string The filename for the file (if blank, current is used)
 */
function upload_profilepicfile($file, $path, $filename="")
{
	if(empty($file['name']) || $file['name'] == "none" || $file['size'] < 1)
	{
		$upload['error'] = 1;
		return $upload;
	}

	if(!$filename)
	{
		$filename = $file['name'];
	}

	$upload['original_filename'] = preg_replace("#/$#", "", $file['name']); // Make the filename safe
	$filename = preg_replace("#/$#", "", $filename); // Make the filename safe
	$moved = @move_uploaded_file($file['tmp_name'], $path."/".$filename);

	if(!$moved)
	{
		$upload['error'] = 2;
		return $upload;
	}
	@my_chmod($path."/".$filename, '0644');
	$upload['filename'] = $filename;
	$upload['path'] = $path;
	$upload['type'] = $file['type'];
	$upload['size'] = $file['size'];
	return $upload;
}
?>