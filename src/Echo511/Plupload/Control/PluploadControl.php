<?php

/**
 * This class is part of Echo511\Plupload. Licence free.
 */

namespace Echo511\Plupload\Control;

use Echo511\Plupload\Entity\IUploadQueueFactory;
use Echo511\Plupload\Entity\Upload;
use Echo511\Plupload\Entity\UploadQueue;
use Echo511\Plupload\Service\Uploader;
use Nette\Application\UI\Control;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\Utils\Random;
use Nette\Utils\Strings;

/**
 * Render component and handle uploads.
 * 
 * @author Nikolas Tsiongas
 */
class PluploadControl extends Control
{

	/** @var string */
	public $maxFileSize = '20mb';

	/** @var string */
	public $maxChunkSize = '1mb';

	/** @var string */
	public $allowedExtensions = '*';

	/** @var callable */
	public $onFileUploaded = array();

	/** @var callable */
	public $onUploadComplete = array();

	/** @var string */
	public $templateFile;

	/** @var string */
    protected $id;

	/** @var Uploader */
    protected $uploader;

	/** @var IUploadQueueFactory */
	protected $uploadQueueFactory;

	/** @var IStorage */
    protected $cacheStorage;

	/**
	 * @param Uploader $uploader
	 * @param IUploadQueueFactory $uploadQueueFactory
	 * @param IStorage $cacheStorage
	 */
	public function __construct(Uploader $uploader, IUploadQueueFactory $uploadQueueFactory, IStorage $cacheStorage)
	{
		$this->uploader = $uploader;
		$this->uploadQueueFactory = $uploadQueueFactory;
		$this->cacheStorage = $cacheStorage;

		$this->templateFile = __DIR__ . '/../templates/control/plupload.latte';
		$this->id = Random::generate();
	}



	/**
	 * Render component.
	 */
	public function render()
	{
		$this->template->setFile($this->templateFile);
		$this->template->id = $this->id;
		$this->template->maxFileSize = $this->maxFileSize;
		$this->template->maxChunkSize = $this->maxChunkSize;
		$this->template->allowedExtensions = $this->allowedExtensions;
		$this->template->render();
	}



	/**
	 * Handle incoming chunk/file.
	 * @param string $id
	 */
	public function handleUpload($id)
	{
		$this->id = $id;
        $self = $this;
		$this->uploader->upload($id, function(Upload $upload) use ($self) {
            $uploadQueue = $self->restoreUploadQueue();
			$uploadQueue->addUpload($upload);
            $self->onFileUploaded($uploadQueue);
            $self->storeUploadQueue($uploadQueue);
		});
	}



	/**
	 * Fire callback when uploading is done.
	 * @param string $id
	 */
	public function handleUploadComplete($id)
	{
		$this->id = $id;
		$this->onUploadComplete($this->restoreUploadQueue());
	}



	/**
	 * Restore upload queue from previous request.
	 * @return UploadQueue
	 */
	public function restoreUploadQueue()
	{
		$cache = new Cache($this->cacheStorage, get_class());
        $uploadQueueFactory = $this->uploadQueueFactory;
        $id = $this->id;
		return $cache->load($this->id, function() use ($uploadQueueFactory, $id) {
				return $uploadQueueFactory->create($id);
			});
	}



	/**
	 * Store upload queue between requests.
	 * @param UploadQueue $uploadQueue
	 */
    public function storeUploadQueue(UploadQueue $uploadQueue)
	{
		$cache = new Cache($this->cacheStorage, get_class());
		$cache->save($this->id, $uploadQueue, array(
		    Cache::EXPIRE => '1 minutes',
		    Cache::SLIDING => TRUE,
		));
	}



}

interface IPluploadControlFactory
{

	/** @return PluploadControl */
	function create();
}
