<?php

/*
 * This file is part of the mixpanel bundle.
 *
 * (c) Samuel Gordalina <https://github.com/gordalina/mixpanel-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gordalina\MixpanelBundle\DataCollector;

use Gordalina\MixpanelBundle\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Gordalina\MixpanelBundle\Mixpanel\Flusher;

class MixpanelDataCollector extends DataCollector implements LateDataCollectorInterface
{
    /**
     * @var ManagerRegistry
     */
    private $registry;

    /**
     * @var Flusher
     */
    private $flusher;

    /**
     * @param  ManagerRegistry  $registry
     * @param  Flusher $flusher
     */
    public function __construct(ManagerRegistry $registry, Flusher $flusher)
    {
        $this->registry = $registry;
        $this->flusher = $flusher;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data = array(
            'mixpanel' => array(),
            'projects' => $this->registry->getAlias(),
            'users' => $this->registry->getUsers(),
            'config' => $this->registry->getConfig(),
            'time' => 0,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function lateCollect()
    {
        // lets collect the time by flushing the queue
        // normally this is only done when kernel.finish_request event is dispatched
        $this->flusher->flush();

        $this->data['mixpanel'] = $this->flusher->getData();
        $this->data['time'] = $this->flusher->getTime();
    }

    /**
     * @return integer
     */
    public function getTotal()
    {
        return array_reduce($this->data['mixpanel'], function ($carry, $item) {
            // $item is a project
            return array_reduce($item, function ($carry, $item) {
                // $item is an event or people
                return $carry += count($item);
            }, $carry);
        }, 0);
    }

    /**
     * @return integer
     */
    public function getEventCount()
    {
        return array_reduce($this->data['mixpanel'], function ($carry, $item) {
            // $item is a project
            return $carry += count($item['events']);
        }, 0);
    }

    /**
     * @return integer
     */
    public function getEngagementCount()
    {
        return array_reduce($this->data['mixpanel'], function ($carry, $item) {
            // $item is a project
            return $carry += count($item['people']);
        }, 0);
    }

    /**
     * @return array
     */
    public function getProjects()
    {
        return $this->data['projects'];
    }

    /**
     * @return array
     */
    public function getUsers()
    {
        return $this->data['users'];
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->data['config'];
    }

    /**
     * @param  string $id
     * @return array
     */
    public function getEvents($id)
    {
        $data = array();

        foreach ($this->data['mixpanel'][$id]['events'] as $event) {
            $data[$event['event']] = $event['properties'];
        }

        return $data;
    }

    /**
     * @param  string $id
     * @return array
     */
    public function getEngagement($id)
    {
        $data = array();

        foreach ($this->data['mixpanel'][$id]['people'] as $people) {
            $data[] = $people;
        }

        return $data;
    }

    /**
     * @return integer
     */
    public function getTime()
    {
        return $this->data['time'];
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'mixpanel';
    }
}
