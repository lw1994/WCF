<?php
namespace wcf\data\package\installation\plugin;
use wcf\data\AbstractDatabaseObjectAction;
use wcf\data\devtools\project\DevtoolsProject;
use wcf\system\cache\CacheHandler;
use wcf\system\devtools\pip\DevtoolsPackageInstallationDispatcher;
use wcf\system\devtools\pip\DevtoolsPip;
use wcf\system\devtools\pip\IIdempotentPackageInstallationPlugin;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\exception\UserInputException;
use wcf\system\package\plugin\IPackageInstallationPlugin;
use wcf\system\package\SplitNodeException;
use wcf\system\search\SearchIndexManager;
use wcf\system\version\VersionTracker;
use wcf\system\WCF;

/**
 * Executes package installation plugin-related actions.
 * 
 * @author	Alexander Ebert
 * @copyright	2001-2017 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	WoltLabSuite\Core\Data\Package\Installation\Plugin
 * 
 * @method	PackageInstallationPlugin		create()
 * @method	PackageInstallationPluginEditor[]	getObjects()
 * @method	PackageInstallationPluginEditor		getSingleObject()
 */
class PackageInstallationPluginAction extends AbstractDatabaseObjectAction {
	/**
	 * @inheritDoc
	 */
	protected $className = PackageInstallationPluginEditor::class;
	
	/**
	 * @inheritDoc
	 */
	protected $requireACP = ['invoke'];
	
	/**
	 * @var DevtoolsPip
	 */
	public $devtoolsPip;
	
	/**
	 * @var PackageInstallationPlugin
	 */
	public $packageInstallationPlugin;
	
	/**
	 * @var DevtoolsProject
	 */
	public $project;
	
	public function validateInvoke() {
		if (!ENABLE_DEVELOPER_TOOLS || !WCF::getSession()->getPermission('admin.configuration.package.canInstallPackage')) {
			throw new PermissionDeniedException();
		}
		
		$this->readString('pluginName');
		$this->readInteger('projectID');
		$this->readString('target');
		
		$this->project = new DevtoolsProject($this->parameters['projectID']);
		if (!$this->project->projectID || $this->project->validate() !== '') {
			throw new UserInputException('projectID');
		}
		
		$this->packageInstallationPlugin = new PackageInstallationPlugin($this->parameters['pluginName']);
		if (!$this->packageInstallationPlugin->pluginName) {
			throw new UserInputException('pluginName');
		}
		
		$this->devtoolsPip = new DevtoolsPip($this->packageInstallationPlugin);
		$targets = $this->devtoolsPip->getTargets($this->project);
		if (!in_array($this->parameters['target'], $targets)) {
			throw new UserInputException('target');
		}
	}
	
	public function invoke() {
		$dispatcher = new DevtoolsPackageInstallationDispatcher($this->project);
		/** @var IIdempotentPackageInstallationPlugin $pip */
		$pip = new $this->packageInstallationPlugin->className($dispatcher, [
			'value' => $this->devtoolsPip->getInstructionValue($this->project, $this->parameters['target'])
		]);
		
		try {
			$pip->update();
		}
		catch (SplitNodeException $e) {
			throw new \RuntimeException("PIP '{$this->packageInstallationPlugin->pluginName}' is not allowed to throw a 'SplitNodeException'.");
		}
		
		// clear cache
		
		// TODO: use a central method instead!
		
		// create search index tables
		SearchIndexManager::getInstance()->createSearchIndices();
		
		VersionTracker::getInstance()->createStorageTables();
		
		CacheHandler::getInstance()->flushAll();
	}
}
