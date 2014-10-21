<?php
//THIS IS THE CLASS WHICH IS IN THE CLASSES FOLDER USUALLY. NOT USING A FRAMEWORK NOW, THOUGH!
define('SYSTEM_FOLDER', '');
define('USER_CONTENT_VERSION', '1');
class ImageUploader {
	//	Attributes
	
	//	Constructor
	public $versionPath;
	protected $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif');
	public function __construct () {
		if (defined('SYSTEM_FOLDER') && defined('USER_CONTENT_VERSION')) {
			//This is something like /website/user_content/1/
			$this->versionPath = SYSTEM_FOLDER . 'user_content/' . USER_CONTENT_VERSION .'/';	
			
		} else {
			die('No version path');
		}
	}
	
	protected $extension;
	public function getFileExtension() {
		if (!$this->extension) {
			if ($this->theFile) {
				$this->extension = pathinfo($this->theFile['original_name'], PATHINFO_EXTENSION);
				
				if ($this->isAllowedExtension($this->extension)) {
					return $this->extension;
				}
			}
		}
		
		return $this->extension;
	}
	
	protected function isAllowedExtension ($ext) {
		if ($ext && $this->allowedExtensions) {
			if (in_array($ext, $this->allowedExtensions)) {
				return true;	
			}
		}
		
		return false;
	}
	
	protected $imageSource;
	protected function getImageSource () {
		if (!$this->imageSource) {
			if ($this->theFile) {
				$uploadedFile = $this->theFile['name'];
				
				if ($uploadedFile) {
					switch ($this->getFileExtension()) {
						case 'jpg':
							$this->imageSource = imagecreatefromjpeg($uploadedFile);
							break;
						case 'jpeg':
							$this->imageSource = imagecreatefromjpeg($uploadedFile);
							break;
							
						case 'png':
							$this->imageSource = imagecreatefrompng($uploadedFile);
							break;
						case 'gif':
							$this->imageSource = imagecreatefromgif($uploadedFile);
							break;
					}
				}
			}
		}

		return $this->imageSource;
	}
	
	protected $theFile;

	protected function getTheFile($file) {
		if (is_array($file)) {
			$newFile['name'] = $file['tmp_name'];
			$newFile['original_name'] = $file['name'];
		} else {
			$newFile['name'] = $file;	
			$newFile['original_name'] = $file;
		}
		
		return $newFile;
	}
	
	public function placeImageInContainer ($target, $newFilename, $file, 
	$containerWidth, $containerHeight, 
	$imageDesiredFileWidth, $imageDesiredFileHeight,
	$posX, $posY, $background) {
		$this->theFile = $this->getTheFile($file);
		
		//Get the width and height of the file thats being uploaded
		//list($currentFileWidth, $currentFileHeight) = getimagesize($this->theFile['name']);
		
		//Make an image container that is as big as the desired width and height. The image width and height is being calculated after this
		//And is being pasted into this container. The image cant be bigger than this container but it CAN be smaller, in that case
		//The transparent background sustains the desiredWidth*desiredHeight dimension.

		$newImage = imagecreatetruecolor($containerWidth, $containerHeight);
		
		// Make the background
		if ($background != 'transparent') {
			if (stripos($background, '#') !== false) {
				$background = substr($background, 1, strlen($background));
			}
			$R = '0X' . substr($background, 0, 2);
			$B = '0X' . substr($background, 2, 2);
			$G = '0X' . substr($background, 4, 2);

			
			$bgColor = imagecolorallocate($newImage, $R, $B, $G);
			imagefill($newImage, 0, 0, $bgColor);

			//imagefilledrectangle($newImage, 0, 0, $containerWidth, $containerHeight, $bgColor);
		} else {
			// Make the background transparent
			$black = imagecolorallocate($newImage, 0, 0, 0);
			imagecolortransparent($newImage, $black);	
		}
		
		$src = $this->getImageSource();

		if ($newImage && $src) {
			
			$fileDestination = $this->versionPath.$target;
			
			//In order to place an image in a container the image that will be put IN the container should be resized to the size he requires(imageDesiredFileWidth and height) 
			$resizedFilename = $this->createNewSizeTmp($src, $imageDesiredFileWidth, $imageDesiredFileHeight, $fileDestination .'tmp/');
			if ($resizedFilename) {
				//When the resizedFilename TMP is made use this image to place into the container.
				$this->theFile['name'] = $fileDestination .'tmp/'.$resizedFilename;
				
				$newSrc = imagecreatefrompng($this->theFile['name']);
				imagecopyresampled($newImage, $newSrc, $posX, $posY, 0, 0, $imageDesiredFileWidth, $imageDesiredFileHeight, $imageDesiredFileWidth, $imageDesiredFileHeight);
				$fileDestination = $this->versionPath.$target;
				$fileName = $newFilename .'.png';
				
				if ($this->uploadImage($newImage, $fileDestination, $fileName)) {
					//The image is saved. Delete the recently made TMP
					unlink($fileDestination .'/tmp/'.$resizedFilename);
					
					//Return the target and filename so it can be saved in a database or whatever the user wants with it
					//Returns something like managedMedia/participant/logo_1.png

					return $target.$fileName;
				} else {
					//The image is not saved but will not be saved. Delete the recently made TMP
					unlink($fileDestination .'/tmp/'.$resizedFilename);	
				}
			}
		}

		return false;
	}
	
