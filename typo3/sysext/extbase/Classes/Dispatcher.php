<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Jochen Rau <jochen.rau@typoplanet.de>
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

/**
 * Creates a request an dispatches it to the controller which was specified
 * by TS Setup, Flexform and returns the content to the v4 framework.
 *
 * This class is the main entry point for extbase extensions in the frontend.
 *
 * @package Extbase
 * @version $ID:$
 */
class Tx_Extbase_Dispatcher {

	/**
	 * @var Tx_Extbase_Configuration_Manager
	 */
	protected $configurationManager;

	/**
	 * @var Tx_Extbase_Reflection_Service
	 */
	protected static $reflectionService;

	/**
	 * @var Tx_Extbase_Persistence_Manager
	 */
	private static $persistenceManager;

	/**
	 * The settings for the Extbase framework
	 * @var array
	 */
	private static $settings;


	/**
	 * Constructs this Dispatcher and registers the autoloader
	 */
	public function __construct() {
		spl_autoload_register(array($this, 'autoloadClass'));
	}

	/**
	 * Creates a request an dispatches it to a controller.
	 *
	 * @param string $content The content
	 * @param array|NULL $configuration The TS configuration array
	 * @return string $content The processed content
	 */
	public function dispatch($content, $configuration) {

		// FIXME Remove the next lines. These are only there to generate the ext_autoload.php file
		//$extutil = new Tx_Extbase_Utility_Extension;
		//$extutil->createAutoloadRegistryForExtension('extbase', t3lib_extMgm::extPath('extbase'));
		//$extutil->createAutoloadRegistryForExtension('fluid', t3lib_extMgm::extPath('fluid'));

		if (!is_array($configuration)) {
			t3lib_div::sysLog('Extbase was not able to dispatch the request. No configuration.', 'extbase', t3lib_div::SYSLOG_SEVERITY_ERROR);
			return $content;
		}
		$this->initializeConfiguration($configuration);

		$requestBuilder = t3lib_div::makeInstance('Tx_Extbase_MVC_Web_RequestBuilder');
		$request = $requestBuilder->initialize($configuration);
		$request = $requestBuilder->build();
		$response = t3lib_div::makeInstance('Tx_Extbase_MVC_Web_Response');

		$persistenceManager = self::getPersistenceManager($configuration);

		$dispatchLoopCount = 0;
		while (!$request->isDispatched()) {
			if ($dispatchLoopCount++ > 99) throw new Tx_Extbase_MVC_Exception_InfiniteLoop('Could not ultimately dispatch the request after '  . $dispatchLoopCount . ' iterations.', 1217839467);
			$controller = $this->getPreparedController($request);
			try {
				$controller->processRequest($request, $response);
			} catch (Tx_Extbase_MVC_Exception_StopAction $ignoredException) {
			} catch (Tx_Extbase_MVC_Exception_InvalidArgumentValue $exception) {
				return '';
			}
		}

		$persistenceManager->persistAll();
		self::$reflectionService->shutdown();
		if (count($response->getAdditionalHeaderData()) > 0) {
			$GLOBALS['TSFE']->additionalHeaderData[$request->getControllerExtensionName()] = implode("\n", $response->getAdditionalHeaderData());
		}
		$response->sendHeaders();
		return $response->getContent();
	}

	/**
	 * Initializes the configuration manager and the Extbase settings
	 *
	 * @param $configuration The current incoming configuration
	 * @return void
	 */
	protected function initializeConfiguration($configuration) {
		$configurationSources = array();
		$configurationSources[] = t3lib_div::makeInstance('Tx_Extbase_Configuration_Source_TypoScriptSource');
		if (!empty($this->cObj->data['pi_flexform'])) {
			$configurationSource = t3lib_div::makeInstance('Tx_Extbase_Configuration_Source_FlexFormSource');
			$configurationSource->setFlexFormContent($this->cObj->data['pi_flexform']);
			$configurationSources[] = $configurationSource;
		}
		$this->configurationManager = t3lib_div::makeInstance('Tx_Extbase_Configuration_Manager', $configurationSources);
		$this->configurationManager->loadExtbaseSettings($configuration, $this->cObj);
		self::$settings = $this->configurationManager->getSettings('Extbase');
	}

