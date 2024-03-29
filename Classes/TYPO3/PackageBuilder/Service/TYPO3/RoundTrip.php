<?php
namespace TYPO3\PackageBuilder\Service\TYPO3;
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010 Nico de Haen <mail@ndh-websolutions.de>
 *  All rights reserved
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
 * Performs all changes that are required to adapt the
 * existing classes and methods to the changes in the configurations
 *
 * @package PackageBuilder
 * @Flow\Scope("singleton")
 *
 */
class RoundTrip extends \TYPO3\PackageBuilder\Service\AbstractRoundTrip {

	const SPLIT_TOKEN = '## EXTENSION BUILDER DEFAULTS END TOKEN - Everything BEFORE this line is overwritten with the defaults of the extension builder';
	const OLD_SPLIT_TOKEN = '## KICKSTARTER DEFAULTS END TOKEN - Everything BEFORE this line is overwritten with the defaults of the kickstarter';
	protected $previousExtension = NULL;

	/**
	 * if an extension was renamed this property keeps the original extensionDirectory
	 * otherwise it is set to the current extensionDir
	 *
	 * @var string path
	 */
	protected $previousExtensionDirectory;

	/**
	 * the directory of the current extension
	 *
	 * @var string path
	 */
	protected $packageDirectory;

	/**
	 * if an extension was renamed this property keeps the old key
	 * otherwise it is set to the current extensionKey
	 *
	 * @var string
	 */
	protected $previousExtensionKey;

	/**
	 * @var array
	 */
	protected $oldDomainObjects = array();

	/**
	 * @var array
	 */
	protected $renamedDomainObjects = array();

	/**
	 * @var \TYPO3\ParserApi\Service\Parser
	 * @Flow\Inject
	 */
	protected $classParser;

	/**
	 * @var \TYPO3\PackageBuilder\Configuration\TYPO3\ConfigurationManager
	 */
	protected $configurationManager;

	/**
	 * was the extension renamed?
	 *
	 * @var boolean
	 */
	protected $packageRenamed = FALSE;

	/**
	 * @var \TYPO3\PackageBuilder\Log\FileLogger
	 */
	protected $logger;


	/**
	 * If a JSON file is found in the extensions directory the previous version
	of the extension is build to compare it with the new configuration coming
	from the extension builder input
	 *
	 * @param \TYPO3\PackageBuilder\Domain\Model\Extension $extension
	 */
	public function initialize(\TYPO3\PackageBuilder\Domain\Model\Extension $extension) {
		$this->package = $extension;
		$this->packageDirectory = $this->package->getExtensionDir();
		$this->extClassPrefix = $this->package->getExtensionKey();
		$this->settings = $this->configurationManager->getExtensionBuilderSettings();
		// defaults
		$this->previousExtensionDirectory = $this->packageDirectory;
		$this->previousExtensionKey = $this->package->getExtensionKey();
		if ($extension->isRenamed()) {
			$this->previousExtensionDirectory = $extension->getPreviousExtensionDirectory();
			$this->previousExtensionKey = $extension->getOriginalExtensionKey();
			$this->packageRenamed = TRUE;
			$this->logger->log((('Extension renamed: ' . $this->previousExtensionKey) . ' => ') . $this->package->getExtensionKey(), 'extension_builder', 1, array('$previousExtensionDirectory ' => $this->previousExtensionDirectory));
		}
		// Rename the old kickstarter.json file to ExtensionBuilder.json
		if (file_exists($this->previousExtensionDirectory . 'kickstarter.json')) {
			rename($this->previousExtensionDirectory . 'kickstarter.json', $this->previousExtensionDirectory . \TYPO3\PackageBuilder\Configuration\ConfigurationManager::EXTENSION_BUILDER_SETTINGS_FILE);
		}
		if (file_exists($this->previousExtensionDirectory . \TYPO3\PackageBuilder\Configuration\ConfigurationManager::EXTENSION_BUILDER_SETTINGS_FILE)) {
			$extensionSchemaBuilder = \t3lib_div::makeInstance('TYPO3\\PackageBuilder\\Service\\ExtensionSchemaBuilder');
			$jsonConfig = $this->configurationManager->getExtensionBuilderConfiguration($this->previousExtensionKey, FALSE);
			$this->logger->log(('old JSON:' . $this->previousExtensionDirectory) . 'ExtensionBuilder.json', 'extension_builder', 0, $jsonConfig);
			$this->previousExtension = $extensionSchemaBuilder->build($jsonConfig);
			$oldDomainObjects = $this->previousExtension->getDomainObjects();
			foreach ($oldDomainObjects as $oldDomainObject) {
				$this->oldDomainObjects[$oldDomainObject->getUniqueIdentifier()] = $oldDomainObject;
				$this->logger->log((('Old domain object: ' . $oldDomainObject->getName()) . ' - ') . $oldDomainObject->getUniqueIdentifier(), 'extension_builder');
			}
			// now we store all renamed domainObjects in an array to enable detection of renaming in
			// relationProperties (property->getForeignModel)
			// we also build an array with the new unique identifiers to detect deleting of domainObjects
			$currentDomainsObjects = array();
			foreach ($this->package->getDomainObjects() as $domainObject) {
				if (isset($this->oldDomainObjects[$domainObject->getUniqueIdentifier()])) {
					if ($this->oldDomainObjects[$domainObject->getUniqueIdentifier()]->getName() != $domainObject->getName()) {
						$renamedDomainObjects[$domainObject->getUniqueIdentifier()] = $domainObject;
					}
				}
				$currentDomainsObjects[$domainObject->getUniqueIdentifier()] = $domainObject;
			}
			// remove deleted objects
			foreach ($oldDomainObjects as $oldDomainObject) {
				if (!isset($currentDomainsObjects[$oldDomainObject->getUniqueIdentifier()])) {
					$this->removeDomainObjectFiles($oldDomainObject);
				}
			}
		}
	}