	protected function createNewSizeTmp ($src, $width, $height, $destination) {
		list($currentFileWidth, $currentFileHeight) = getimagesize($this->theFile['name']);
		
		$resizeImageContainer = imagecreatetruecolor($width, $height);
		imagecopyresampled($resizeImageContainer, $src, 0, 0, 0, 0, $width, $height, $currentFileWidth, $currentFileHeight);
		$resizedFilename = 'tmp_' . substr(md5(time()), 0, 5) .'.png';
		if ($this->uploadImage($resizeImageContainer, $destination, $resizedFilename)) {
			return $resizedFilename;
		}
		
		return false;
	}

	public function createImageWithFixedDimensions ($target, $newFilename, $file, $desiredWidth, $desiredHeight) {
		$this->theFile = $this->getTheFile($file);
		
		//Get the width and height of the file thats being uploaded
		list($currentFileWidth, $currentFileHeight) = getimagesize($this->theFile['name']);
		
		//Make an image container that is as big as the desired width and height. The image width and height is being calculated after this
		//And is being pasted into this container. The image cant be bigger than this container but it CAN be smaller, in that case
		//The transparent background sustains the desiredWidth*desiredHeight dimension.
		$newImage = imagecreatetruecolor($desiredWidth, $desiredHeight);
		
		// Make the background transparent
		$black = imagecolorallocate($newImage, 0, 0, 0);
		imagecolortransparent($newImage, $black);

		$src = $this->getImageSource();
		
		$newX = 0;
		$newY = 0;
		
		$ratio = $currentFileWidth/$currentFileHeight;
		if ($currentFileWidth > $currentFileHeight) {
			//Image is wider than high. Therefore the width can be the desired width and the height has to be relative to its width;
			$newWidth = $desiredWidth;
			$newHeight = $desiredWidth / $ratio;
			$newY = ($desiredHeight - $newHeight) / 2;
		} else {
			//Image is higher than wide
			$newWidth = $desiredHeight * $ratio;
			$newHeight = $desiredHeight;
			$newX = ($desiredWidth - $newWidth) / 2;
		}
		
		

		if ($newImage && $src) {
			imagecopyresampled($newImage, $src, $newX, $newY, 0, 0, $newWidth, $newHeight, $currentFileWidth, $currentFileHeight);
			$fileDestination = $this->versionPath.$target;
			$fileName = $newFilename .'.png';
			
			if ($this->uploadImage($newImage, $fileDestination, $fileName)) {
				//Return the target and filename so it can be saved in a database or whatever the user wants with it
				//Returns something like managedMedia/participant/logo_1.png
				return $target.$fileName;
			}
		}
		return false;
	}
	
	public function createImageWithMaxDimensions ($target, $newFilename, $file, $maxWidth, $maxHeight) {
		$this->theFile = $this->getTheFile($file);
		
		//Get the width and height of the file thats being uploaded
		list($currentFileWidth, $currentFileHeight) = getimagesize($this->theFile['name']);

		if ($maxWidth >= $currentFileWidth && $maxHeight >= $currentFileHeight) {
			//Max width and height are not reached. Use their original dimensions
			$newWidth = $currentFileWidth;
			$newHeight = $currentFileHeight;

		} else {
			$ratio = $currentFileWidth/$currentFileHeight;
			if ($currentFileWidth > $currentFileHeight) {
				//Check if the new width should be capped to the maxWidth or can sustain his own width(Because its lower than the maximum)
				$newTempWidth = ($maxWidth >= $currentFileWidth) ? $currentFileWidth : $maxWidth;
				
				$newWidth = $newTempWidth;
				$newHeight = $newTempWidth / $ratio;
			} else {
				//Check if the new height should be capped to the maxHeight or can sustain his own height(Because its lower than the maximum)
				$newTempHeight = ($maxHeight >= $currentFileHeight) ? $currentFileHeight : $maxHeight;
				
				$newWidth = $newTempHeight * $ratio;
				$newHeight = $newTempHeight;
			}
		}
		
		$src = $this->getImageSource();
		//Create an image container that is equally big as the newly founded width and height.
		$newImage = imagecreatetruecolor($newWidth, $newHeight);
		if ($newImage && $src) {
			imagecopyresampled($newImage, $src, 0, 0, 0, 0, $newWidth, $newHeight, $currentFileWidth, $currentFileHeight);
			$fileDestination = $this->versionPath.$target;
			$fileName = $newFilename .'.png';
			
			if ($this->uploadImage($newImage, $fileDestination, $fileName)) {
				//Return the target and filename so it can be saved in a database or whatever the user wants with it
				//Returns something like managedMedia/participant/logo_1.png
				return $target.$fileName;
			}
		}
		return false;
	}
	
	public function uploadUnalteredImage($target, $newFilename, $file) {
		$this->theFile = $this->getTheFile($file);

		//Get the width and height of the file thats being uploaded
		list($currentFileWidth, $currentFileHeight) = getimagesize($this->theFile['name']);
		$src = $this->getImageSource();
		
		//Create an image container that is equally big as the newly founded width and height.
		$newImage = imagecreatetruecolor($currentFileWidth, $currentFileHeight);

		if ($newImage && $src) {
			imagecopyresampled($newImage, $src, 0, 0, 0, 0, $currentFileWidth, $currentFileHeight, $currentFileWidth, $currentFileHeight);
			$fileDestination = $this->versionPath.$target;
			$fileName = $newFilename .'.png';
			if ($this->uploadImage($newImage, $fileDestination, $fileName)) {
				//Return the target and filename so it can be saved in a database or whatever the user wants with it
				//Returns something like managedMedia/participant/logo_1.png
				return $target.$fileName;
			}
		}

		return false;
	}
	
	protected function uploadImage($image, $destination, $imageName) {
		if (imagepng($image, $destination.$imageName)) {
			return true;
		}

		return false;
	}
}
?>