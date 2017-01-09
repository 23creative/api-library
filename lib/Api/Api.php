<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     MIT http://opensource.org/licenses/MIT
 */

namespace Mautic\Api;

use Mautic\Auth\AuthInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Base API class
 */
class Api implements LoggerAwareInterface
{
    /**
     * Common endpoint for this API
     *
     * @var string
     */
    protected $endpoint;

    /**
     * Name of the array element where the list of items is
     *
     * @var string
     */
    protected $listName;

    /**
     * Name of the array element where the item data is
     *
     * @var string
     */
    protected $itemName;

    /**
     * Array of default endpoints supported by the context; if empty, all are supported
     *
     * @var array
     */
    protected $endpointsSupported = array();

    /**
     * Base URL for API endpoints
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * @var AuthInterface
     */
    private $auth;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param AuthInterface $auth
     * @param string        $baseUrl
     */
    public function __construct(AuthInterface $auth, $baseUrl = '')
    {
        $this->auth = $auth;
        $this->setBaseUrl($baseUrl);
    }

    /**
     * Get the logger.
     *
     * @return LoggerInterface
     */
    public function getLogger()
    {
        // If a logger hasn't been set, use NullLogger
        if (!($this->logger instanceof LoggerInterface)) {
            $this->logger = new NullLogger();
        }

        return $this->logger;
    }

    /**
     * Sets a logger.
     *
     * @param LoggerInterface $logger
     *
     * @return $this
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Returns list name
     *
     * @return string
     */
    public function listName()
    {
        return $this->listName;
    }

    /**
     * Returns item name
     *
     * @return string
     */
    public function itemName()
    {
        return $this->itemName;
    }

    /**
     * Set the base URL for API endpoints
     *
     * @param string $url
     *
     * @return $this
     */
    public function setBaseUrl($url)
    {
        if (substr($url, -1) != '/') {
            $url .= '/';
        }

        if (substr($url, -4, 4) != 'api/') {
            $url .= 'api/';
        }

        $this->baseUrl = $url;

        return $this;
    }

    /**
     * Make the API request
     *
     * @param string $endpoint
     * @param array  $parameters
     * @param string $method
     *
     * @return array|mixed
     */
    public function makeRequest($endpoint, array $parameters = array(), $method = 'GET')
    {
        $url = $this->baseUrl.$endpoint;

        if (strpos($url, 'http') === false) {
            $error = array(
                'code'    => 500,
                'message' => sprintf(
                    'URL is incomplete.  Please use %s, set the base URL as the third argument to MauticApi::getContext(), or make $endpoint a complete URL.',
                    __CLASS__.'setBaseUrl()'
                )
            );
        } else {
            try {
                $response = $this->auth->makeRequest($url, $parameters, $method);

                $this->getLogger()->debug('API Response', array('response' => $response));

                if (!is_array($response)) {
                    $this->getLogger()->warning($response);

                    //assume an error
                    $error = array(
                        'code'    => 500,
                        'message' => $response
                    );
                }

                // @deprecated support for 2.6.0 to be removed in 3.0
                if (!isset($response['errors']) && isset($response['error']) && isset($response['error_description'])) {
                    $message = $response['error'].': '.$response['error_description'];

                    $this->getLogger()->warning($message);

                    $error = array(
                        'code'    => 403,
                        'message' => $message
                    );
                }
            } catch (\Exception $e) {
                $this->getLogger()->error('Failed connecting to Mautic API: '.$e->getMessage(), array('trace' => $e->getTraceAsString()));

                $error = array(
                    'code'    => $e->getCode(),
                    'message' => $e->getMessage()
                );
            }
        }

        if (!empty($error)) {
            return array(
                'errors' => array($error),
                // @deprecated 2.6.0 to be removed 3.0
                'error'  => $error
            );
        } elseif (!empty($response['errors'])) {
            $this->getLogger()->error('Mautic API returned errors: '.var_export($response['errors']));
        }

        return $response;
    }

    /**
     * Returns HTTP response info
     *
     * @return array
     */
    public function getResponseInfo()
    {
        return $this->auth->getResponseInfo();
    }

    /**
     * Returns HTTP response headers
     *
     * @return array
     */
    public function getResponseHeaders()
    {
        return $this->auth->getResponseHeaders();
    }

    /**
     * Returns Mautic version from the HTTP response headers
     * (the header exists since Mautic 2.4.0)
     *
     * @return string|null if not known
     */
    public function getMauticVersion()
    {
        $headers = $this->auth->getResponseHeaders();

        if (isset($headers['Mautic-Version'])) {
            return $headers['Mautic-Version'];
        }

        return null;
    }

