<?php

namespace Symbiote\ApiWrapper;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Control\HTTPResponse;

class ApiWrapperController extends Controller
{
    private static array $versions = [
        'v1' => [
            'docs'      => EndpointsController::class,
            'watch'     => ServiceWrapperController::class,
        ]
    ];

    private static array $cors = [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Headers' => 'Authorization, Content-Type',
        'Access-Control-Allow-Methods' => 'GET,POST,PUT,DELETE',
    ];

    public function handleRequest(HTTPRequest $request): HTTPResponse
    {
        // for OPTIONS requests, ie CORS preflight,
        // respond with what's expected
        if (strtolower($request->httpMethod()) === 'options') {
            $response = HTTPResponse::create();
            return $this->addCorsHeaders($response);
        }

        $apiVersions = self::config()->get('versions');
        if(is_array($apiVersions)) {
            foreach ($apiVersions as $version => $handlers) {
                $res = $request->match($version, true);
                if ($res) {
                    foreach ($handlers as $segment => $cls) {
                        if ($request->match($segment, true)) {
                            $controller = is_string($cls) ? Injector::inst()->create($cls) : $cls;
                            if ($controller) {
                                if (method_exists($controller, 'setSegment')) {
                                    $controller->setSegment($segment);
                                }

                                $response = $controller->handleRequest($request);
                                return $this->addCorsHeaders($response);
                            }
                        }
                    }
                }
            }
        }

        return parent::handleRequest($request);
    }

    public function index()
    {
        return $this->httpError(404);
    }

    protected function addCorsHeaders(HTTPResponse $response): HTTPResponse
    {
        $cors = $this->config()->get('cors');
        if (is_array($cors) && $cors !== []) {
            foreach ($cors as $header => $val) {
                $response->addHeader($header, $val);
            }
        }

        return $response;
    }
}
