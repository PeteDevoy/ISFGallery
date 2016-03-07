<?php

/**
 * Model for avatars.
 *
 * @package ISFGallery
 */
class ISFGallery_Model_Image extends XenForo_Model
{

	/**
	 * Processes an image upload for a user.
	 *
	 * @param XenForo_Upload $upload The uploaded image.
	 * @param integer $userId User ID avatar belongs to
	 * @param array|false $permissions User's permissions. False to skip permission checks
	 *
	 * @return array Changed avatar fields
	 */
	public function uploadImage(XenForo_Upload $upload, $userId, $permissions)
	{
		if (!$userId)
		{
			throw new XenForo_Exception('Missing user ID.');
		}

		if ($permissions !== false && !is_array($permissions))
		{
			throw new XenForo_Exception('Invalid permission set.');
		}

		$largestDimension = $this->getSizeFromCode('l');

		if (!$upload->isValid())
		{
			throw new XenForo_Exception($upload->getErrors(), true);
		}

		if (!$upload->isImage())
		{
			throw new XenForo_Exception(new XenForo_Phrase('uploaded_file_is_not_valid_image'), true);
		};

		$imageType = $upload->getImageInfoField('type');
		if (!in_array($imageType, array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG)))
		{
			throw new XenForo_Exception(new XenForo_Phrase('uploaded_file_is_not_valid_image'), true);
		}

		$baseTempFile = $upload->getTempFile();

		$width = $upload->getImageInfoField('width');
		$height = $upload->getImageInfoField('height');

		return $this->addToGallery($userId, $baseTempFile, $imageType, $width, $height, $permissions);
	}

	/**
	 * Re-crops an existing avatar with a square, starting at the specified coordinates
	 *
	 * @param integer $userId
	 * @param integer $x
	 * @param integer $y
	 *
	 * @return array Changed avatar fields
	 */
	public function recropAvatar($userId, $x, $y)
	{
		$sizeList = self::$_sizes;

		// get rid of the first entry in the sizes array
		list($largeSizeCode, $largeMaxDimensions) = each($sizeList);

		$outputFiles = array();

		$avatarFile = $this->getAvatarFilePath($userId, $largeSizeCode);
		$imageInfo = getimagesize($avatarFile);
		if (!$imageInfo)
		{
			throw new XenForo_Exception('Non-image passed in to recropAvatar');
		}
		$imageType = $imageInfo[2];

		// now loop through the rest
		while (list($sizeCode, $maxDimensions) = each($sizeList))
		{
			$image = XenForo_Image_Abstract::createFromFile($avatarFile, $imageType);
			$image->thumbnailFixedShorterSide($maxDimensions);

			if ($image->getOrientation() != XenForo_Image_Abstract::ORIENTATION_SQUARE)
			{
				$ratio = $maxDimensions / $sizeList['m'];

				$xCrop = floor($ratio * $x);
				$yCrop = floor($ratio * $y);

				if ($image->getWidth() > $maxDimensions && $image->getWidth() - $xCrop < $maxDimensions)
				{
					$xCrop = $image->getWidth() - $maxDimensions;
				}
				if ($image->getHeight() > $maxDimensions && $image->getHeight() - $yCrop < $maxDimensions)
				{
					$yCrop = $image->getHeight() - $maxDimensions;
				}

				$image->crop($xCrop, $yCrop, $maxDimensions, $maxDimensions);
			}

			$newTempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');

			$image->output($imageType, $newTempFile, self::$imageQuality);
			unset($image);

			$outputFiles[$sizeCode] = $newTempFile;
		}

		foreach ($outputFiles AS $sizeCode => $tempFile)
		{
			$this->_writeAvatar($userId, $sizeCode, $tempFile);
		}
		foreach ($outputFiles AS $tempFile)
		{
			@unlink($tempFile);
		}

		$dwData = array(
			'avatar_date' => XenForo_Application::$time,
			'avatar_crop_x' => $x,
			'avatar_crop_y' => $y,
			'gravatar' => '',
		);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_User');
		$dw->setExistingData($userId);
		$dw->bulkSet($dwData);
		$dw->save();

		return $dwData;
	}

	/**
	 * Writes out an avatar.
	 *
	 * @param integer $userId
	 * @param string $size Size code
	 * @param string $tempFile Temporary avatar file. Will be moved.
	 *
	 * @return boolean
	 */
	protected function _writeAvatar($userId, $size, $tempFile)
	{
		if (!in_array($size, array_keys(self::$_sizes)))
		{
			throw new XenForo_Exception('Invalid avatar size.');
		}

		$filePath = $this->getImageFilePath($userId, $size);
		$directory = dirname($filePath);

		if (XenForo_Helper_File::createDirectory($directory, true) && is_writable($directory))
		{
			if (file_exists($filePath))
			{
				@unlink($filePath);
			}

			return XenForo_Helper_File::safeRename($tempFile, $filePath);
		}
		else
		{
			return false;
		}
	}

	/**
	 * Get the file path to an avatar.
	 *
	 * @param integer $userId
	 * @param string $size Size code
	 * @param string External data directory path (optional)
	 *
	 * @return string
	 */
	public function getImageFilePath($userId, $size, $externalDataPath = null)
	{
		if ($externalDataPath === null)
		{
			$externalDataPath = XenForo_Helper_File::getExternalDataPath();
		}

		return sprintf('%s/isf_gallery_images/%s/%d/%d.jpg',
			$externalDataPath,
			$size,
			floor($userId / 1000),
			$userId
		);
	}

	/**
	 * Deletes a user's avatar.
	 *
	 * @param integer $userId
	 * @param boolean $updateUser
	 *
	 * @return array Changed avatar fields
	 */
	public function deleteAvatar($userId, $updateUser = true)
	{
		foreach (array_keys(self::$_sizes) AS $size)
		{
			$filePath = $this->getAvatarFilePath($userId, $size);
			if (file_exists($filePath) && is_writable($filePath))
			{
				@unlink($filePath);
			}
		}

		$dwData = array(
			'avatar_date' => 0,
			'avatar_width' => 0,
			'avatar_height' => 0,
			'avatar_crop_x' => 0,
			'avatar_crop_y' => 0,
			'gravatar' => '',
		);

		if ($updateUser)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($userId);
			$dw->bulkSet($dwData);
			$dw->save();
		}

		return $dwData;
	}

	/**
	 * Returns the _sizes array, defining what avatar sizes are available.
	 *
	 * @return array
	 */
	public static function getSizes()
	{
		return self::$_sizes;
	}

	/**
	 * Returns the maximum size (in pixels) of an avatar corresponding to the size code specified
	 *
	 * @param string $sizeCode (s,m,l)
	 *
	 * @return integer
	 */
	public static function getSizeFromCode($sizeCode)
	{
		return self::$_sizes[strtolower($sizeCode)];
	}

	public function getLatestImages()
	{
		$images[] = array(
			'image_id' => 1,
			'user_id' => 1,
			'time_uploaded' => '2016-02-24T22:01',
			'path' => '/styles/default/xenforo/XenForo-small.png'
		);
		return $images;
	}
}
