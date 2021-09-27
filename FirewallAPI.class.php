<?php
// vim: set ai ts=4 sw=4 ft=php:
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2014 Schmooze Com Inc.
//
namespace FreePBX\modules\Certman;

/**
 * Implements a trivial API for use with Certman
 */
class FirewallAPI {

	/** Is firewall available on this machine? */
	private $fw = false;

	/** Firewall object */
	private $fwobj;

	public function __construct() {
		// Is firewall enabled and active?
		try {
			$this->fwobj = \FreePBX::Firewall();
			$this->fw = $this->fwobj->isEnabled();
		} catch (\Exception $e) {
			// Firewall not active, or not enabled, don't do anything
			return;
		}
	}

	/**
	 * Is firewall available on this machine?
	 *
	 * @return bool
	 */
	public function isAvailable() {
		return $this->fw;
	}

	/**
	 * enableLERules
	 *
	 * @return void
	 */
	public function enableLeRules(){
		if($this->fw){
			$this->fwobj->enableLeRules();
		}
	}

	/**
	 * disableLERules
	 *
	 * @return void
	 */
	public function disableLeRules(){
		if($this->fw){
			$this->fwobj->disableLeRules();
		}
	}

	/**
	 * getLeOptions
	 *
	 * @return array
	 */

	public function getLeOptions(){
		$serviceenabled = false;
		$lerules = false;
		$leports = array();
		$fwzones = array();
		$hints = array();
		$brand = \FreePBX::Config()->get("DASHBOARD_FREEPBX_BRAND");

		// firewall module installed/enabled in module admin, and enabled in firewall settings
		if($this->fw){
			$as = $this->fwobj->getAdvancedSettings();
			$fwservice = $this->fwobj->getService('letsencrypt');
			if ($as['lefilter'] == "enabled") {
				$lerules = true;
			}
			if (isset($fwservice['fw'][0]['port'])) {
				$serviceenabled = true;
				$leports[] = $fwservice['fw'][0]['port'];
				if (!$fwservice['disabled']) {
					$fwzones = $fwservice['zones'];
				}
			} else {
				$allservices = $this->fwobj->getServices();
				unset($allservices['custom']); // ignore custom services
				foreach ($allservices as $services) {
					foreach($services as $service) {
						$s = $this->fwobj->getService($service);
						if (!$s['disabled']) {
							foreach ($s['fw'] as $fw) {
								if ($fw['leport']) {
									$leports[] = $fw['port'];
								}
							}
						}
					}
				}
			}
			$cli = php_sapi_name() == 'cli';
			if ($this->isAvailable()) {
				$asurl = '<a href="?display=firewall&page=advanced&tab=settings" class="alert-link"><em>';
				$servicesurl = '<a href="?display=firewall&page=services&tab=servicestab"  class="alert-link"><em>';
				$closeanchor = '</em></a>';
				if (!$lerules) {
					if ($cli) {
						$hints[] = _("<options=bold>Responsive LetsEncrypt Rules</> are not enabled. Enabling <options=bold>Responsive LetsEncrypt Rules</> is recommended. Enable at the command line with '<info>fwconsole firewall lerules enable</>' or within the web interface at <info>Connectivity->Firewall->Advanced->Advanced Settings</>.");
					} else {
						$hints[] = sprintf(_("%sResponsive LetsEncrypt Rules%s are not enabled. Enabling %sResponsive LetsEncrypt Rules%s is recommended."), $asurl, $closeanchor, $asurl, $closeanchor, $asurl, $closeanchor);
					}
				}
				if ($serviceenabled && !$lerules && !in_array("external", $fwzones)) {
					if ($cli) {
						$hints[] = _("Internet Zone access is not enabled for the LetsEncrypt Service, make sure public access to the service is available via port 80.\n\nUse <options=bold>Responsive LetsEncrypt Rules</> (recommended) or enable Internet Zone access for the LetsEncypt Service in the web interface at <info>Connectivity->Firewall->Services</info>.");
					} else {
						$hints[] = sprintf(_("Internet Zone access is not enabled for the LetsEncrypt Service, make sure public access to the service is available via port 80. Enable %sResponsive LetsEncrypt Rules%s (recommended) or manually enable LetsEncrypt Service Internet Zone access at %sConnectivity->Firewall->Services%s."),$asurl, $closeanchor, $servicesurl, $closeanchor);
					}
				}
				if (!in_array(80, $leports)) {
					if ($serviceenabled) {
						$hints[] = sprintf(_("The LetsEncrypt Service is listening on port %s. Using a custom port other than 80 is not officially supported.\n\nThe LetsEncrypt servers only send challenge queries to port 80. Certificate requests will fail unless your network redirects incoming port 80 requests to port %s."), $leports[0], $leports[0]);
					} else {
						$hints[] = sprintf(_("%s http services are not listening on port 80. LetsEncrypt using a port other than 80 is not officially supported.\n\nThe LetsEncrypt servers only send challenge queries to port 80. %s http services are currently listening on %s %s. Certificate requests will fail unless your network redirects incoming port 80 requests to a listening http port."), $brand, $brand, count($leports)==1?_("port"):_("ports"), preg_replace("/,([^,]+)$/", _(" and") . "$1", implode(', ',$leports)));
					}
				}
			}
		}

		// firewall module installed/enabled in module admin, but disabled in firewall settings
		if (isset($this->fwobj) && !$this->fw) {
			$hints[] = sprintf(_("The %s Firewall is not enabled."), $brand);
		}

		// firewall not installed or not enabled
		if (!$this->fw) {
			$hints[] = _("The LetsEncrypt servers only send challenge queries to port 80. Certificate requests will fail if public access via port 80 is not available.");
		}

		return array('service' => $serviceenabled, 'ports' => $leports, 'fwzones' => $fwzones, 'lerules' => $lerules, 'hints' => $hints);
	}
}
