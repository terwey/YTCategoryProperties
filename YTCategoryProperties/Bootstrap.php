<?php
/**
 * Shopware 4.0 Plugin: Properties per Category
 * Copyright © 2013 Yorick Terweijden IT Advice
 *
 * @category   Shopware Plugin
 * @package    Shopware_Plugins
 * @copyright  Copyright (c) 2013, Yorick Terweijden IT Advice (www.itadvice.de)
 * @author     Yorick Terweijden
 */
class Shopware_Plugins_Core_CategoryProperties_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
	public function getCapabilities() {
		return array(
			'install' => true,
			'update' => true,
			'enable' => true
		);
	}

	public function getLabel() {
		return 'Properties per Category';
	}

	public function getVersion() {
		return '1.0.0';
	}

	public function getInfo() {
		return array(
			'author' => 'Yorick Terweijden IT Advice',
			'copyright' => 'Copyright (c) 2013, Yorick Terweijden IT Advice',
			'version' => $this->getVersion(),
			'label' => $this->getLabel(),
			'supplier' => 'Yorick Terweijden IT Advice',
			'license' => 'MIT',
			'description' => 'Allows the seperation of properties on basis of a category.',
			'support' => 'Email: twisted@itadvice.de',
			'link' => 'http://www.itadvice.de'
		);
	}

	public function install()
	{
		/**
		 * Global events
		 */
		$this->subscribeEvent(
		    'Enlight_Controller_Action_PreDispatch',
		    'onPreDispatch'
		);

		$this->subscribeEvent(
		    'Enlight_Controller_Action_PostDispatch',
		    'onPostDispatch'
		);

		/**
		 * Hooks
		 */

		$this->subscribeEvent(
			'sArticles::sGetCategoryProperties::after',
			'sGetCategoryPropertiesAfter'
		);

		$this->subscribeEvent(
			'sArticles::sGetArticleProperties::after',
			'sGetArticlePropertiesAfter'
		);
		

		$form = $this->Form();
		$form->setElement('textarea', 'prefixes', array(
            'label' =>  'Prefixes',
            'value' =>  'a- | Category A % b- | Category B',
            'description' => 'Format: prefix | category. % denotes a second category',
            'required' => true,
            'scope' =>  \Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('text', 'optionGroup', array(
            'label' =>  'Option group',
            'value' =>  'OptionGroupName',
            'description' => 'Name of option group to filter on.',
            'required' => true,
            'scope' =>  \Shopware\Models\Config\Element::SCOPE_SHOP
        ));
		$form->save();
 	
		return true;
	}

	/**
	 * Hooks
	 */
    public function sGetCategoryPropertiesAfter (Enlight_Hook_HookArgs $args)
	{
		$properties = $args->getReturn();
		
		$configPrefixesString = $this->Config()->prefixes;
		$optionGroupName = $this->Config()->optionGroup;
		
		// convert the string into the categoryPrefixes array
		$categoryPrefixes = array();
		if (strpos($configPrefixesString,'%') !== false) {
			$configArray = explode('%', $configPrefixesString);
			foreach ($configArray as $values) {
				$a = explode('|', $values);
				$categoryPrefixes[trim($a[0])] = trim($a[1]);
			}
		} else {
			$a = explode('|', $configPrefixesString);
			$categoryPrefixes[trim($a[0])] = trim($a[1]);
		}

		$urlParsed = $this->queryToArray($properties['filterOptions']['grouped'][$optionGroupName]['default']['linkSelect']);
		$categoryID = $urlParsed['sCategory'];
		$category = Shopware()->Modules()->System()->sSYSTEM->sMODULES["sCategories"]->sGetCategoryContent($categoryID);
		$categoryName = $category['name'];

		// first remove the categories that need to be filtered
		if (in_array($categoryName, $categoryPrefixes)) {
			$key = array_search($categoryName, $categoryPrefixes);
			$categoriesToRemovePrefixes = $categoryPrefixes;
			unset($categoriesToRemovePrefixes[$key]);

			foreach ($properties['filterOptions']['optionsOnly'] as $optionGroup => $optionGroupArray) {
				foreach ($properties['filterOptions']['optionsOnly'][$optionGroup]['values'] as $optionName => $value) {
					foreach ($categoriesToRemovePrefixes as $prefix => $category) {
						if (!strncmp($optionName, $prefix, strlen($prefix))) {
							unset($properties['filterOptions']['optionsOnly'][$optionGroup]['values'][$optionName]);
						}
					}
				}
				if (empty($properties['filterOptions']['optionsOnly'][$optionGroup]['values'])) {
					// unset the group if it's empty
					unset($properties['filterOptions']['optionsOnly'][$optionGroup]);
				}
			}

			// clean up the grouped array
			foreach ($properties['filterOptions']['grouped'][$optionGroupName]['options'] as $optionGroup => $optionGroupArray) {
				foreach ($optionGroupArray as $optionName => $optionArray) {
					foreach ($categoriesToRemovePrefixes as $prefix => $category) {
						if (!strncmp($optionName, $prefix, strlen($prefix))) {
							unset($properties['filterOptions']['grouped'][$optionGroupName]['options'][$optionGroup][$optionName]);
						}
					}
				}
					
				if (empty($properties['filterOptions']['grouped'][$optionGroupName]['options'][$optionGroup])) {
					// unset group if empty
					unset($properties['filterOptions']['grouped'][$optionGroupName]['options'][$optionGroup]);
				}
			}
		}

		// remove the prefix from the grouped filterOptions
		foreach ($properties['filterOptions']['grouped'][$optionGroupName]['options'] as $optionGroup => $optionGroupArray) {
				foreach ($optionGroupArray as $optionName => $optionArray) {
					foreach ($categoryPrefixes as $prefix => $category) {
						if (!strncmp($optionName, $prefix, strlen($prefix))) {
							$optionNameWithoutPrefix = substr($optionName, strlen($prefix), strlen($optionName) );
							$properties['filterOptions']['grouped'][$optionGroupName]['options'][$optionGroup][$optionNameWithoutPrefix] = $properties['filterOptions']['grouped'][$optionGroupName]['options'][$optionGroup][$optionName];
							unset($properties['filterOptions']['grouped'][$optionGroupName]['options'][$optionGroup][$optionName]);
							$properties['filterOptions']['grouped'][$optionGroupName]['options'][$optionGroup][$optionNameWithoutPrefix]['value'] = $optionNameWithoutPrefix;
						}
					}
				}
		}

		// remove the prefix from the remaining filterOptions
		foreach ($properties['filterOptions']['optionsOnly'] as $optionGroup => $optionGroupArray) {
			foreach ($properties['filterOptions']['optionsOnly'][$optionGroup]['values'] as $optionName => $value) {
				foreach ($categoryPrefixes as $prefix => $category) {
		            if (!strncmp($optionName, $prefix, strlen($prefix))) {
		                $optionNameWithoutPrefix = substr($optionName, strlen($prefix), strlen($optionName) );
		            }
				}

				$properties['filterOptions']['optionsOnly'][$optionGroup]['values'][$optionName]['value'] = $optionNameWithoutPrefix;
				$properties['filterOptions']['optionsOnly'][$optionGroup]['values'][$optionNameWithoutPrefix] = $properties['filterOptions']['optionsOnly'][$optionGroup]['values'][$optionName];
				unset($properties['filterOptions']['optionsOnly'][$optionGroup]['values'][$optionName]);
			}
		}

		$args->setReturn($properties);
	}

	public function sGetArticlePropertiesAfter (Enlight_Hook_HookArgs $args)
    {
        $properties = $args->getReturn();

        $configPrefixesString = $this->Config()->prefixes;
		$optionGroupName = $this->Config()->optionGroup;
		
		// convert the string into the categoryPrefixes array
		$categoryPrefixes = array();
		if (strpos($configPrefixesString,'%') !== false) {
			$configArray = explode('%', $configPrefixesString);
			foreach ($configArray as $values) {
				$a = explode('|', $values);
				$categoryPrefixes[trim($a[0])] = trim($a[1]);
			}
		} else {
			$a = explode('|', $configPrefixesString);
			$categoryPrefixes[trim($a[0])] = trim($a[1]);
		}

		// first clean up the option array
        foreach ($properties as $optionGroupID => $value) {
        	unset($properties[$optionGroupID]['value']);
        	foreach ($value['values'] as $key => $optionName) {
        		foreach ($categoryPrefixes as $prefix => $category) {
		            if (!strncmp($optionName, $prefix, strlen($prefix))) {
		            	$properties[$optionGroupID]['values'][$key] = substr($optionName, strlen($prefix), strlen($optionName) );
		            }
				}
        	}

        	// then sort and create the new comma seperated list
        	sort($properties[$optionGroupID]['values']);
        	$properties[$optionGroupID]['value'] = implode(', ', $properties[$optionGroupID]['values']);
        }
        
		$args->setReturn($properties);
    }

	public function queryToArray($qry)	{
		$result = array();
		//string must contain at least one = and cannot be in first position
		if(strpos($qry,'=')) {
			if(strpos($qry,'?')!==false) {
					$q = parse_url($qry);
					$qry = $q['query'];
				}
		} else {
			return false;
		}

		foreach (explode('&', $qry) as $couple) {
			list ($key, $val) = explode('=', $couple);
			$result[$key] = $val;
		}

		return empty($result) ? false : $result;
	}
}
 
