<?php namespace DoubleOptIn\ClientApi\Client\Commands\Responses;

use DoubleOptIn\ClientApi\Client\Commands\Responses\Limiter\RateLimit;
use DoubleOptIn\ClientApi\Client\Commands\Responses\Models\Action;
use Guzzle\Http\Message\Response;
use stdClass;

/**
 * Class CommandResponse
 *
 * Base class for command responses
 *
 * @package DoubleOptIn\ClientApi\Client\Commands\Responses
 */
class CommandResponse
{
	/**
	 * original response
	 *
	 * @var Response
	 */
	private $response;

	/**
	 * limiter
	 *
	 * @var RateLimit
	 */
	private $limiter;

	/**
	 * return json decoded \stdClass
	 *
	 * @var stdClass
	 */
	private $json;

	/**
	 * @param Response $response
	 */
	public function __construct(Response $response)
	{
		$this->response = $response;

		$this->limiter = new RateLimit($response->getHeaders());
	}

	/**
	 * returns decoded object
	 *
	 * @return null|stdClass
	 */
	protected function decoded()
	{
		if ($this->json === null) {
			/** @var \Guzzle\Http\Message\Header $contentType */
			$contentType = (string)$this->response->getHeader('content-type');
			if ($contentType === 'application/json')
				$this->json = json_decode($this->response->getBody(true));
		}

		return $this->json;
	}

	/**
	 * returns limiter
	 *
	 * @return RateLimit
	 */
	public function limiter()
	{
		return $this->limiter;
	}

	/**
	 * command failed?
	 *
	 * @return bool
	 */
	public function fails()
	{
		return $this->response->getStatusCode() >= 300;
	}

	/**
	 * returns the error message
	 *
	 * @return string
	 */
	public function errorMessage()
	{
		if ($this->decoded() === null) {
			throw new \RuntimeException('No result from server.');
		}

		$decoded = $this->decoded();
		if (isset($decoded->error))
			return sprintf('%s (%s)', $decoded->error->message, $decoded->error->code);

		$message = sprintf('%s (%s)', $decoded->message, $this->statusCode());

		if (isset($this->decoded()->errors)) {
			$errors = $this->decoded()->errors;

			$message .= $this->resolveAttributes($errors);
		}

		return $message;
	}

	/**
	 * returns status code
	 *
	 * @return int
	 */
	public function statusCode()
	{
		return $this->response->getStatusCode();
	}

	/**
	 * returns a list of all actions
	 *
	 * @return array|Action[]
	 */
	public function all()
	{
		$result = array();

		$data = $this->data();
		if ( ! is_array($data))
			$data = array($data);

		foreach ($data as $entry)
		{
			$result[] = Action::createFromStdClass($entry);
		}

		return $result;
	}

	/**
	 * returns the action or null
	 *
	 * @return Action|null
	 */
	public function action()
	{
		$action = $this->data();

		if (is_array($action))
			return null;

		return Action::createFromStdClass($action);
	}

	/**
	 * returns decoded data
	 *
	 * @return array|\stdClass
	 */
	public function data()
	{
		return $this->decoded()->data;
	}

	/**
	 * returns decoded meta data
	 *
	 * @return array
	 */
	public function meta()
	{
		return $this->decoded()->meta;
	}

	/**
	 * resolves all known attributes
	 *
	 * @param stdClass $stdClass
	 *
	 * @return string
	 */
	private function resolveAttributes(stdClass $stdClass)
	{
		$attributes = array('action', 'hash', 'scope', 'data');

		$message = '';
		foreach ($attributes as $attribute) {
			$message .= $this->resolveAttribute($stdClass, $attribute);
		}

		return $message;
	}

	/**
	 * resolves an attribute
	 *
	 * @param stdClass $stdClass
	 * @param string $attribute
	 *
	 * @return string
	 */
	private function resolveAttribute(stdClass $stdClass, $attribute)
	{
		if (isset($stdClass->$attribute))
			return PHP_EOL . sprintf('  %s: %s', $attribute, implode(', ', $stdClass->$attribute));

		return '';
	}
}