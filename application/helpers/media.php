<?php
/**
 * Media Helper. Add Media Items to an Incident
 * 
 * @package    Media
 * @author     Ushahidi Team
 * @copyright  (c) 2008 Ushahidi Team
 * @license    http://www.ushahidi.com/license.html
 */
class media_Core {
	/**
	* Associate media with an incident
	*
	* @param Incident_Model $incident
	* @param array $type (links, photos, etc.)
	* @return Media_Model
	*/
	static function links($incident, $type)
	{
		if ($incident AND ! empty($type))
		{
			$tag = ORM::factory("tag")->where("name", "=", $tag_name)->find();
			if (!$tag->loaded()) {
			$tag->name = $tag_name;
			$tag->count = 0;
			}

			$tag->add($item);
			$tag->count++;
			return $tag->save();
			}
		}
	}
	
	
	static function photos($incident, $media)
	{
		$filenames = upload::save('incident_photo');
		$i = 1;

		foreach ($filenames as $filename)
		{
			$new_filename = $this->id."_".$i."_".time();

			// Resize original file... make sure its max 408px wide
			Image::factory($filename)->resize(408,248,Image::AUTO)
				->save(Kohana::config('upload.directory', TRUE).$new_filename.".jpg");

			// Create thumbnail
			Image::factory($filename)->resize(70,41,Image::HEIGHT)
				->save(Kohana::config('upload.directory', TRUE).$new_filename."_t.jpg");

			// Remove the temporary file
			unlink($filename);

			// Save to DB
			$photo = ORM::factory('media');
			$photo->location_id = $location->id;
			$photo->incident_id = $this->id;
			$photo->media_type = 1; // Images
			$photo->media_link = $new_filename.".jpg";
			$photo->media_thumb = $new_filename."_t.jpg";
			$photo->media_date = date("Y-m-d H:i:s",time());
			$photo->save();
			$i++;
		}
	}
}