<?php
namespace TYPO3\PackageBuilder\ViewHelper;

/*                                                                        *
 * This script belongs to the Flow package "Fluid".                      *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License as published by the *
 * Free Software Foundation, either version 3 of the License, or (at your *
 * option) any later version.                                             *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser       *
 * General Public License for more details.                               *
 *                                                                        *
 * You should have received a copy of the GNU Lesser General Public       *
 * License along with the script.                                         *
 * If not, see http://www.gnu.org/licenses/lgpl.html                      *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 *
 * @version $Id: RenderViewHelper.php 2813 2009-07-16 14:02:34Z k-fish $
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 * @scope prototype
 */
class RenderViewHelper extends \TYPO3\Fluid\ViewHelpers\RenderViewHelper {


	/**
	 * Renders the content.
	 *
	 * @param string $section Name of section to render. If used in a layout, renders a section of the main content file. If used inside a standard template, renders a section of the same file.
	 * @param string $partial Reference to a partial.
	 * @param array $arguments Arguments to pass to the partial.
	 * @param boolean $optional Set to TRUE, to ignore unknown sections, so the definition of a section inside a template can be optional for a layout
	 * @return string
	 * @api
	 */
	public function render($section = NULL, $partial = NULL, $arguments = array(), $optional = FALSE) {
		$arguments = $this->loadSettingsIntoArguments($arguments);

		if ($partial !== NULL) {
			return $this->viewHelperVariableContainer->getView()->renderPartial($partial, $section, $arguments);
		} elseif ($section !== NULL) {
			return $this->viewHelperVariableContainer->getView()->renderSection($section, $arguments, $optional);
		}
		return '';
	}

	/**
	 * If $arguments['settings'] is not set, it is loaded from the TemplateVariableContainer (if it is available there).
	 *
	 * @param array $arguments
	 * @return array
	 */
	protected function loadSettingsIntoArguments($arguments) {
		if (!isset($arguments['settings']) && $this->templateVariableContainer->exists('settings')) {
			$arguments['settings'] = $this->templateVariableContainer->get('settings');
		}
		return $arguments;
	}

	/**
	 *
	 * @param string $filePath
	 * @param Array $variables
	 */
	protected function renderTemplate($filePath, $variables) {
		if (!isset($variables['settings']['codeTemplateRootPath'])) {
			//t3lib_div::devlog('Render: ' . $filePath, 'builder', 2, $variables);
			throw new \TYPO3\PackageBuilder\Exception\ConfigurationError('No template root path configured: ' . $filePath);
		}
		if (!file_exists($variables['settings']['codeTemplateRootPath'] . $filePath)) {
			//t3lib_div::devlog('No template file found: ' . $variables['settings']['codeTemplateRootPath'] . $filePath, 'extension_builder', 2, $variables);
			throw new \TYPO3\PackageBuilder\Exception\ConfigurationError('No template file found: ' . $variables['settings']['codeTemplateRootPath'] . $filePath);
		}
		$parsedTemplate = $this->templateParser->parse(file_get_contents($variables['settings']['codeTemplateRootPath'] . $filePath));
		return $parsedTemplate->render($this->buildRenderingContext($variables));
	}
}

?>