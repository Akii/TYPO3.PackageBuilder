<?php
namespace TYPO3\PackageBuilder\Service;
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
 *
 * @Flow\Scope("singleton")
 */

abstract class AbstractPackageFactory {

	abstract public function create(array $configuration);

	/**
	 * @param \TYPO3\Flow\Log\Logger $logger
	 */
	public function injectLogger(\TYPO3\Flow\Log\Logger $logger) {
		$this->logger = $logger;
	}
}