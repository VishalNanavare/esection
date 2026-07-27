<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all controllers that extend BaseController.
     *
     * @var list<string>
     */
    protected $helpers = ['esection', 'url', 'form', 'html'];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do not edit this line
        parent::initController($request, $response, $logger);

        // Preload helper globally
        helper(['esection', 'url', 'form', 'html']);
    }
}
