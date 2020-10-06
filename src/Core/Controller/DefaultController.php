<?php

namespace Core\Controller;

use Core\Entity\Response;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends CoreController
{
    /**
     * @return string
     */
    public function indexAction()
    {
        return $this->render('Default/index');
    }

    /**
     * @param Request $request
     * @param string $options
     * @param string|null $imageSrc
     *
     * @return Response
     * @throws \Exception
     */
    public function uploadAction(Request $request,string $options, string $imageSrc = null): Response
    {
        $queryParams = $request->query->all();
        $image = $this->imageHandler()->processImage($options, $imageSrc, $queryParams);

        $this->response->generateImageResponse($image);

        return $this->response;
    }

    /**
     * @param string $options
     * @param string $imageSrc
     *
     * @return Response
     * @throws \Exception
     */
    public function pathAction(string $options, string $imageSrc = null): Response
    {
        $image = $this->imageHandler()->processImage($options, $imageSrc);

        $this->response->generatePathResponse($image);

        return $this->response;
    }
}