    /**
     * Get a single item
     *
     * @param int $id
     *
     * @return array|mixed
     */
    public function get($id)
    {
        return $this->makeRequest("{$this->endpoint}/$id");
    }

    /**
     * Get a list of items
     *
     * @param string $search
     * @param int    $start
     * @param int    $limit
     * @param string $orderBy
     * @param string $orderByDir
     * @param bool   $publishedOnly
     * @param bool   $minimal
     *
     * @return array|mixed
     */
    public function getList($search = '', $start = 0, $limit = 0, $orderBy = '', $orderByDir = 'ASC', $publishedOnly = false, $minimal = false)
    {
        $parameters = array(
            'search'        => $search,
            'start'         => $start,
            'limit'         => $limit,
            'orderBy'       => $orderBy,
            'orderByDir'    => $orderByDir,
            'publishedOnly' => $publishedOnly,
            'minimal'       => $minimal
        );

        $parameters = array_filter($parameters);

        return $this->makeRequest($this->endpoint, $parameters);
    }

    /**
     * Proxy function to getList with $publishedOnly set to true
     *
     * @param string $search
     * @param int    $start
     * @param int    $limit
     * @param string $orderBy
     * @param string $orderByDir
     *
     * @return array|mixed
     */
    public function getPublishedList($search = '', $start = 0, $limit = 0, $orderBy = '', $orderByDir = 'ASC')
    {
        return $this->getList($search, $start, $limit, $orderBy, $orderByDir, true);
    }

    /**
     * Create a new item (if supported)
     *
     * @param array $parameters
     *
     * @return array|mixed
     */
    public function create(array $parameters)
    {
        $supported = $this->isSupported('create');

        return (true === $supported) ? $this->makeRequest($this->endpoint.'/new', $parameters, 'POST') : $supported;
    }

    /**
     * Create a batch of new items
     *
     * @param array $parameters
     *
     * @return array|mixed
     */
    public function createBatch(array $parameters)
    {
        $supported = $this->isSupported('createBatch');

        return (true === $supported) ? $this->makeRequest($this->endpoint.'/batch/new', $parameters, 'POST') : $supported;
    }

    /**
     * Edit an item with option to create if it doesn't exist
     *
     * @param int   $id
     * @param array $parameters
     * @param bool  $createIfNotExists = false
     *
     * @return array|mixed
     */
    public function edit($id, array $parameters, $createIfNotExists = false)
    {
        $method    = $createIfNotExists ? 'PUT' : 'PATCH';
        $supported = $this->isSupported('edit');

        return (true === $supported) ? $this->makeRequest($this->endpoint.'/'.$id.'/edit', $parameters, $method) : $supported;
    }

    /**
     * Edit a batch of items
     *
     * @param array $parameters
     * @param bool  $createIfNotExists
     *
     * @return array|mixed
     */
    public function editBatch(array $parameters, $createIfNotExists = false)
    {
        $method    = $createIfNotExists ? 'PUT' : 'PATCH';
        $supported = $this->isSupported('editBatch');

        return (true === $supported) ? $this->makeRequest($this->endpoint.'/batch/edit', $parameters, $method) : $supported;
    }

    /**
     * Delete an item
     *
     * @param $id
     *
     * @return array|mixed
     */
    public function delete($id)
    {
        $supported = $this->isSupported('delete');

        return (true === $supported) ? $this->makeRequest($this->endpoint.'/'.$id.'/delete', array(), 'DELETE') : $supported;
    }

    /**
     * Delete a batch of items
     *
     * @param $ids
     *
     * @return array|mixed
     */
    public function deleteBatch(array $ids)
    {
        $supported = $this->isSupported('deleteBatch');

        return (true === $supported) ? $this->makeRequest($this->endpoint.'/batch/delete', array('ids' => $ids), 'DELETE') : $supported;
    }

    /**
     * Returns a not supported error
     *
     * @param string $action
     *
     * @return array
     */
    protected function actionNotSupported($action)
    {
        $error = array(
            'code'    => 500,
            'message' => "$action is not supported at this time."
        );

        return array(
            'errors' => array(
                $error
            ),
            // @deprecated 2.6.0 to be removed in 3.0
            'error'  => $error
        );
    }

    /**
     * Verify that a default endpoint is supported by the API
     *
     * @param $action
     *
     * @return bool
     */
    protected function isSupported($action)
    {
        if (empty($this->endpointsSupported) || in_array($action, $this->endpointsSupported)) {
            return true;
        }

        return $this->actionNotSupported($action);
    }
}
