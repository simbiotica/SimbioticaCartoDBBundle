<?php

namespace Simbiotica\CartoDBBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('SimbioticaCartoDBBundle:Default:index.html.twig', array('name' => $name));
    }
}
