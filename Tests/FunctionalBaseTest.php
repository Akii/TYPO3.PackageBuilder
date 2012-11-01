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

use TYPO3\Flow\Annotations as Flow;

/**
 * @package
 * @author Nico de Haen
 */

abstract class FunctionalBaseTest extends \TYPO3\Flow\Tests\FunctionalTestCase{

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
	 * @Flow\Inject
	 */
	protected $parser;

	/**
	 * @var \TYPO3\ParserApi\Service\Printer
	 * @Flow\Inject
	 */
	protected $printer;

	/**
	 * @var \TYPO3\PackageBuilder\Service\TYPO3\CodeGenerator
	 * @Flow\Inject
	 */
	protected $codeGenerator;

	/**
	 * @var \TYPO3\PackageBuilder\Service\TYPO3\ClassBuilder
	 * @Flow\Inject
	 */
	protected $classBuilder;

	/**
	 * @var \TYPO3\PackageBuilder\Configuration\TYPO3\ConfigurationManager
	 * @Flow\Inject
	 */
	protected $configurationManager;

	/*
	 * @var \TYPO3\PackageBuilder\Domain\Model\Extension
	 */
	protected $extension;


	public function setUp(){
		parent::setUp();
		if(!defined('PATH_typo3conf')) {
			define('PATH_typo3conf','');
		}
		$path = dirname(__FILE__);
		$pathParts = explode('Tests',$path);
		$this->packagePath = $pathParts[0];
		$this->fixturesPath = $this->packagePath . 'Tests/Fixtures/';
		if(!class_exists('PHPParser_Parser')) {
			// needed to run tests local
			\TYPO3\ParserApi\AutoLoader::register();
		}
		$this->parser = new \TYPO3\ParserApi\Service\Parser();
		$this->printer = new \TYPO3\ParserApi\Service\Printer();
		\org\bovigo\vfs\vfsStream::setup('testDir');
		$this->testDir = \org\bovigo\vfs\vfsStream::url('testDir') . '/';
			// only temporary for debugging
		$this->testDir = $this->fixturesPath . 'tmp/';

		$this->extension = $this->getMock('TYPO3\PackageBuilder\Domain\Model\Extension',array('getExtensionDir'));
		$extensionKey = 'dummy';
		//$dummyExtensionDir = PATH_typo3conf.'ext/extension_builder/Tests/Examples/'.$extensionKey.'/';

		$this->extension->setExtensionKey($extensionKey);
		$this->extension->setNamespace('DUMMY\\Dummy');
		$this->extension->expects(
			$this->any())
				->method('getExtensionDir')
				->will($this->returnValue($this->testDir));

		$this->classBuilder = $this->objectManager->get('\\TYPO3\\PackageBuilder\\Service\\TYPO3\\ClassBuilder');
		$this->classBuilder->initialize($this->extension, FALSE);
		$this->inject($this->classBuilder, 'logger', new \TYPO3\Flow\Log\Logger());
		$this->codeGenerator = $this->objectManager->get('\\TYPO3\\PackageBuilder\\Service\\TYPO3\CodeGenerator');
		$this->configurationManager = $this->objectManager->get('\\TYPO3\\PackageBuilder\\Configuration\\TYPO3\\ConfigurationManager');
		$this->inject($this->codeGenerator, 'codeTemplateRootPath', $this->packagePath . 'Resources/Private/CodeTemplates/TYPO3/');
		$this->inject($this->codeGenerator, 'extension', $this->extension);
		$this->inject($this->codeGenerator, 'editModeEnabled', FALSE);
		$this->inject($this->classBuilder, 'codeGenerator', $this->codeGenerator);


	}

	public function tearDown() {
		if(is_dir($this->testDir)) {
			//\TYPO3\Flow\Utility\Files::emptyDirectoryRecursively($this->testDir);
			//rmdir($this->testDir);
		}
	}

	protected function parseFile($fileName) {
			// allow absolute and relative paths
		$fileName = str_replace($this->testDir, '', $fileName);
		$classFilePath = $this->testDir . $fileName;
		$classFileObject = $this->parser->parseFile($classFilePath)->getFirstClass();
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
