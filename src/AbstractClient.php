<?php declare(strict_types=1);

namespace DCarbone\PHPConsulAPI;

/*
   Copyright 2016-2020 Daniel Carbone (daniel.p.carbone@gmail.com)

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
*/

use DCarbone\Go\HTTP;
use DCarbone\Go\Time;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class AbstractClient
 * @package DCarbone\PHPConsulAPI
 */
abstract class AbstractClient
{
    private const headerConsulPrefix = 'X-Consul-';
    private const headerConsulIndex = self::headerConsulPrefix . 'Index';
    private const headerConsulContentHash = self::headerConsulPrefix . 'ContentHash';
    private const headerConsulKnownLeader = self::headerConsulPrefix . 'KnownLeader';
    private const headerConsulLastContact = self::headerConsulPrefix . 'LastContact';
    private const headerConsulTranslateAddresses = self::headerConsulPrefix . 'Translate-Addresses';
    private const headerCache = 'X-Cache';

    /** @var Config */
    protected $config;

    /**
     * AbstractConsulClient constructor.
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        // TODO: Clone config?
        $this->config = clone $config;
    }

    /**
     * @return \DCarbone\PHPConsulAPI\Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * @param \DCarbone\PHPConsulAPI\RequestResponse $r
     * @param int $desiredCode
     * @return \DCarbone\PHPConsulAPI\RequestResponse
     */
    protected function requireCode(RequestResponse $r, int $desiredCode): RequestResponse
    {
        // If a previous error occurred, just return as-is.
        if (null !== $r->Err) {
            return $r;
        }

        // If we have any kind of response...
        if (null !== $r->Response) {
            // If this is a response...
            if ($r->Response instanceof ResponseInterface) {
                // Get the response code...
                $actualCode = $r->Response->getStatusCode();

                // If $desiredCode, move right along
                if ($desiredCode === $actualCode) {
                    return $r;
                }

                // Otherwise, return error
                $r->Err = new Error(
                    sprintf(
                        '%s - Non-%d response seen.  Response code: %d.  Message: %s',
                        get_class($this),
                        $desiredCode,
                        $actualCode,
                        $r->Response->getReasonPhrase()
                    )
                );
            } else {
                $r->Err = new Error(
                    sprintf(
                        '%s - Expected response to be instance of \\Psr\\Message\\ResponseInterface, %s seen.',
                        get_class($this),
                        is_object($r->Response) ? get_class($r->Response) : gettype($r->Response)
                    )
                );
            }
        }

        return $r;
    }

    /**
     * @param \DCarbone\PHPConsulAPI\RequestResponse $r
     * @return \DCarbone\PHPConsulAPI\RequestResponse
     */
    protected function requireOK(RequestResponse $r): RequestResponse
    {
        return $this->requireCode($r, HTTP\StatusOK);
    }

    /**
     * @param \DCarbone\PHPConsulAPI\Request $r
     * @return \DCarbone\PHPConsulAPI\RequestResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function do(Request $r): RequestResponse
    {
        $rt = microtime(true);
        $response = null;
        $err = null;

        try {
            // If we actually have a client defined...
            if (isset($this->config->HttpClient) && $this->config->HttpClient instanceof ClientInterface) {
                $response = $this->config->HttpClient->send(
                    $r->toPsrRequest(),
                    $this->config->getGuzzleRequestOptions($r)
                );
            } // Otherwise, throw error to be caught below
            else {
                throw new \RuntimeException('Unable to execute query as no HttpClient has been defined.');
            }
        } catch (\Exception $e) {
            // If there has been an exception of any kind, catch it and create Error object
            $err = new Error(
                sprintf(
                    '%s - Error seen while executing "%s".  Message: "%s"',
                    get_class($this),
                    $r->getUri(),
                    $e->getMessage()
                )
            );
        }

        // Calculate duration and move along whatever response and error we see (if any)
        return new RequestResponse(intval((microtime(true) - $rt)), $response, $err);
    }

    /**
     * @param \DCarbone\Go\Time\Duration $duration
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param \Psr\Http\Message\UriInterface $uri
     * @return \DCarbone\PHPConsulAPI\QueryMeta
     */
    protected function buildQueryMeta(
        Time\Duration $duration,
        ResponseInterface $response,
        UriInterface $uri
    ): QueryMeta
    {
        $qm = new QueryMeta();

        $qm->RequestTime = $duration;
        $qm->RequestUrl = (string)$uri;

        if ('' !== ($h = $response->getHeaderLine(self::headerConsulIndex))) {
            $qm->LastIndex = (int)$h;
        }

        $qm->LastContentHash = $response->getHeaderLine(self::headerConsulContentHash);

        // note: do not need to check both as guzzle response compares headers insensitively
        if ('' !== ($h = $response->getHeaderLine(self::headerConsulKnownLeader))) {
            $qm->KnownLeader = (bool)$h;
        }
        // note: do not need to check both as guzzle response compares headers insensitively
        if ('' !== ($h = $response->getHeaderLine(self::headerConsulLastContact))) {
            $qm->LastContact = (int)$h;
        }

        if ('' !== ($h = $response->getHeaderLine(self::headerConsulTranslateAddresses))) {
            $qm->AddressTranslationEnabled = (bool)$h;
        }

        if ('' !== ($h = $response->getHeaderLine(self::headerCache))) {
            $qm->CacheAge = Time::Duration(intval($h, 10) * Time::Second);
        }

        return $qm;
    }

    /**
     * @param \DCarbone\Go\Time\Duration $duration
     * @return \DCarbone\PHPConsulAPI\WriteMeta
     */
    protected function buildWriteMeta(Time\Duration $duration): WriteMeta
    {
        $wm = new WriteMeta();
        $wm->RequestTime = $duration;

        return $wm;
    }

    /**
     * @param \Psr\Http\Message\StreamInterface $body
     * @return \DCarbone\PHPConsulAPI\DecodedBody
     */
    protected function decodeBody(StreamInterface $body): DecodedBody
    {
        $data = @json_decode((string)$body, true);

        if (JSON_ERROR_NONE === json_last_error()) {
            return new DecodedBody($data, null);
        }

        return new DecodedBody(
            null,
            new Error(
                sprintf(
                    '%s - Unable to parse response as JSON.  Message: %s',
                    get_class($this),
                    json_last_error_msg()
                )
            )
        );
    }
}