	/**
	 * This method is the main part of the roundtrip functionality
	It looks for a previous version of the current domain object and
	parses the existing class file for that domain model
	compares all properties and methods with the previous version.

	Methods are either removed/added or updated according to the new property names
	 *
	 * @param \TYPO3\PackageBuilder\Domain\Model\DomainObject $domainObject The new domain object
	 * @return \TYPO3\PackageBuilder\Domain\Model\ClassObject\ClassObject OR NULL
	 */
	public function getDomainModelClass(\TYPO3\PackageBuilder\Domain\Model\DomainObject $currentDomainObject) {
		if (isset($this->oldDomainObjects[$currentDomainObject->getUniqueIdentifier()])) {
			$this->logger->log('domainObject identified:' . $currentDomainObject->getName(), 'extension_builder', 0);
			$oldDomainObject = $this->oldDomainObjects[$currentDomainObject->getUniqueIdentifier()];
			$extensionDir = $this->previousExtensionDirectory;
			$fileName = (CodeGenerator::getFolderForClassFile($extensionDir, 'Model', FALSE) . $oldDomainObject->getName()) . '.php';
			if (file_exists($fileName)) {
				// import the classObject from the existing file
				$className = $oldDomainObject->getClassName();
				$this->classObject = $this->classParser->parseFile($fileName)->getFirstClass();
				//t3lib_div::devlog('Model class methods','extension_builder',0,$this->classObject->getMethods());
				if ($oldDomainObject->getName() != $currentDomainObject->getName() || $this->packageRenamed) {
					if (!$this->packageRenamed) {
						$this->logger->log((('domainObject renamed. old: ' . $oldDomainObject->getName()) . ' new: ') . $currentDomainObject->getName(), 'extension_builder');
					}
					$newClassName = $currentDomainObject->getClassName();
					$this->classObject->setName($newClassName);
					$this->classObject->setFileName($currentDomainObject->getName() . '.php');
					$this->cleanUp(CodeGenerator::getFolderForClassFile($extensionDir, 'Model'), $oldDomainObject->getName() . '.php');
					$this->cleanUp($extensionDir . 'Configuration/TCA/', $oldDomainObject->getName() . '.php');
				}
				$this->updateModelClassProperties($oldDomainObject, $currentDomainObject);
				$newActions = array();
				foreach ($currentDomainObject->getActions() as $newAction) {
					$newActions[$newAction->getName()] = $newAction;
				}
				$oldActions = $oldDomainObject->getActions();
				if ((empty($newActions) && !$currentDomainObject->isAggregateRoot()) && (!empty($oldActions) || $oldDomainObject->isAggregateRoot())) {
					// remove the controller
					$this->cleanUp(CodeGenerator::getFolderForClassFile($extensionDir, 'Controller'), $oldDomainObject->getName() . 'Controller.php');
				}
				// the parent class settings configuration
				$parentClass = $currentDomainObject->getParentClass();
				$oldParentClass = $oldDomainObject->getParentClass();
				if (!empty($parentClass)) {
					if ($oldParentClass != $parentClass) {
						// the parent class was just new added
						$this->classObject->setParentClass($parentClass);
					}
				} else {
					if (!empty($oldParentClass)) {
						// the old object had a parent class setting, but it's removed now
						if ($currentDomainObject->isEntity()) {
							$parentClass = $this->configurationManager->getParentClassForEntityObject($this->package->getExtensionKey());
						} else {
							$parentClass = $this->configurationManager->getParentClassForValueObject($this->package->getExtensionKey());
						}
						$this->classObject->setParentClass($parentClass);
					}
				}
				if ($currentDomainObject->isEntity() && !$oldDomainObject->isEntity()) {
					// the object type was changed in the modeler
					$this->classObject->setParentClass($this->configurationManager->getParentClassForEntityObject($this->package->getExtensionKey()));
				} elseif (!$currentDomainObject->isEntity() && $oldDomainObject->isEntity()) {
					// the object type was changed in the modeler
					$this->classObject->setParentClass($this->configurationManager->getParentClassForValueObject($this->package->getExtensionKey()));
				}
				return $this->classObject;
			} else {
				$this->logger->log('class file didn\'t exist:' . $fileName, 'extension_builder', 0);
			}
		} else {
			$this->logger->log('domainObject not identified:' . $currentDomainObject->getName(), 'extension_builder', 0, $this->oldDomainObjects);
			$fileName = (CodeGenerator::getFolderForClassFile($this->packageDirectory, 'Model', FALSE) . $currentDomainObject->getName()) . '.php';
			if (file_exists($fileName)) {
				// import the classObject from the existing file
				$className = $currentDomainObject->getClassName();
				$this->classObject = $this->classParser->parseFile($fileName)->getFirstClass();
				$this->logger->log(('class file found:' . $currentDomainObject->getName()) . '.php', 'extension_builder', 0, (array) $this->classObject->getAnnotations());
				return $this->classObject;
			}
		}
		return NULL;
	}

