<?php

class ISFGallery_ControllerPublic_Index extends XenForo_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		// fetch the Scratchpad_Model_Note class
		$imageModel = $this->_getImageModel();

		// get the maximum number of notes to fetch
		// $maxNotes = XenForo_Application::get('options')->scratchPadMaxNotes;

		// get the most recent notes
		$images = $imageModel->getLatestImages();

		// read the date of the most recent note

		if ($images)
		{
			$date = $images[0]['time_uploaded'];
		}
		else
		{
			$date = 0;
		}

		// put the data into an array to be passed to the view so the template can use it
		$viewParams = array(
			'images' => $images
		);

		// return a View (ISFGallery_ViewPublic_Index) using template 'isf_gallery_index'
		return $this->responseView(
			'ISFGallery_ViewPublic_Index',
			'isf_gallery_index',
			$viewParams
		);
	}

	public function actionPostImage()
	{
		// this action must be called via POST
		$this->_assertPostOnly();

		// create image model
		$imageModel = $this->_getImageModel();

		// the user is the current visitor
		$userId = XenForo_Visitor::getUserId();

		// fetch and clean the message text from input
		$imageFile = XenForo_Upload::getUploadedFile('image');

		/* @var $visitor XenForo_Visitor */
		$visitor = XenForo_Visitor::getInstance();

		// create a new DataWriter and set user_id and message fields
		/*
		$writer = XenForo_DataWriter::create('Scratchpad_DataWriter_Note');
		$writer->set('user_id', $userId);
		$writer->set('image_url', $image_url);
		$writer->save();
		*/

		// upload new avatar
		if ($imageFile)
		{
			$imageData = $imageModel->uploadImage($imageFile, $visitor['user_id']);
		}

		// only run this code if the action has been loaded via XenForo.ajax()
		if ($this->_noRedirect())
		{

			/*
			// fetch the date of the newest note shown on the page
			$date = $this->_input->filterSingle('date', XenForo_Input::UINT);

			// fetch all notes newer than the specified date,
			// which will include the one we just inserted
			$notes = $noteModel->getNotesNewerThan($date);

			// read the date of the newest note
			$date = $images[0]['time_uploaded'];

			// put the data into an array to be passed to the view so the template can use it
			$viewParams = array(
				'images' => $images,
				'date' => $date
			);

			// return a View (ISFGallery_ViewPublic_LatestImages) using template 'isf_gallery_index'
			return $this->responseView(
				'ISFGallery_ViewPublic_LatestImages',
				'isf_gallery_images',
				$viewParams
			);
			*/
			return 'nice ajax call bruh';
		}

		// redirect back to the normal scratchpad index page
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('gallery')
		);
	}

	/**
	 * @return ISFGallery_Model_Image
	 */
	protected function _getImageModel()
	{
		return $this->getModelFromCache('ISFGallery_Model_Image');
	}
}
