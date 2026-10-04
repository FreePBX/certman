<?php
// vim: set ai ts=4 sw=4 ft=php:
//	License for all code of this FreePBX module can be found in the license file inside the module directory
namespace FreePBX\modules\Certman;

use Analogic\ACME\ClientInterface;
use RuntimeException;

/**
 * ACME HTTP client for private / self-hosted Let's Encrypt-compatible servers.
 *
 * It mirrors \Analogic\ACME\Client (the default lescript transport) but adds the
 * pieces needed to talk to an internal ACME server using http-01:
 *
 *   - An explicit directory URL. lescript always requests the directory through
 *     the relative path '/directory', but private servers expose it elsewhere
 *     (step-ca: /acme/<provisioner>/directory, Pebble: /dir, ...). When a
 *     directory URL is configured we substitute it for that relative request.
 *   - An optional custom CA bundle (CURLOPT_CAINFO) for servers presenting a
 *     certificate signed by a private CA.
 *   - An optional insecure mode that skips TLS verification for self-signed
 *     setups. Off by default; only used when the operator explicitly opts in.
 *
 * Keeping this in the module (rather than patching vendor/) means a composer
 * update of analogic/lescript will not wipe the customisation.
 */
#[\AllowDynamicProperties]
class AcmeHttpClient implements ClientInterface
{
	private $lastCode;
	private $lastHeader;
	private $base;
	private $directoryUrl;
	private $caBundle;
	private $insecure;

	/**
	 * @param string      $base         Origin (scheme://host[:port]) used for any relative request
	 * @param string|null $directoryUrl Full ACME directory URL (substituted for lescript's '/directory')
	 * @param string|null $caBundle     Path to a PEM CA bundle that signs the ACME server certificate
	 * @param bool        $insecure     Skip TLS verification of the ACME server (self-signed)
	 */
	public function __construct($base, $directoryUrl = null, $caBundle = null, $insecure = false)
	{
		$this->base = rtrim((string)$base, '/');
		$this->directoryUrl = $directoryUrl;
		$this->caBundle = $caBundle;
		$this->insecure = (bool)$insecure;
	}

	private function curl($method, $url, $data = null)
	{
		// lescript always fetches the directory via the relative path '/directory'.
		// Private servers expose it on non-standard paths, so honour the explicit URL.
		if ($url === '/directory' && !empty($this->directoryUrl)) {
			$url = $this->directoryUrl;
		}

		$headers = array('Accept: application/json', 'Content-Type: application/jose+json');
		$handle = curl_init();
		curl_setopt($handle, CURLOPT_URL, preg_match('~^http~', $url) ? $url : $this->base.$url);
		curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_HEADER, true);

		if (!empty($this->caBundle)) {
			curl_setopt($handle, CURLOPT_CAINFO, $this->caBundle);
		}
		if ($this->insecure) {
			curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
		}

		switch ($method) {
			case 'GET':
				break;
			case 'POST':
				curl_setopt($handle, CURLOPT_POST, true);
				curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
				break;
		}
		$response = curl_exec($handle);

		if (curl_errno($handle)) {
			throw new RuntimeException('Curl: '.curl_error($handle));
		}

		$header_size = curl_getinfo($handle, CURLINFO_HEADER_SIZE);

		$header = substr($response, 0, $header_size);
		$body = substr($response, $header_size);

		$this->lastHeader = $header;
		$this->lastCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);

		if ($this->lastCode >= 400 && $this->lastCode < 600) {
			throw new RuntimeException($this->lastCode."\n".$body);
		}

		$data = json_decode($body, true);
		return $data === null ? $body : $data;
	}

	public function post($url, $data)
	{
		return $this->curl('POST', $url, $data);
	}

	public function get($url)
	{
		return $this->curl('GET', $url);
	}

	public function getLastNonce()
	{
		if (preg_match('~Replay-Nonce: (.+)~i', $this->lastHeader, $matches)) {
			return trim($matches[1]);
		}

		throw new RuntimeException("We don't have nonce");
	}

	public function getLastLocation()
	{
		if (preg_match('~Location: (.+)~i', $this->lastHeader, $matches)) {
			return trim($matches[1]);
		}
		return null;
	}

	public function getLastCode()
	{
		return $this->lastCode;
	}

	public function getLastLinks()
	{
		preg_match_all('~Link: <(.+)>;rel="up"~', $this->lastHeader, $matches);
		return $matches[1];
	}
}
