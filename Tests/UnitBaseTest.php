<?php
namespace TYPO3\PackageBuilder\Tests;
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Nico de Haen <mail@ndh-websolutions.de>
 *  All rights reserved
 *
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * @package
 * @author Nico de Haen
 */

abstract class UnitBaseTest extends \TYPO3\Flow\Tests\BaseTestCase{

	/**
	 * @var string
	 */
	protected $testDir = '';

	protected $fixturesPath = '';

	/**
	 * @var string
	 */
	protected $packagePath = '';

	/**
	 * @var \TYPO3\ParserApi\Service\Parser
	 */
	protected $parser;

	/**
	 * @var \TYPO3\ParserApi\Service\Printer
	 */
	protected $printer;

	/**
	 * @var ObjectManagerInterface
	 */
	protected $objectManager;

	/**
	 * @var \TYPO3\PackageBuilder\Service\TYPO3\CodeGenerator
	 * @Flow\Inject
	 */
	protected $codeGenerator;

	/**
	 * @var \TYPO3\PackageBuilder\Service\TYPO3\ClassBuilder
	 */
	protected $classBuilder;


	public function setUp(){
		if(!defined('PATH_typo3conf')) {
			define('PATH_typo3conf','');
		}
		$path = dirname(__FILE__);
		$pathParts = explode('Tests',$path);
		$this->packagePath = $pathParts[0];
		$this->fixturesPath = $this->packagePath . 'Tests/Fixtures/';
		if(!class_exists('PHPParser_Parser')) {
			// needed to run tests local
			//$packageManager = new \TYPO3\FLOW3\Package\PackageManager();
			//$parserPackage = $packageManager->getPackage('TYPO3.ParserApi');
			//include_once($parserPackage->getClassesPath() . 'Autoloader.php');
			$pathParts = explode('TYPO3.PackageBuilder',$path);
			//include_once($pathParts[0] . 'Typo3.ParserApi/Classes/Autoloader.php');
			\TYPO3\ParserApi\AutoLoader::register();
		}
		$this->parser = new \TYPO3\ParserApi\Service\Parser();
		$this->printer = new \TYPO3\ParserApi\Service\Printer();
		//\org\bovigo\vfs\vfsStream::register();
		//\org\bovigo\vfs\vfsStream::setRoot(new \org\bovigo\vfs\vfsStreamDirectory('testDirectory'));
		\org\bovigo\vfs\vfsStream::setup('testDir');
		$dummyExtensionDir = \org\bovigo\vfs\vfsStream::url('testDir') . '/';

		$dummyExtensionDir = $this->fixturesPath . 'tmp/';

		$this->extension = $this->getMock('TYPO3\PackageBuilder\Domain\Model\Extension',array('getExtensionDir'));
		$extensionKey = 'dummy';
		//$dummyExtensionDir = PATH_typo3conf.'ext/extension_builder/Tests/Examples/'.$extensionKey.'/';

		$this->extension->setExtensionKey($extensionKey);
		$this->extension->expects(
			$this->any())
				->method('getExtensionDir')
				->will($this->returnValue($dummyExtensionDir));

		$this->objectManager = new \TYPO3\Flow\Object\ObjectManager(new \TYPO3\Flow\Core\ApplicationContext('Testing'));
		$this->codeGenerator = $this->getMock($this->buildAccessibleProxy('\\TYPO3\\PackageBuilder\\Service\\TYPO3\CodeGenerator'), array('dummy'));

		$configurationManager = new \TYPO3\PackageBuilder\Configuration\TYPO3\ConfigurationManager();

		$this->classBuilder = new \TYPO3\PackageBuilder\Service\TYPO3\ClassBuilder();
		$this->classBuilder->initialize($this->extension, FALSE);
		$this->inject($this->codeGenerator, 'classBuilder', $this->classBuilder);
		$this->inject($this->codeGenerator, 'objectManager', $this->objectManager);
		$this->inject($this->codeGenerator, 'codeTemplateRootPath', $this->packagePath . 'Resources/Private/CodeTemplates/TYPO3/');
		$this->inject($this->codeGenerator, 'extension', $this->extension);
		$this->inject($this->codeGenerator, 'editModeEnabled', FALSE);
		$this->inject($this->classBuilder, 'codeGenerator', $this->codeGenerator);
		$this->inject($this->classBuilder, 'packageConfigurationManager', $configurationManager);
		$this->inject($this->classBuilder, 'logger', new \TYPO3\Flow\Log\Logger());
		$this->classBuilder->initialize($this->extension, FALSE);

		//$this->classBuilder->injectConfigurationManager($configurationManager);

		/**
		$yamlParser = new Tx_ExtensionBuilder_Utility_SpycYAMLParser();
		$settings = $yamlParser->YAMLLoadString(file_get_contents(PATH_typo3conf.'ext/extension_builder/Tests/Examples/Settings/settings1.yaml'));
		$this->extension->setSettings($settings);
		$configurationManager = t3lib_div::makeInstance('Tx_ExtensionBuilder_Configuration_ConfigurationManager');
		$this->roundTripService =  $this->getMock($this->buildAccessibleProxy('Tx_ExtensionBuilder_Service_RoundTrip'),array('dummy'));
		$this->classBuilder = t3lib_div::makeInstance('Tx_ExtensionBuilder_Service_ClassBuilder');
		$this->classBuilder->injectConfigurationManager($configurationManager);
		*/

	}

	public function tearDown() {
		/**
		$tmpFiles = \TYPO3\FLOW3\Utility\Files::readDirectoryRecursively($this->testDir);
		foreach($tmpFiles as $tmpFile) {
			//unlink($this->testDir . $tmpFile);
		}
		 * */
		//rmdir($this->testDir);
	}

	protected function parseFile($fileName) {
		$classFilePath = $this->packagePath . 'Tests/Fixtures/' . $fileName;
		$classFileObject = $this->parser->parseFile($classFilePath);
		return $classFileObject;
	}

	/**
	 * Helper function
	 * @param $name
	 * @param $entity
	 * @param $aggregateRoot
	 * @return object Tx_ExtensionBuilder_Domain_Model_DomainObject
	 */
	protected function buildDomainObject($name, $entity = false, $aggregateRoot = false){
		$domainObject = $this->getMock($this->buildAccessibleProxy('\\TYPO3\\PackageBuilder\\Domain\\Model\\DomainObject'), array('dummy'));
		$domainObject->setExtension($this->extension);
		$domainObject->setName($name);
		$domainObject->setEntity($entity);
		$domainObject->setAggregateRoot($aggregateRoot);
		if($aggregateRoot){
			$defaultActions = array('list','show','new','create','edit','update','delete');
			foreach($defaultActions as $actionName){
				$action = $this->objectManager->get('\\TYPO3\\PackageBuilder\\Domain\\Model\\DomainObject\\Action');
				$action->setName($actionName);
				if($actionName == 'deleted'){
					$action->setNeedsTemplate = false;
				}
				$domainObject->addAction($action);
			}
		}
		return $domainObject;
	}

}