	/**
	 * @param \TYPO3\PackageBuilder\Domain\Model\DomainObject $domainObject
	 * @return \TYPO3\PackageBuilder\Domain\Model\ClassObject\ClassObject OR NULL
	 */
	public function getControllerClass(\TYPO3\PackageBuilder\Domain\Model\DomainObject $currentDomainObject) {
		$extensionDir = $this->previousExtensionDirectory;
		if (isset($this->oldDomainObjects[$currentDomainObject->getUniqueIdentifier()])) {
			$oldDomainObject = $this->oldDomainObjects[$currentDomainObject->getUniqueIdentifier()];
			$fileName = (CodeGenerator::getFolderForClassFile($extensionDir, 'Controller', FALSE) . $oldDomainObject->getName()) . 'Controller.php';
			if (file_exists($fileName)) {
				$this->logger->log('existing controller class:' . $fileName, 'extension_builder', 0);
				include_once $fileName;
				$className = $oldDomainObject->getControllerName();
				$this->classObject = $this->classParser->parse($className);
				//t3lib_div::devlog('Controller class methods','extension_builder',0,$this->classObject->getMethods());
				if ($oldDomainObject->getName() != $currentDomainObject->getName() || $this->packageRenamed) {
					$this->mapOldControllerToCurrentClassObject($oldDomainObject, $currentDomainObject);
				} else {
					if ($oldDomainObject->isAggregateRoot() && !$currentDomainObject->isAggregateRoot()) {
						$injectMethodName = ('inject' . \t3lib_div::lcfirst($oldDomainObject->getName())) . 'Repository';
						$this->classObject->removeMethod($injectMethodName);
					}
				}
				$newActions = array();
				foreach ($currentDomainObject->getActions() as $newAction) {
					$newActions[$newAction->getName()] = $newAction;
				}
				$oldActions = $oldDomainObject->getActions();
				if (isset($this->oldDomainObjects[$currentDomainObject->getUniqueIdentifier()])) {
					// now we remove old action methods
					foreach ($oldActions as $oldAction) {
						if (!isset($newActions[$oldAction->getName()])) {
							// an action was removed
							$this->classObject->removeMethod($oldAction->getName() . 'Action');
							$this->logger->log('Action method removed:' . $oldAction->getName(), 'extension_builder', 0, $this->classObject->getMethods());
						}
					}
				}
				return $this->classObject;
			} else {
				$this->logger->log('class file didn\'t exist:' . $fileName, 'extension_builder', 2);
				return NULL;
			}
		} else {
			$fileName = (CodeGenerator::getFolderForClassFile($extensionDir, 'Controller', FALSE) . $currentDomainObject->getName()) . 'Controller.php';
			if (file_exists($fileName)) {
				include_once $fileName;
				$className = $currentDomainObject->getControllerName();
				$this->classObject = $this->classParser->parse($className);
				return $this->classObject;
			} else {
				$this->logger->log('No existing controller class:' . $fileName, 'extension_builder', 2);
			}
		}
		$this->logger->log('No existing controller class:' . $currentDomainObject->getName(), 'extension_builder', 2);
		return NULL;
	}

