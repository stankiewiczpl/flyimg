<?php

namespace Core\Handler;

use Core\Entity\AppParameters;
use Core\Entity\Image\InputImage;
use Core\Entity\OptionsBag;
use Core\Entity\Image\OutputImage;
use Core\Processor\ExtractProcessor;
use Core\Processor\FaceDetectProcessor;
use Core\Processor\ImageProcessor;
use League\Flysystem\Filesystem;

/**
 * Class ImageHandler
 * @package Core\Service
 */
class ImageHandler
{
    /** @var ImageProcessor */
    protected $imageProcessor;

    /** @var FaceDetectProcessor */
    protected $faceDetectProcessor;

    /** @var ExtractProcessor */
    protected $extractProcessor;

    /** @var SecurityHandler */
    protected $securityHandler;

    /** @var Filesystem */
    protected $filesystem;

    /** @var AppParameters */
    protected $appParameters;

    /**
     * ImageHandler constructor.
     *
     * @param Filesystem    $filesystem
     * @param AppParameters $appParameters
     */
    public function __construct(Filesystem $filesystem, AppParameters $appParameters)
    {
        $this->filesystem = $filesystem;
        $this->appParameters = $appParameters;

        $this->imageProcessor = new ImageProcessor();
        $this->faceDetectProcessor = new FaceDetectProcessor();
        $this->extractProcessor = new ExtractProcessor();
        $this->securityHandler = new SecurityHandler($appParameters);
    }

    /**
     * @return ImageProcessor
     */
    public function imageProcessor(): ImageProcessor
    {
        return $this->imageProcessor;
    }

    /**
     * @return AppParameters
     */
    public function appParameters(): AppParameters
    {
        return $this->appParameters;
    }

    /**
     * @return SecurityHandler
     */
    public function securityHandler(): SecurityHandler
    {
        return $this->securityHandler;
    }


    /**
     * @param string $options
     * @param string $imageSrc
     * @param array $queryParams
     *
     * @return OutputImage
     * @throws \Exception
     */
    public function processImage(string $options, string $imageSrc, array $queryParams = []): OutputImage
    {
        [$options, $imageSrc] = $this->securityHandler->checkSecurityHash($options, $imageSrc);
        $this->securityHandler->checkRestrictedDomains($imageSrc);


        $optionsBag = new OptionsBag($this->appParameters, $options);
        $inputImage = new InputImage($optionsBag, $imageSrc, $queryParams);
        $outputImage = new OutputImage($inputImage);

        try {
            if ($this->filesystem->has($outputImage->getOutputImageName()) && $optionsBag->get('refresh')) {
                $this->filesystem->delete($outputImage->getOutputImageName());
            }

            if (!$this->filesystem->has($outputImage->getOutputImageName())) {
                $outputImage = $this->processNewImage($outputImage);
            }

            $outputImage->attachOutputContent($this->filesystem->read($outputImage->getOutputImageName()));
        } catch (\Exception $e) {
            $outputImage->removeOutputImage();
            throw $e;
        }

        return $outputImage;
    }

    /**
     * @param OutputImage $outputImage
     *
     * @throws \Exception
     */
    protected function faceDetectionProcess(OutputImage $outputImage): void
    {
        $faceCrop = $outputImage->extractKey('face-crop');
        $faceCropPosition = $outputImage->extractKey('face-crop-position');
        $faceBlur = $outputImage->extractKey('face-blur');

        if ($faceBlur && !$outputImage->isOutputGif()) {
            $this->faceDetectProcessor->blurFaces($outputImage);
        }

        if ($faceCrop && !$outputImage->isOutputGif()) {
            $this->faceDetectProcessor->cropFaces($outputImage, $faceCropPosition);
        }
    }

    /**
     * @param OutputImage $outputImage
     *
     * @return OutputImage
     * @throws \Exception
     */
    protected function processNewImage(OutputImage $outputImage): OutputImage
    {
        //Check Extract options
        if ($outputImage->extractKey('extract')) {
            $this->extractProcess($outputImage);
        }

        $outputImage = $this->imageProcessor()->processNewImage($outputImage);

        //Check Face Detection options
        $this->faceDetectionProcess($outputImage);

        $this->filesystem->write(
            $outputImage->getOutputImageName(),
            stream_get_contents(fopen($outputImage->getOutputImagePath(), 'r'))
        );

        return $outputImage;
    }

    /**
     * @param OutputImage $outputImage
     *
     * @throws \Exception
     */
    protected function extractProcess(OutputImage $outputImage): void
    {
        $this->extractProcessor->extract($outputImage);
    }

    /**
     * @param OutputImage $outputImage
     *
     * @return string
     */
    public function responseContentType(OutputImage $outputImage): string
    {
        if ($outputImage->getOutputImageExtension() == OutputImage::EXT_WEBP) {
            return InputImage::WEBP_MIME_TYPE;
        }
        if ($outputImage->getOutputImageExtension() == OutputImage::EXT_PNG) {
            return InputImage::PNG_MIME_TYPE;
        }
        if ($outputImage->getOutputImageExtension() == OutputImage::EXT_GIF) {
            return InputImage::GIF_MIME_TYPE;
        }

        return InputImage::JPEG_MIME_TYPE;
    }
}
