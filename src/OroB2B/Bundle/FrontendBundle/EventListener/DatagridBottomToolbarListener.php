<?php

namespace OroB2B\Bundle\FrontendBundle\EventListener;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Event\BuildBefore;

class DatagridBottomToolbarListener extends AbstractFrontendDatagridListener
{
    /**
     * {@inheritDoc}
     */
    public function onBuildBefore(BuildBefore $event)
    {
        $config = $event->getConfig();
        if ($this->isApplicable($config)) {
            $config->offsetSetByPath('[options][toolbarOptions][placement][bottom]', true);
        }
    }

    /**
     * @param DatagridConfiguration $config
     * @return bool
     */
    protected function isApplicable(DatagridConfiguration $config)
    {
        return $this->isFrontendRequest() &&
            $config->offsetGetByPath('[options][toolbarOptions][placement][bottom]') === null;
    }
}