	/**
	 * If a domainObject was renamed
	 *
	 * @param \TYPO3\PackageBuilder\Domain\Model\DomainObject $oldDomainObject
	 * @param \TYPO3\PackageBuilder\Domain\Model\DomainObject $currentDomainObject
	 * @return void
	 */
	protected function mapOldControllerToCurrentClassObject(\TYPO3\PackageBuilder\Domain\Model\DomainObject $oldDomainObject, \TYPO3\PackageBuilder\Domain\Model\DomainObject $currentDomainObject) {
		$extensionDir = $this->previousExtensionDirectory;
		$newClassName = $currentDomainObject->getControllerName();
		$newName = $currentDomainObject->getName();
		$oldName = $oldDomainObject->getName();
		$this->classObject->setName($newClassName);
		$this->classObject->setDescription($this->replaceUpperAndLowerCase($oldName, $newName, $this->classObject->getDescription()));
		if ($oldDomainObject->isAggregateRoot()) {
			// should we keep the old properties comments and tags?
			$this->classObject->removeProperty(\t3lib_div::lcfirst($oldName) . 'Repository');
			$injectMethodName = ('inject' . $oldName) . 'Repository';
			if ($currentDomainObject->isAggregateRoot()) {
				// update the initializeAction method body
				$initializeMethod = $this->classObject->getMethod('initializeAction');
				if ($initializeMethod != NULL) {
					$initializeMethodBody = $initializeMethod->getBody();
					$newInitializeMethodBody = str_replace($oldDomainObject->getDomainRepositoryClassName(), $currentDomainObject->getDomainRepositoryClassName(), $initializeMethodBody);
					$newInitializeMethodBody = str_replace(\t3lib_div::lcfirst($oldName) . 'Repository', \t3lib_div::lcfirst($newName) . 'Repository', $newInitializeMethodBody);
					$initializeMethod->setBody($newInitializeMethodBody);
					$this->classObject->setMethod($initializeMethod);
				}
				$injectMethod = $this->classObject->getMethod($injectMethodName);
				if ($injectMethod != NULL) {
					$this->classObject->removeMethod($injectMethodName);
					$newInjectMethodBody = str_replace(\t3lib_div::lcfirst($oldName), \t3lib_div::lcfirst($newName), $injectMethod->getBody());
					$injectMethod->setBody($newInjectMethodBody);
					$injectMethod->setTag('param', (($currentDomainObject->getDomainRepositoryClassName() . ' $') . $newName) . 'Repository');
					$injectMethod->setName(('inject' . $newName) . 'Repository');
					$parameter = new \TYPO3\PackageBuilder\Domain\Model\ClassObject\MethodParameter(\t3lib_div::lcfirst($newName) . 'Repository');
					$parameter->setTypeHint($currentDomainObject->getDomainRepositoryClassName());
					$parameter->setPosition(0);
					$injectMethod->replaceParameter($parameter);
					$this->classObject->setMethod($injectMethod);
				}
				foreach ($oldDomainObject->getActions() as $action) {
					// here we have to update all the occurences of domain object names in action methods
					$actionMethod = $this->classObject->getMethod($action->getName() . 'Action');
					if ($actionMethod != NULL) {
						$actionMethodBody = $actionMethod->getBody();
						$newActionMethodBody = str_replace(\t3lib_div::lcfirst($oldName) . 'Repository', \t3lib_div::lcfirst($newName) . 'Repository', $actionMethodBody);
						$actionMethod->setBody($newActionMethodBody);
						$actionMethod->setTag('param', $currentDomainObject->getClassName());
						$parameters = $actionMethod->getParameters();
						foreach ($parameters as &$parameter) {
							if (strpos($parameter->getTypeHint(), $oldDomainObject->getClassName()) > -1) {
								$parameter->setTypeHint($currentDomainObject->getClassName());
								$parameter->setName($this->replaceUpperAndLowerCase($oldName, $newName, $parameter->getName()));
								$actionMethod->replaceParameter($parameter);
							}
						}
						$tags = $actionMethod->getTags();
						foreach ($tags as $tagName => $tagValue) {
							$tags[$tagName] = $this->replaceUpperAndLowerCase($oldName, $newName, $tagValue);
						}
						$actionMethod->setTags($tags);
						$actionMethod->setDescription($this->replaceUpperAndLowerCase($oldName, $newName, $actionMethod->getDescription()));
						//TODO: this is not safe. Could rename unwanted variables
						$actionMethod->setBody($this->replaceUpperAndLowerCase($oldName, $newName, $actionMethod->getBody()));
						$this->classObject->setMethod($actionMethod);
					}
				}
			} else {
				$this->classObject->removeMethod('initializeAction');
				$this->classObject->removeMethod($injectMethodName);
				$this->cleanUp(CodeGenerator::getFolderForClassFile($extensionDir, 'Repository'), $oldName . 'Repository.php');
			}
		}
		$this->classObject->setFileName($newName . 'Controller.php');
		$this->cleanUp(CodeGenerator::getFolderForClassFile($extensionDir, 'Controller'), $oldName . 'Controller.php');
	}

	/**
	 * @param \TYPO3\PackageBuilder\Domain\Model\DomainObject $domainObject
	 * @return Tx_ExtensionBuilder_Domain_Model_Class OR NULL
	 */
	public function getRepositoryClass(\TYPO3\PackageBuilder\Domain\Model\DomainObject $currentDomainObject) {
		$extensionDir = $this->previousExtensionDirectory;
		if (isset($this->oldDomainObjects[$currentDomainObject->getUniqueIdentifier()])) {
			$oldDomainObject = $this->oldDomainObjects[$currentDomainObject->getUniqueIdentifier()];
			$fileName = (CodeGenerator::getFolderForClassFile($extensionDir, 'Repository', FALSE) . $oldDomainObject->getName()) . 'Repository.php';
			if (file_exists($fileName)) {
				include_once $fileName;
				$className = $oldDomainObject->getDomainRepositoryClassName();
				$this->classObject = $this->classParser->parse($className);
				if ($oldDomainObject->getName() != $currentDomainObject->getName() || $this->packageRenamed) {
					$newClassName = $currentDomainObject->getDomainRepositoryClassName();
					$this->classObject->setName($newClassName);
					$this->classObject->setFileName($currentDomainObject->getName() . '_Repository.php');
					$this->cleanUp(CodeGenerator::getFolderForClassFile($extensionDir, 'Repository'), $oldDomainObject->getName() . 'Repository.php');
				}
				return $this->classObject;
			} else {
				$this->logger->log('class file didn\'t exist:' . $fileName, 'extension_builder', 2);
			}
		} else {
			$fileName = (CodeGenerator::getFolderForClassFile($extensionDir, 'Repository', FALSE) . $currentDomainObject->getName()) . 'Repository.php';
			if (file_exists($fileName)) {
				include_once $fileName;
				$className = $currentDomainObject->getDomainRepositoryClassName();
				$this->classObject = $this->classParser->parse($className);
				$this->logger->log('existing Repository class:' . $fileName, 'extension_builder', 0, (array) $this->classObject);
				return $this->classObject;
			}
		}
		$this->logger->log('No existing Repository class:' . $currentDomainObject->getName(), 'extension_builder', 2);
		return NULL;
	}

