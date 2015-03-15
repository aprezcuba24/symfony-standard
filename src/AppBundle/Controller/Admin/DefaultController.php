<?php

/**
 * @author: Renier Ricardo Figueredo
 * @mail: aprezcuba24@gmail.com
 */

namespace AppBundle\Controller\Admin;

use CULabs\AdminBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class DefaultController extends Controller
{
    /**
     * @Route("/admin", name="admin_dashboard")
     */
    public function indexAction()
    {
        return $this->render('admin/default/dashboard.html.twig');
    }
} 