<?php

namespace Mollie\Component\Mollie\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

class DisplayController extends BaseController
{
    protected $default_view = 'configuration';

    /**
     * Display a view.
     *
     * @param   boolean  $cachable
     * @param   array    $urlparams
     * @return  static
     */
    public function display($cachable = false, $urlparams = []): static
    {
        parent::display($cachable, $urlparams);

        return $this;
    }
}