	/**
	 * Compare the properties of each object and remove/update
	the properties and the related methods
	 *
	 * @param \TYPO3\PackageBuilder\Domain\Model\DomainObject $oldDomainObject
	 * @param \TYPO3\PackageBuilder\Domain\Model\DomainObject $newDomainObject
	 */
	protected function updateModelClassProperties(\TYPO3\PackageBuilder\Domain\Model\DomainObject $oldDomainObject, \TYPO3\PackageBuilder\Domain\Model\DomainObject $newDomainObject) {
		$newProperties = array();
		foreach ($newDomainObject->getProperties() as $property) {
			$newProperties[$property->getUniqueIdentifier()] = $property;
		}
		//t3lib_div::devlog('properties new:','extension_builder',0,$newProperties);
		// compare all old properties with new ones
		foreach ($oldDomainObject->getProperties() as $oldProperty) {
			if (isset($newProperties[$oldProperty->getUniqueIdentifier()])) {
				$newProperty = $newProperties[$oldProperty->getUniqueIdentifier()];
				// relation type changed
				if ($oldProperty->isAnyToManyRelation() != $newProperty->isAnyToManyRelation()) {
					$this->logger->log((('property type changed:' . $oldProperty->getName()) . ' ') . $newProperty->getName(), 'extension_builder', 0, $newProperties);
					// remove old methods since we won't convert getter and setter methods to add/remove methods
					if ($oldProperty->isAnyToManyRelation()) {
						$this->classObject->removeMethod('add' . ucfirst(\TYPO3\PackageBuilder\Utility\Inflector::singularize($oldProperty->getName())));
						$this->classObject->removeMethod('remove' . ucfirst(\TYPO3\PackageBuilder\Utility\Inflector::singularize($oldProperty->getName())));
					}
					$this->classObject->removeMethod('get' . ucfirst($oldProperty->getName()));
					$this->classObject->removeMethod('set' . ucfirst($oldProperty->getName()));
					if ($oldProperty->isBoolean()) {
						$this->classObject->removeMethod('is' . ucfirst(\TYPO3\PackageBuilder\Utility\Inflector::singularize($oldProperty->getName())));
					}
					$this->classObject->removeProperty($oldProperty->getName());
					$this->logger->log('property type changed => removed old property:' . $oldProperty->getName(), 'extension_builder', 1);
				} else {
					$this->updateProperty($oldProperty, $newProperty);
					$newDomainObject->getPropertyByName($newProperty->getName())->setNew(FALSE);
				}
			} else {
				$this->removePropertyAndRelatedMethods($oldProperty);
			}
		}
	}

	/**
	 * Removes all related methods, if a property was removed
	 *
	 * @param \TYPO3\PackageBuilder\Domain\Model\DomainObject\AbstractProperty $propertyToRemove
	 * @return void
	 */
	protected function removePropertyAndRelatedMethods($propertyToRemove) {
		$propertyName = $propertyToRemove->getName();
		$this->classObject->removeProperty($propertyName);
		if ($propertyToRemove->isAnyToManyRelation()) {
			$this->classObject->removeMethod('add' . ucfirst(\TYPO3\PackageBuilder\Utility\Inflector::singularize($propertyName)));
			$this->classObject->removeMethod('remove' . ucfirst(\TYPO3\PackageBuilder\Utility\Inflector::singularize($propertyName)));
			$this->logger->log(('Methods removed: ' . 'add') . ucfirst(\TYPO3\PackageBuilder\Utility\Inflector::singularize($propertyName)), 'extension_builder');
		}
		$this->classObject->removeMethod('get' . ucfirst($propertyName));
		$this->classObject->removeMethod('set' . ucfirst($propertyName));
		if ($propertyToRemove->isBoolean()) {
			$this->classObject->removeMethod('is' . ucfirst($propertyName));
		}
		$this->logger->log(('Methods removed: ' . 'get') . ucfirst($propertyName), 'extension_builder');
	}

	/**
	 * Rename a property and update comment (var tag and description)
	 *
	 * @param Tx_ExtensionBuilder_Domain_Model_AbstractDomainObjectProperty $oldProperty
	 * @param Tx_ExtensionBuilder_Domain_Model_AbstractDomainObjectProperty $newProperty
	 * @return void
	 */
	protected function updateProperty($oldProperty, $newProperty) {
		$classProperty = $this->classObject->getProperty($oldProperty->getName());
		if ($classProperty) {
			$classProperty->setName($newProperty->getName());
			$classProperty->setTag('var', $newProperty->getTypeForComment());
			$newDescription = $newProperty->getDescription();
			if (empty($newDescription) || $newDescription == $newProperty->getName()) {
				$newDescription = str_replace($oldProperty->getName(), $newProperty->getName(), $classProperty->getDescription());
			}
			$classProperty->setDescription($newDescription);
			$this->classObject->removeProperty($oldProperty->getName());
			$this->classObject->setProperty($classProperty);
			if ($this->relatedMethodsNeedUpdate($oldProperty, $newProperty)) {
				$this->updatePropertyRelatedMethods($oldProperty, $newProperty);
			}
		}
	}

