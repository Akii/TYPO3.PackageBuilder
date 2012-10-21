<?php
namespace TYPO3\PackageBuilder\Controller;

/*                                                                        *
 * This script belongs to the Flow package "TYPO3.PackageBuilder".       *
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use \TYPO3\Flow\Configuration\ConfigurationManager as ConfigurationManager;

/**
 * Standard controller for the TYPO3.PackageBuilder package
 *
 * @Flow\Scope("singleton")
 */
class PackageController extends \TYPO3\Ice\Controller\StandardController {


	/**
	 * @var \TYPO3\PackageBuilder\Persistence\PersistenceManagerInterface
	 * @Flow\Inject
	 */
	#protected $persistenceManager;

	/**
	 * @var \TYPO3\Flow\Package\PackageManagerInterface
	 * @Flow\Inject
	 */
	protected $packageManager;

	/**
	 * @var \TYPO3\PackageBuilder\Configuration\AbstractConfigurationManager
	 */
	protected $packageConfigurationManager;


	/**
	 * @var \TYPO3\Flow\Log\SystemLoggerInterface
	 * @Flow\Inject
	 *
	 */
	protected $logger;

	/**
	 * @var string
	 */
	protected $targetFrameWork = 'Flow';


	/**
	 * @var \TYPO3\PackageBuilder\Service\AbstractCodeGenerator
	 */

	protected $codeGenerator;

	/**
	 * @var \TYPO3\PackageBuilder\Domain\Repository\AbstractPackageRepository
	 */
	protected $packageRepository;

	/**
	 * Initialize action, and merge settings if needed
	 *
	 * @return void
	 */
	public function initializeAction() {

		if ($this->request->hasArgument('frameWork')) {
			$this->targetFrameWork = $this->request->getArgument('frameWork');
		} elseif (!isset($this->settings['codeGeneration']['frameWork']) OR $this->settings['codeGeneration']['frameWork'] == 'Flow') {
			$this->targetFrameWork = 'Flow';
		} else {
			$this->targetFrameWork = 'TYPO3';
		}
		$this->settings['codeGeneration'] = \TYPO3\Flow\Utility\Arrays::arrayMergeRecursiveOverrule(
			$this->settings['codeGeneration'],
			$this->settings['codeGeneration'][$this->targetFrameWork]
		);
		if (!empty($this->settings['extendIceSettings'])) {
			$this->settings = \TYPO3\Flow\Utility\Arrays::arrayMergeRecursiveOverrule(
				$this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'TYPO3.Ice'),
				$this->settings
			);
		}
		$this->logger =  \TYPO3\Flow\Log\LoggerFactory::create('PackageBuilderLogger','\\TYPO3\\PackageBuilder\\Log\\FileLogger','\\TYPO3\\Flow\\Log\\Backend\\FileBackend', $this->settings['log']['backendOptions']);

		if($this->actionMethodName == 'createAction') {
			if(!class_exists('\\TYPO3\\PackageBuilder\\Service\\' . $this->targetFrameWork. '\\CodeGenerator')) {
				throw new \TYPO3\PackageBuilder\Exception\MissingComponentException('No CodeGenerator class for target framework ' . $this->targetFrameWork . ' found');
			}
			$this->codeGenerator = $this->objectManager->get('\\TYPO3\\PackageBuilder\\Service\\' . $this->targetFrameWork. '\\CodeGenerator');
			$this->packageRepository = $this->objectManager->get('\\TYPO3\\PackageBuilder\\Domain\\Repository\\' . $this->targetFrameWork. '\\PackageRepository');
			$this->packageFactory = $this->objectManager->get('\\TYPO3\\PackageBuilder\\Service\\' . $this->targetFrameWork. '\\PackageFactory');
			$this->packageConfigurationManager = $this->objectManager->get('\\TYPO3\\PackageBuilder\\Configuration\\' . $this->targetFrameWork. '\\ConfigurationManager');

			$this->codeGenerator->injectLogger($this->logger);
			$this->packageRepository->injectLogger($this->logger);
			$this->packageFactory->injectLogger($this->logger);
		}
	}


	/**
	 * Shows the Interface to create a new package
	 */
	public function newAction() {
		$this->indexAction();
	}

	/**
	 * list all packages
	 */
	public function listAction() {

	}

	/**
	 * create a new package based on the configuration
	 */
	public function createAction() {
		try {
			$settingsFile = $this->settings['codeGeneration']['packagesDir']. 'test.json';
			if(!file_exists($settingsFile)){
				die($settingsFile);
			}
			define('PATH_typo3conf',$this->settings['codeGeneration']['packagesDir']);
			$packageBuildConfiguration = $this->packageConfigurationManager->getConfigurationFromFile($settingsFile);
			/**
			$validationConfigurationResult = $this->extensionValidator->validateConfigurationFormat($packageBuildConfiguration);
			if (!empty($validationConfigurationResult['warnings'])) {
				$confirmationRequired = $this->handleValidationWarnings($validationConfigurationResult['warnings']);
				if (!empty($confirmationRequired)) {
					return $confirmationRequired;
				}
			}
			*/
			$package = $this->packageFactory->create($packageBuildConfiguration);
			//\TYPO3\Flow\var_dump($package);

		}
		catch (Exception $e) {
			throw $e;
		}
		file_put_contents($this->settings['log']['backendOptions']['logFileURL'],'');

		//\TYPO3\Flow\var_dump($this->settings['codeGeneration']);
		$this->settings['packageConfiguration'] = $this->packageConfigurationManager->getPackageConfiguration($package->getKey());
		$this->codeGenerator->injectSettings($this->settings);

		$package->setBaseDir($this->settings['codeGeneration']['packagesDir']);
		$this->codeGenerator->build($package);

		die('Package "' . $package->getName() . '" successfull created!<br />Logfile output:<br /><pre>' . file_get_contents($this->settings['log']['backendOptions']['logFileURL']). '</pre>');
	}

}

?>