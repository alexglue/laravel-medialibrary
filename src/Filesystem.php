<?php

namespace Spatie\MediaLibrary;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Filesystem\Factory;

class Filesystem
{
	/**
	 * @var Factory
	 */
	protected $filesystems;
	/**
	 * @var \Illuminate\Contracts\Config\Repository
	 */
	protected $config;

	/**
	 * @param Factory                                 $filesystems
	 * @param \Illuminate\Contracts\Config\Repository $config
	 */
	public function __construct(Factory $filesystems, ConfigRepository $config)
	{
		$this->filesystems = $filesystems;
		$this->config      = $config;
	}

	/**
	 * Add a file to the mediaLibrary for the given media.
	 *
	 * @param string                     $file
	 * @param \Spatie\MediaLibrary\Media $media
	 * @param                            $targetFileName
	 */
	public function add($file, Media $media, $targetFileName = '')
	{
		$this->copyToMediaLibrary($file, $media, '', $targetFileName);

		app(FileManipulator::class)->createDerivedFiles($media);
	}

	/**
	 * Copy a file to the mediaLibrary for the given $media.
	 *
	 * @param string                     $file
	 * @param \Spatie\MediaLibrary\Media $media
	 * @param string                     $subDirectory
	 * @param string                     $targetFileName
	 */
	public function copyToMediaLibrary($file, Media $media, $subDirectory = '', $targetFileName = '')
	{
		$destination = array_filter(
			[
				$this->getMediaDirectoryName($media),
				$subDirectory,
				$targetFileName ? basename($targetFileName) : $this->renderFilename($file)
			]
		);

		$destination = implode(DIRECTORY_SEPARATOR, $destination);

		$this->filesystems->disk($media->disk)->put($destination, fopen($file, 'r+'));
	}

	/**
	 * Copy a file from the mediaLibrary to the given targetFile.
	 *
	 * @param \Spatie\MediaLibrary\Media $media
	 * @param string                     $targetFile
	 */
	public function copyFromMediaLibrary(Media $media, $targetFile)
	{
		$sourceFile = $this->getMediaDirectoryName($media) . DIRECTORY_SEPARATOR . $media->file_name;

		touch($targetFile);

		$stream = $this->filesystems->disk($media->disk)->readStream($sourceFile);
		file_put_contents($targetFile, stream_get_contents($stream), FILE_APPEND);
		fclose($stream);
	}

	/**
	 * Remove all files for the given media.
	 *
	 * @param \Spatie\MediaLibrary\Media $media
	 */
	public function removeFiles(Media $media)
	{
		$this->filesystems->disk($media->disk)->deleteDirectory($this->getMediaDirectoryName($media));
	}

	/**
	 * Rename a file for the given media.
	 *
	 * @param Media  $media
	 * @param string $oldName
	 * @return bool
	 */
	public function renameFile(Media $media, $oldName)
	{
		$oldFile = $this->getMediaDirectoryName($media) . DIRECTORY_SEPARATOR . $oldName;
		$newFile = $this->getMediaDirectoryName($media) . DIRECTORY_SEPARATOR . $media->file_name;

		$this->filesystems->disk($media->disk)->move($oldFile, $newFile);

		return true;
	}

	/**
	 * Return the directory where all files of the given media are stored.
	 *
	 * @param \Spatie\MediaLibrary\Media $media
	 * @return string
	 */
	public function getMediaDirectory(Media $media)
	{
		$directory = $this->getMediaDirectoryName($media);
		$this->filesystems->disk($media->disk)->makeDirectory($directory);

		return $directory;
	}

	public function getMediaDirectoryName(Media $media)
	{
		$filename   = pathinfo($media->file_name, PATHINFO_FILENAME);
		$model_path = explode('\\', $media->model_type);

		$parts = [
			strtolower(array_pop($model_path)),
			strtolower($media->collection_name),
			implode(DIRECTORY_SEPARATOR, array_slice(str_split($filename, 3), 0, 3)),
			$media->id
		];

		return implode(DIRECTORY_SEPARATOR, $parts);
	}

	public function renderFilename($filename)
	{
		return md5($filename) . '.' . pathinfo($filename, PATHINFO_EXTENSION);
	}
}