	/**
	 * @param Tx_ExtensionBuilder_Domain_Model_AbstractDomainObjectProperty $oldProperty
	 * @param Tx_ExtensionBuilder_Domain_Model_AbstractDomainObjectProperty $newProperty
	 * @return boolean
	 */
	protected function relatedMethodsNeedUpdate($oldProperty, $newProperty) {
		if ($this->packageRenamed) {
			return TRUE;
		}
		if ($newProperty->getName() != $oldProperty->getName()) {
			$this->logger->log((('property renamed:' . $oldProperty->getName()) . ' ') . $newProperty->getName(), 'extension_builder', 0);
			return TRUE;
		}
		if ($newProperty->getTypeForComment() != $this->updateExtensionKey($oldProperty->getTypeForComment())) {
			$this->logger->log((('property type changed from ' . $this->updateExtensionKey($oldProperty->getTypeForComment())) . ' to ') . $newProperty->getTypeForComment(), 'extension_builder', 0);
			return TRUE;
		}
		if ($newProperty->isRelation()) {
			// if only the related domain object was renamed
			if ($this->getForeignClassName($newProperty) != $this->updateExtensionKey($oldProperty->getForeignClassName())) {
				$this->logger->log((('related domainObject was renamed:' . $this->updateExtensionKey($oldProperty->getForeignClassName())) . ' ->') . $this->getForeignClassName($newProperty), 'extension_builder');
				return TRUE;
			}
		}
	}

	/**
	 * replace occurences of the old extension key with the new one
	used to compare classNames
	 *
	 * @param $stringToParse
	 * @return unknown_type
	 */
	protected function updateExtensionKey($stringToParse) {
		if (!$this->packageRenamed) {
			return $stringToParse;
		}
		return str_replace(('_' . ucfirst($this->previousExtensionKey)) . '_', ('_' . ucfirst($this->package->getExtensionKey())) . '_', $stringToParse);
	}

	/**
	 * @param Tx_ExtensionBuilder_Domain_Model_AbstractDomainObjectProperty $oldProperty
	 * @param Tx_ExtensionBuilder_Domain_Model_AbstractDomainObjectProperty $newProperty
	 * @return void
	 */
	protected function updatePropertyRelatedMethods($oldProperty, $newProperty) {
		if ($newProperty->isAnyToManyRelation()) {
			$this->updateMethod($oldProperty, $newProperty, 'add');
			$this->updateMethod($oldProperty, $newProperty, 'remove');
		}
		$this->updateMethod($oldProperty, $newProperty, 'get');
		$this->updateMethod($oldProperty, $newProperty, 'set');
		if ($newProperty->isBoolean()) {
			$this->updateMethod($oldProperty, $newProperty, 'is');
		}
		if ($newProperty->getTypeForComment() != $this->updateExtensionKey($oldProperty->getTypeForComment())) {
			if ($oldProperty->isBoolean() && !$newProperty->isBoolean()) {
				$this->classObject->removeMethod(\TYPO3\PackageBuilder\Utility\Tools::getMethodName($oldProperty, 'is'));
				$this->logger->log('Method removed:' . \TYPO3\PackageBuilder\Utility\Tools::getMethodName($oldProperty, 'is'), 'extension_builder', 1, $this->classObject->getMethods());
			}
		}
	}

	/**
	 * update means renaming of method name, parameter and replacing parameter names in method body
	 *
	 * @param Tx_ExtensionBuilder_Domain_Model_AbstractDomainObjectProperty $oldProperty
	 * @param Tx_ExtensionBuilder_Domain_Model_AbstractDomainObjectProperty $newProperty
	 * @param string $methodType get,set,add,remove,is
	 * @return void
	 */
	protected function updateMethod($oldProperty, $newProperty, $methodType) {
		$oldMethodName = \TYPO3\PackageBuilder\Utility\Tools::getMethodName($oldProperty, $methodType);
		// the method to be merged
		$mergedMethod = $this->classObject->getMethod($oldMethodName);
		if (!$mergedMethod) {
			// no previous version of the method exists
			return;
		}
		$newMethodName = \TYPO3\PackageBuilder\Utility\Tools::getMethodName($newProperty, $methodType);
		$this->logger->log((('updateMethod:' . $oldMethodName) . '=>') . $newMethodName, 'extension_builder');
		if ($oldProperty->getName() != $newProperty->getName()) {
			// rename the method
			$mergedMethod->setName($newMethodName);
			$oldMethodBody = $mergedMethod->getBody();
			$oldComment = $mergedMethod->getDocComment();
			$newMethodBody = $this->replacePropertyNameInMethodBody($oldProperty->getName(), $newProperty->getName(), $oldMethodBody);
			$mergedMethod->setBody($newMethodBody);
		}
		// update the method parameters
		$methodParameters = $mergedMethod->getParameters();
		if (!empty($methodParameters)) {
			$parameterTags = $mergedMethod->getTagsValues('param');
			foreach ($methodParameters as $methodParameter) {
				$oldParameterName = $methodParameter->getName();
				if ($oldParameterName == \TYPO3\PackageBuilder\Utility\Tools::getParameterName($oldProperty, $methodType)) {
					$newParameterName = \TYPO3\PackageBuilder\Utility\Tools::getParameterName($newProperty, $methodType);
					$methodParameter->setName($newParameterName);
					$newMethodBody = $this->replacePropertyNameInMethodBody($oldParameterName, $newParameterName, $mergedMethod->getBody());
					$mergedMethod->setBody($newMethodBody);
				}
				$typeHint = $methodParameter->getTypeHint();
				if ($typeHint) {
					if ($oldProperty->isRelation() && $typeHint == $oldProperty->getForeignClassName()) {
						$methodParameter->setTypeHint($this->updateExtensionKey($this->getForeignClassName($newProperty)));
					}
				}
				$parameterTags[$methodParameter->getPosition()] = \TYPO3\PackageBuilder\Utility\Tools::getParamTag($newProperty, $methodType);
				$mergedMethod->replaceParameter($methodParameter);
			}
			$mergedMethod->setTag('param', $parameterTags);
		}
		$returnTagValue = trim($mergedMethod->getTagsValues('return'));
		if ($returnTagValue != 'void') {
			$mergedMethod->setTag('return', ($newProperty->getTypeForComment() . ' ') . $newProperty->getName());
		}
		// replace property names in description
		$mergedMethod->setDescription(str_replace($oldProperty->getName(), $newProperty->getName(), $mergedMethod->getDescription()));
		if (method_exists($oldProperty, 'getForeignModel') && method_exists($newProperty, 'getForeignModel')) {
			$mergedMethod->setDescription(str_replace($oldProperty->getForeignClassName(), $newProperty->getForeignClassName(), $mergedMethod->getDescription()));
		}
		$this->classObject->removeMethod($oldMethodName);
		$this->classObject->addMethod($mergedMethod);
	}

