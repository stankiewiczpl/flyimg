Classes:

- BaseImage

	- InputImage

	- OutputImageAbstract
		- PngOutputImage
		- GifOutputImage
		- JpgOutputImage
		- WebPOutputImage

- ProcessOptions: process the compression options


- CoreManager: 
	- Parse Options
	- create InputImage
	- create OutputImage based on requested format

- ImageProcessor: process an OutputImage