	/**
	 * Builds and returns a controller
	 *
	 * @param Tx_Extbase_MVC_Web_Request $request
	 * @return Tx_Extbase_MVC_Controller_ControllerInterface The prepared controller
	 */
	protected function getPreparedController(Tx_Extbase_MVC_Web_Request $request) {
		$controllerObjectName = $request->getControllerObjectName();
		$controller = t3lib_div::makeInstance($controllerObjectName);
		if (!$controller instanceof Tx_Extbase_MVC_Controller_ControllerInterface) {
			throw new Tx_Extbase_MVC_Exception_InvalidController('Invalid controller "' . $request->getControllerObjectName() . '". The controller must implement the Tx_Extbase_MVC_Controller_ControllerInterface.', 1202921619);
		}
		$propertyMapper = t3lib_div::makeInstance('Tx_Extbase_Property_Mapper');
		$controller->injectPropertyMapper($propertyMapper);
		$controller->injectSettings($this->configurationManager->getSettings($request->getControllerExtensionName()));
		$cacheManager = t3lib_div::makeInstance('t3lib_cache_Manager');
		self::$reflectionService = t3lib_div::makeInstance('Tx_Extbase_Reflection_Service');
		try {
			self::$reflectionService->setCache($cacheManager->getCache('cache_extbase_reflection'));
		} catch (t3lib_cache_exception_NoSuchCache $exception) {
			$GLOBALS['typo3CacheFactory']->create(
				'cache_extbase_reflection',
				't3lib_cache_frontend_VariableFrontend',
				$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['cache_extbase_reflection']['backend'],
				$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['cache_extbase_reflection']['options']
			);
			self::$reflectionService->setCache($cacheManager->getCache('cache_extbase_reflection'));
		}
		if (!self::$reflectionService->isInitialized()) {
			self::$reflectionService->initialize();
		}
		$objectManager = t3lib_div::makeInstance('Tx_Extbase_Object_Manager');
		$validatorResolver = t3lib_div::makeInstance('Tx_Extbase_Validation_ValidatorResolver');
		$validatorResolver->injectObjectManager($objectManager);
		$validatorResolver->injectReflectionService(self::$reflectionService);
		$controller->injectValidatorResolver($validatorResolver);
		$controller->injectReflectionService(self::$reflectionService);
		$controller->injectObjectManager($objectManager);
		return $controller;
	}

	/**
	 * This function prepares and returns the Persistance Manager
	 *
	 * @param array $configuration The given configuration
	 * @return Tx_Extbase_Persistence_Manager A (singleton) instance of the Persistence Manager
	 */
	public static function getPersistenceManager(array $configuration = array()) {
		if (self::$persistenceManager === NULL) {
			$queryFactory = t3lib_div::makeInstance('Tx_Extbase_Persistence_QueryFactory'); // singleton

			$dataMapper = t3lib_div::makeInstance('Tx_Extbase_Persistence_Mapper_DataMapper'); // singleton

			$storageBackend = t3lib_div::makeInstance('Tx_Extbase_Persistence_Storage_Typo3DbBackend', $GLOBALS['TYPO3_DB']); // singleton
			$storageBackend->injectDataMapper($dataMapper);
			if (isset($configuration['enableAutomaticCacheClearing']) && $configuration['enableAutomaticCacheClearing'] === '1') {
				$storageBackend->setAutomaticCacheClearing(TRUE);
			} else {
				$storageBackend->setAutomaticCacheClearing(FALSE);
			}
			
			$persistenceSession = t3lib_div::makeInstance('Tx_Extbase_Persistence_Session'); // singleton

			$persistenceBackend = t3lib_div::makeInstance('Tx_Extbase_Persistence_Backend', $persistenceSession, $storageBackend); // singleton
			$persistenceBackend->injectDataMapper($dataMapper);
			$persistenceBackend->injectIdentityMap(t3lib_div::makeInstance('Tx_Extbase_Persistence_IdentityMap'));
			$persistenceBackend->injectQOMFactory(t3lib_div::makeInstance('Tx_Extbase_Persistence_QOM_QueryObjectModelFactory', $storageBackend, $dataMapper));
			$persistenceBackend->injectValueFactory(t3lib_div::makeInstance('Tx_Extbase_Persistence_ValueFactory'));

			$persistenceManager = t3lib_div::makeInstance('Tx_Extbase_Persistence_Manager'); // singleton
			$persistenceManager->injectBackend($persistenceBackend);
			$persistenceManager->injectSession($persistenceSession);

			self::$persistenceManager = $persistenceManager;
		}

		return self::$persistenceManager;
	}

	/**
	 * This function returns the settings of Extbase
	 *
	 * @return array The settings
	 */
	public static function getSettings() {
		return self::$settings;
	}


	/**
	 * Loads php files containing classes or interfaces found in the classes directory of
	 * an extension.
	 *
	 * @param string $className: Name of the class/interface to load
	 * @uses t3lib_extMgm::extPath()
	 * @return void
	 */
	public static function autoloadClass($className) {
		// TODO Remove debug code
		// TODO Make a registry for Extbase classes
		//$starttime = microtime(true);
		$classNameParts = explode('_', $className, 3);
		$extensionKey = Tx_Extbase_Utility_Plugin::convertCamelCaseToLowerCaseUnderscored($classNameParts[1]);
		if (t3lib_extMgm::isLoaded($extensionKey)) {
			$classFilePathAndName = t3lib_extMgm::extPath($extensionKey) . 'Classes/' . strtr($classNameParts[2], '_', '/') . '.php';
			if (file_exists($classFilePathAndName)) {
				require($classFilePathAndName);
			}
		}
		//$endtime = microtime(true);
		//debug(($endtime - $starttime) * 10000, $className);
	}
}
?>