	/**
	 * @param string $search
	 * @param string $replace
	 * @param string $haystack
	 * @return string with replaced values
	 */
	protected function replaceUpperAndLowerCase($search, $replace, $haystack) {
		$result = str_replace(ucfirst($search), ucfirst($replace), $haystack);
		$result = str_replace(\t3lib_div::lcfirst($search), \t3lib_div::lcfirst($replace), $result);
		return $result;
	}

	/**
	 * Replace all occurences of the old property name with the new name
	 *
	 * @param string $oldName
	 * @param string $newName
	 * @param string $string
	 * @return string
	 */
	protected function replacePropertyNameInMethodBody($oldName, $newName, $string) {
		$regex = ('/([\\$|>])' . $oldName) . '([^a-zA-Z0-9_])/';
		$result = preg_replace($regex, ('$1' . $newName) . '$2', $string);
		return $result;
	}

	/**comments
			 * if the foreign DomainObject was renamed, the relation has to be updated also
			 *
			 * @param Tx_ExtensionBuilder_Domain_Model_DomainObject_Relation_AbstractRelation $relation
			 * @return string className of foreign class
			 */
	public function getForeignClassName($relation) {
		if ($relation->getForeignModel() && isset($this->renamedDomainObjects[$relation->getForeignModel()->getUniqueIdentifier()])) {
			$renamedObject = $this->renamedDomainObjects[$relation->getForeignModel()->getUniqueIdentifier()];
			return $renamedObject->getClassName;
		} else {
			return $relation->getForeignClassName();
		}
	}

	/**
	 * remove domainObject related files if a domainObject was deleted
	 *
	 * @return void
	 */
	protected function removeDomainObjectFiles($domainObject) {
		$this->logger->log('Remove domainObject ' . $domainObject->getName(), 'extension_builder', 0);
		$this->cleanUp(CodeGenerator::getFolderForClassFile($this->previousExtensionDirectory, 'Model', FALSE), $domainObject->getName() . '.php');
		$this->cleanUp($this->previousExtensionDirectory . 'Configuration/TCA/', $domainObject->getName() . '.php');
		if ($domainObject->isAggregateRoot()) {
			$this->cleanUp(CodeGenerator::getFolderForClassFile($this->previousExtensionDirectory, 'Controller', FALSE), $domainObject->getName() . 'Controller.php');
			$this->cleanUp(CodeGenerator::getFolderForClassFile($this->previousExtensionDirectory, 'Repository', FALSE), $domainObject->getName() . 'Repository.php');
		}
		if (count($domainObject->getActions()) > 0) {
			$this->cleanUp(CodeGenerator::getFolderForClassFile($this->previousExtensionDirectory, 'Controller', FALSE), $domainObject->getName() . 'Controller.php');
		}
		// other files
		$iconsDirectory = $this->packageDirectory . 'Resources/Public/Icons/';
		$languageDirectory = $this->packageDirectory . 'Resources/Private/Language/';
		$locallang_cshFile = (($languageDirectory . 'locallang_csh_') . $domainObject->getDatabaseTableName()) . '.xml';
		$iconFile = ($iconsDirectory . $domainObject->getDatabaseTableName()) . '.gif';
		if (file_exists($locallang_cshFile)) {
			// no overwrite settings check here...
			unlink($locallang_cshFile);
			$this->logger->log('locallang_csh file removed: ' . $locallang_cshFile, 'extension_builder', 1);
		}
		if (file_exists($iconFile)) {
			unlink($iconFile);
			$this->logger->log('icon file removed: ' . $iconFile, 'extension_builder', 1);
		}
	}

	/**
	 * remove class files that are not required any more, due to renaming of ModelObjects or changed types
	 *
	 * @param string $path
	 * @param string $file
	 * @return unknown_type
	 */
	public function cleanUp($path, $fileName) {
		if ($this->packageRenamed) {
			// wo won't delete the old extension!
			return;
		}
		if (!is_file(($path . $fileName))) {
			$this->logger->log(('cleanUp File not found: ' . $path) . $fileName, 'extension_builder', 1);
			return;
		}
		unlink($path . $fileName);
	}

	/**
	 * @return array
	 */
	static public function getExtConfiguration() {
		$extConfiguration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['extension_builder']);
		return $extConfiguration;
	}

	/**
	 * finds a related overwrite setting to a path
	and returns the overWrite setting
	-1 for do not create at all
	0  for overwrite
	1  for merge (if possible)
	2  for keep existing file
	 *
	 * @param string $path of the file to get the settings for
	 * @param \TYPO3\PackageBuilder\Domain\Model\Extension $extension
	 * @return int overWriteSetting
	 */
	static public function getOverWriteSettingForPath($path, $extension) {
		return 0;
		$map = array(
			'skip' => -1,
			'merge' => 1,
			'keep' => 2
		);
		$settings = $extension->getSettings();
		if (!is_array($settings)) {
			throw new \Exception('overWrite settings could not be parsed');
		}
		if (strpos($path, $extension->getExtensionDir()) === 0) {
			$path = str_replace($extension->getExtensionDir(), '', $path);
		}
		$pathParts = explode('/', $path);
		$overWriteSettings = $settings['overwriteSettings'];
		foreach ($pathParts as $pathPart) {
			if (isset($overWriteSettings[$pathPart]) && is_array($overWriteSettings[$pathPart])) {
				// step one level deeper
				$overWriteSettings = $overWriteSettings[$pathPart];
			} else {
				return $map[$overWriteSettings[$pathPart]];
			}
		}
		return 0;
	}

	/**
	 * parse existing tca and set appropriate properties
	 *
	 * @param $extension
	 * @return void
	 */
	static public function prepareExtensionForRoundtrip(&$extension) {
		foreach ($extension->getDomainObjects() as $domainObject) {
			$existingTca = self::getTcaForDomainObject($domainObject, $extension);
			if ($existingTca) {
				foreach ($domainObject->getAnyToManyRelationProperties() as $relationProperty) {
					if (isset($existingTca['columns'][$relationProperty->getName()]['config']['MM'])) {
						//self::logger->log((('Relation table for Model ' . $domainObject->getName()) . ' relation ') . $relationProperty->getName(), 'extension_builder', 0, $existingTca['columns'][$relationProperty->getName()]['config']);
						$relationProperty->setRelationTableName($existingTca['columns'][$relationProperty->getName()]['config']['MM']);
					}
				}
			}
		}
	}

	/**
	 * Returns the current TCA for a domain objects table if the extension is installed
	TODO: check for previous table name if an extension is renamed
	 *
	 * @param \TYPO3\PackageBuilder\Domain\Model\DomainObject $domainObject
	 * @return array
	 */
	static protected function getTcaForDomainObject($domainObject, $extension) {
		$tableName = $domainObject->getDatabaseTableName();
		\t3lib_div::loadTca($tableName);
		if (isset($GLOBALS['TCA'][$tableName])) {
			return $GLOBALS['TCA'][$tableName];
		} else {
			return NULL;
		}
	}

	/**
	 * @param \TYPO3\PackageBuilder\Domain\Model\Extension $extension
	 * @param string $backupDir
	 * @return void
	 */
	static function backupExtension($extension, $backupDir) {
		if (empty($backupDir)) {
			throw new \Exception('Please define a backup directory in extension configuration!');
		} else {
			if (!\t3lib_div::validPathStr($backupDir)) {
				throw new \Exception('Backup directory is not a valid path: ' . $backupDir);
			} else {
				if (\t3lib_div::isAbsPath($backupDir)) {
					if (!\t3lib_div::isAllowedAbsPath($backupDir)) {
						throw new \Exception('Backup directory is not an allowed absolute path: ' . $backupDir);
					}
				} else {
					$backupDir = PATH_site . $backupDir;
				}
			}
		}
		if (strrpos($backupDir, '/') < strlen($backupDir) - 1) {
			$backupDir .= '/';
		}
		if (!is_dir($backupDir)) {
			throw new \Exception('Backup directory does not exist: ' . $backupDir);
		} else {
			if (!is_writable($backupDir)) {
				throw new \Exception('Backup directory is not writable: ' . $backupDir);
			}
		}
		$backupDir .= $extension->getExtensionKey();
		// create a subdirectory for this extension
		if (!is_dir($backupDir)) {
			\t3lib_div::mkdir($backupDir);
		}
		if (strrpos($backupDir, '/') < strlen($backupDir) - 1) {
			$backupDir .= '/';
		}
		$backupDir .= date('Y-m-d-') . time();
		if (!is_dir($backupDir)) {
			\t3lib_div::mkdir($backupDir);
		}
		$extensionDir = substr($extension->getExtensionDir(), 0, strlen($extension->getExtensionDir()) - 1);
		try {
			self::recurse_copy($extensionDir, $backupDir);
		} catch (Exception $e) {
			throw new \Exception('Code generation aborted:' . $e->getMessage());
		}
		//$this->logger->log('Backup created in ' . $backupDir, 'extension_builder', 0);
	}

	/**
	 * @param string $src path to copy
	 * @param string $dst destination
	 * @return void
	 */
	static public function recurse_copy($src, $dst) {
		$dir = opendir($src);
		@mkdir($dst);
		while (FALSE !== ($file = readdir($dir))) {
			if ($file != '.' && $file != '..') {
				if (is_dir(($src . '/') . $file)) {
					self::recurse_copy(($src . '/') . $file, ($dst . '/') . $file);
				} else {
					$success = copy(($src . '/') . $file, ($dst . '/') . $file);
					if (!$success) {
						throw new \Exception((((((('Could not copy ' . $src) . '/') . $file) . ' to ') . $dst) . '/') . $file);
					}
				}
			}
		}
		closedir($dir);
	}

}

?>