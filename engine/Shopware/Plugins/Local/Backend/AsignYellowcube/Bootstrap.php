<?php
/**
 * This file defines the plugin information and extensions
 *
 * @category  asign
 * @package   AsignYellowcube
 * @author    entwicklung@a-sign.ch
 * @copyright A-Sign
 * @license   https://www.a-sign.ch/
 * @version   2.1.3
 * @link      https://www.a-sign.ch/
 * @see       Shopware_Plugins_Backend_AsignYellowcube_Bootstrap
 * @since     File available since Release 1.0
 */

/**
 * Defines plugin information
 *
 * @category A-Sign
 * @package  AsignYellowcube
 * @author   entwicklung@a-sign.ch
 * @link     http://www.a-sign.ch
 */
class Shopware_Plugins_Backend_AsignYellowcube_Bootstrap extends Shopware_Components_Plugin_Bootstrap {
	/**
	 * CRON default time interveal. Default = 3600 sec. (60 min.)
	 * @var integer
	 */
	protected $iDefaultCronInterval = 3600;

	/**
	 * Array of CRONs created for the execution
	 * @var array
	 */
	protected $aCronDefaultEntries = [
		'ActArtCron'   => 'A-SIGN YC Active articles',
		'InactArtCron' => 'A-SIGN YC Inactive articles',
		'OrdCron'      => 'A-SIGN YC Prepayment orders',
	];

	/**
	 * Array of shipping methods to be installed
	 * @var array
	 */
	protected $_aShippingCosts = [
		'SPS_ECO'              => 'PostPac Economy',
		'SPS_ECO_SI'           => 'PostPac Economy mit Unterschrift (SI)',
		'SPS_PRI'              => 'PostPac Priority',
		'SPS_PRI_SI'           => 'PostPac Priority mit Unterschrift (SI)',
		'SPS_PRI_SI;AZ'        => 'PostPac Priority SI Abendzustellung',
		'SPS_PRI_SI;SA'        => 'PostPac Priority SI Samstagszustellung',
		'SPS_PICKUP_APOST'     => 'Abholung A-Post',
		'SPS_PICKUP_URGENT'    => 'International TNT',
		'SPS_PICKUP_INTPRI;GR' => 'Abholung International Priority Gross',
		'SPS_PICKUP_INTPRI;MX' => 'Abholung International Priority Maxi',
		'SPS_PICKUP_INTECO;GR' => 'Abholung International Economy Gross',
		'SPS_PICKUP_INTECO;MX' => 'Abholung International Economy Maxi',
		'SPS_INTPRI'           => 'Internationaler Kleinpaket-Brief Priority',
		'SPS_INTECO'           => 'Internationaler Kleinpaket-Brief Economy',
	];

	/**
	 * Returns plugin info
	 *
	 * @return string
	 *
	 * @throws \Exception
	 */
	public function getInfo() {
		return [
			'label'     => $this->getPluginInfo( 'label' ),
			'class'     => $this->getPluginInfo( 'class' ),
			'author'    => $this->getPluginInfo( 'author' ),
			'copyright' => $this->getPluginInfo( 'copyright' ),
			'support'   => $this->getPluginInfo( 'support' ),
			'version'   => $this->getPluginInfo( 'version' ),
			'link'      => $this->getPluginInfo( 'link' ),
		];
	}

	/**
	 * Returns the version of the plugin as a string
	 *
	 * @param $sParam
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function getPluginInfo( $sParam ) {
		$info = json_decode( file_get_contents( __DIR__ . DIRECTORY_SEPARATOR . 'plugin.json' ), true );

		if ( $info ) {
			return $info[ $sParam ];
		} else {
			throw new \Exception( 'The plugin has an invalid version file.' );
		}
	}

	/**
	 * After init event of the bootstrap class.
	 *
	 * The afterInit function registers the custom plugin models.
	 */
	public function afterInit() {
		$this->Application()->Loader()->registerNamespace( 'Shopware\AsignYellowcube', $this->Path() );
		$this->registerCustomModels();
	}

	/**
	 * Triggers the plugin installation
	 *
	 * @return null
	 */
	public function install() {
		// subscibe the events now
		$this->subscribeEvent(
			'Enlight_Controller_Dispatcher_ControllerPath_Backend_AsignYellowcube',
			'getBackendController'
		);

		$this->subscribeEvent(
			'Enlight_Controller_Dispatcher_ControllerPath_Widgets_AsignWidgetCube',
			'onGetWidgetController'
		);

		$this->subscribeEvent(
			'Enlight_Controller_Action_PostDispatch_Frontend_Checkout',
			'onPostDispatchCheckout'
		);

		$this->subscribeEvent(
			'Enlight_Controller_Action_PostDispatch_Frontend_Register',
			'onPostDispatchRegister'
		);

		$this->subscribeEvent(
			'Enlight_Controller_Action_PostDispatch_Frontend_Account',
			'onPostDispatchAccount'
		);

		$this->subscribeEvent(
			'Shopware_Components_Document::render::before',
			'beforeDocumentRender'
		);

		$this->subscribeEvent(
			'Enlight_Controller_Action_Frontend_Register_saveRegister',
			'afterSaveRegisterAction'
		);

		$this->subscribeEvent(
			'sOrder::sSaveOrder::after', 'afterSaveOrder'
		);

		$this->createMenuItem( [
			'label'      => $this->getPluginInfo( 'label' ),
			'controller' => $this->getPluginInfo( 'controller' ),
			'class'      => $this->getPluginInfo( 'class' ),
			'action'     => $this->getPluginInfo( 'action' ),
			'active'     => 1,
			'parent'     => $this->Menu()->findOneBy( [ 'label' => $this->getPluginInfo( 'parent' ) ] ),
		] );

		$this->ycubeCreateDispatch();
		$this->ycubeCreateConfiguration();
		$this->ycubeManageTableQueries();
		$this->ycubeUpdateSnippets();
		$this->ycubeTemplateEntry();
		$this->ycubeCreateCron();

		return [
			'success'         => true,
			'invalidateCache' => [ 'backend' ],
		];
	}

	/**
	 * Creates yellowcube dispatch/shipping methods
	 *
	 * @return null
	 */
	private function ycubeCreateDispatch() {
		// activate shipping methods one by one...
		$idd = 100;
		foreach ( $this->_aShippingCosts as $shipid => $shipping ) {
			$iSql = "INSERT IGNORE INTO `s_premium_dispatch` SET `id` = '" . $idd . "', `name` = '" . $shipping . "', `description` = '" . $shipping . "', `comment` = '" . $shipid . "', `active` = 1";
			Shopware()->Db()->query( $iSql );

			$idd = $idd + 1;
		}
	}

	/**
	 * Create Configuration Method
	 * Creates configuration form for all necessary details required for Yellowcube SOAP calls
	 *
	 * @return null
	 */
	public function ycubeCreateConfiguration() {
		try {
			$ycForm = $this->Form();

			// Soap connection information only...
			$ycForm->setElement( 'button', 'main', [ 'label' => 'Main Configuration' ] );
			$ycForm->setElement( 'select', 'sYellowCubeMode',
				[
					'required'    => 1,
					'label'       => 'Yellowcube Operating Mode',
					'description' => 'Yellowcube Operating Modes: Test or Production. Before going Live set Mode to Production mode. Default value Test mode.',
					'store'       => [
						[ 'T', 'Test' ],
						[ 'D', 'Development' ],
						[ 'P', 'Production' ],
					],
					'value'       => 'T',
				]
			);

			$ycForm->setElement( 'text', 'sYellowCubeTransMaxTime',
				[
					'required' => 1,
					'label'    => 'Maximum waiting Time (in seconds)',
					'value'    => '120',
				]
			);

			// authentication information only
			$ycForm->setElement( 'button', 'authentication', [ 'label' => 'Authentication' ] );
			$ycForm->setElement( 'text', 'sYellowCubeDepositorNo',
				[
					'required' => 1,
					'label'    => 'Depositor No.',
				]
			);

			$ycForm->setElement( 'text', 'sYellowCubeSender',
				[
					'required' => 1,
					'label'    => 'Sender Identity',
				]
			);

			$ycForm->setElement( 'text', 'sYellowCubeReceiver',
				[
					'required' => 1,
					'label'    => 'Receiver Identity',
				]
			);

			// partner information
			$ycForm->setElement( 'button', 'partner', [ 'label' => 'Partner Information' ] );
			$ycForm->setElement( 'text', 'sYellowCubePType',
				[
					'required'    => 1,
					'label'       => 'Partner Type',
					'description' => 'WE = Warenempfänger (Im Standard YellowCube wird nur eine Partnerrolle neben dem Aufgeber unterstützt.)',
					'value'       => 'WE',
				]
			);

			$ycForm->setElement( 'text', 'sYellowCubePartnerNo',
				[
					'required' => 1,
					'label'    => 'Partner Nummer',
				]
			);

			$ycForm->setElement( 'text', 'sYellowCubePlant',
				[
					'required'    => 1,
					'label'       => 'Plant ID',
					'description' => 'Plant is the place where the product is stored in the auto-store. Bearing ID as a work ID according to the profile distance dealer. e.g. Y001, Y004, Y010, etc.',
				]
			);

			// Certificate information only
			$ycForm->setElement( 'button', 'Certificate', [ 'label' => 'Certificate Configuration' ] );
			$ycForm->setElement( 'text', 'sYellowCubeCertFile',
				[
					'required'    => 1,
					'label'       => 'Certificate Filename',
					'description' => 'Specify the YC Certificate filename placed under your Plugins/Local/Backend/AsignYellowcube/cert/ folder.',
					'value'       => 'cert.pem',
				]
			);

			// Article informations only...
			$ycForm->setElement( 'button', 'Article', [ 'label' => 'Article Configuration' ] );

			$ycForm->setElement( 'select', 'blYellowCubeResetInventory',
				[
					'label'       => 'Reset article stock on inventory update',
					'description' => 'Yes = Set article stock to 0 for YC articles before updating inventory<br />No = Only update article stocks from YC inventory',
					'store'       => [
						[ '1', 'Yes' ],
						[ '0', 'No' ],
					],
					'value'       => '0',
				]
			);

			$ycForm->setElement( 'text', 'sYellowCubeQuantityISO',
				[
					'required'    => 1,
					'label'       => 'Default Quantity (ISO)',
					'description' => 'Verkaufs-Mengeneinheit in ISO-Code. Werte gemäss gültiger, mit Kunde vereinbarter Verkaufsmengenheinheiten.',
					'value'       => 'PCE',
				]
			);

			$ycForm->setElement( 'text', 'sYellowCubeAlternateUnitISO',
				[
					'required' => 1,
					'label'    => 'Alterantive Basismengen-Einheit',
					'value'    => 'PCE',
				]
			);

			$ycForm->setElement( 'select', 'sYellowCubeEANType',
				[
					'required' => 1,
					'label'    => 'EANType',
					'store'    => [
						[ 'HE', 'Hersteller-EAN' ],
						[ 'HK', 'Hersteller-Kurz-EAN' ],
						[ 'I6', 'ITF-Code - 16stellig' ],
						[ 'IC', 'ITF-Code' ],
						[ 'IE', 'Instore-EAN (int. Vergabe mögl.)' ],
						[ 'IK', 'Instore-Kurz-EAN (int. Vergabe mögl.)' ],
						[ 'UC', 'UPC-Code' ],
						[ 'VC', 'Velocity-Code (int. Vergabe mögl.)' ],
					],
					'value'    => 'HE',
				]
			);

			$ycForm->setElement( 'select', 'sYellowCubeNetWeightISO',
				[
					'required' => 1,
					'label'    => 'Default Nettogewicht (ISO)',
					'store'    => [
						[ 'GRM', 'Gramm [gr]' ],
						[ 'KGM', 'Kilogramm [kg]' ],
					],
					'value'    => 'KGM',
				]
			);

			$ycForm->setElement( 'select', 'sYellowCubeGrossWeightISO',
				[
					'required' => 1,
					'label'    => 'Default Bruttogewicht (ISO)',
					'store'    => [
						[ 'GRM', 'Gramm [gr]' ],
						[ 'KGM', 'Kilogramm [kg]' ],
					],
					'value'    => 'KGM',
				]
			);

			$ycForm->setElement( 'select', 'sYellowCubeLengthISO',
				[
					'required' => 1,
					'label'    => 'Länge (ISO)',
					'store'    => [
						[ 'CMT', 'Centimeter' ],
						[ 'MTR', 'Meter' ],
						[ 'MMT', 'Millimeter' ],
					],
					'value'    => 'MTR',
				]
			);

			$ycForm->setElement( 'select', 'sYellowCubeWidthISO',
				[
					'required' => 1,
					'label'    => 'Breite (ISO)',
					'store'    => [
						[ 'CMT', 'Centimeter' ],
						[ 'MTR', 'Meter' ],
						[ 'MMT', 'Millimeter' ],
					],
					'value'    => 'MTR',
				]
			);

			$ycForm->setElement( 'select', 'sYellowCubeHeightISO',
				[
					'required' => 1,
					'label'    => 'Höhe (ISO)',
					'store'    => [
						[ 'CMT', 'Centimeter' ],
						[ 'MTR', 'Meter' ],
						[ 'MMT', 'Millimeter' ],
					],
					'value'    => 'MTR',
				]
			);

			$ycForm->setElement( 'select', 'sYellowCubeVolumeISO',
				[
					'required' => 1,
					'label'    => 'Volumen (ISO)',
					'store'    => [
						[ 'CMQ', 'Kubik-Centimeter [cm3]' ],
						[ 'MTQ', 'Kubik-Meter [m3]' ],
					],
					'value'    => 'MTQ',
				]
			);

			// Order informationb only...
			$ycForm->setElement( 'button', 'Order', [ 'label' => 'Order Configuration' ] );
			$ycForm->setElement( 'select', 'blYellowCubeOrderManualSend',
				[
					'label'       => 'Manually send Order to Yellowcube?',
					'description' => 'Yes = Send order manually.<br />No = Send order immediately after completion.',
					'store'       => [
						[ '1', 'Yes' ],
						[ '0', 'No' ],
					],
					'value'       => 'No',
				]
			);
			$ycForm->setElement( 'select', 'sYellowCubeOrderDocumentsFlag',
				[
					'label'       => 'Entscheid: Ausliefer-Dokumente werden mitgeliefert.',
					'description' => 'Yes = Dokument liegt vor und muss in Sendung mitgeliefert werden.<br>No = es liegen keine Lieferdokumente vor.',
					'store'       => [
						[ '1', 'Yes' ],
						[ '0', 'No' ],
					],
					'value'       => 'No',
				]
			);

			$ycForm->setElement( 'select', 'sYellowCubeDocMimeType',
				[
					'label'       => 'Document MIME Type',
					'description' => 'MIME-Typ als Extention des Lieferbeleges. [pdf|pcl] Inhalt des Streams muss mit der Extention übereinstimmen.',
					'store'       => [
						[ 'pdf', 'PDF' ],
						[ 'pcl', 'PCL' ],
					],
					'value'       => 'PDF',
				]
			);

			$ycForm->setElement( 'select', 'sYellowCubeDocType',
				[
					'label' => 'Typ des Lieferbeleges',
					'store' => [
						[ 'LS', 'Lieferschein' ],
						[ 'BL', 'Bulletin/Bordereau de livraison' ],
						[ 'BC', 'Bolla di Consegna' ],
						[ 'DN', 'Delivery note' ],
						[ 'IV', 'Invoice' ],
						[ 'RG', 'Rechnung' ],
						[ 'FA', 'Facture/Fatura' ],
						[ 'ZS', 'Zahlschein' ],
						[ 'BP', 'Bulletin de payement' ],
						[ 'BV', 'bollettino di versamento' ],
						[ 'PF', 'Payment form' ],
					],
					'value' => 'LS',
				]
			);

			// CRON JOB informations only...
			$ycForm->setElement( 'button', 'Cronjob', [ 'label' => 'CRON Job Configuration' ] );
			$ycForm->setElement( 'select', 'sCronArtFlag',
				[
					'label'    => 'Default Article Mode',
					'required' => 1,
					'store'    => [
						[ 'I', 'Insert Article' ],
						[ 'U', 'Update Article' ],
						[ 'D', 'Deactivate Article' ],
					],
					'value'    => 'I',
				]
			);
			$ycForm->setElement( 'text', 'sYellowCubeCronHash',
				[
					'required'    => 1,
					'label'       => 'Cron-job Hash value',
					'description' => 'Security measure to make sure the script is not called anonymously without Hash Tag',
					'value'       => 'changethis',
				]
			);

			// Developer mode informations only...
			$ycForm->setElement( 'button', 'Developer', [ 'label' => 'Developer mode' ] );
			$ycForm->setElement( 'text', 'sYellowCubeNotifyEmail',
				[
					'required'    => 0,
					'label'       => 'Developer Email-address',
					'description' => 'This should be used only during Development or debugging process. YC XML Request and Response will be sent on this email address.',
				]
			);
		} catch ( \Exception $e ) {
			throw new \Exception( '<b>Fehler beim erstellen des Einstellungsformulars</b><br />' . $e->getMessage() );
		}
	}

	/**
	 * Defines path for the backend controller
	 *
	 * @return null
	 */
	public function ycubeManageTableQueries() {
		$this->ycubeAlterTable();
		$this->createYellowcubeTable( 'Product' );
		$this->createYellowcubeTable( 'Orders' );
		$this->createYellowcubeTable( 'Inventory' );
		$this->createYellowcubeTable( 'Errorlogs' );
	}

	/**
	 * Alter existing tables as per Queries...
	 *
	 * @return null
	 */
	public function ycubeAlterTable() {
		// check if the `tariff` already exists
		if ( ! $this->isColumnExistsInTable( 's_articles', 'tariff' ) ) {
			Shopware()->Db()->query( "ALTER TABLE `s_articles` ADD COLUMN `tariff` CHAR(11) NOT NULL DEFAULT ''" );
		}

		// check if the `tara` already exists
		if ( ! $this->isColumnExistsInTable( 's_articles', 'tara' ) ) {
			Shopware()->Db()->query( "ALTER TABLE `s_articles` ADD COLUMN `tara` DOUBLE NOT NULL DEFAULT 0.0" );
		}

		// check if the `origin` already exists
		if ( ! $this->isColumnExistsInTable( 's_articles', 'origin' ) ) {
			Shopware()->Db()->query( "ALTER TABLE `s_articles` ADD COLUMN `origin` CHAR(10) NOT NULL" );
		}

		// check if the `origin` already exists
		if ( ! $this->isColumnExistsInTable( 's_articles_attributes', 'yc_export' ) ) {
			/** @var CrudService $crudService */
			$service = Shopware()->Container()->get( 'shopware_attribute.crud_service' );
			$service->update( 's_articles_attributes', 'yc_export', 'boolean', [
				'label'            => 'Yellowcube Export',
				'helpText'         => 'Check this box if you want export this article to Yellowcube',

				//user has the opportunity to translate the attribute field for each shop
				'translatable'     => true,

				//attribute will be displayed in the backend module
				'displayInBackend' => true,

				//in case of multi_selection or single_selection type, article entities can be selected,
				'entity'           => 'Shopware\Models\Article\Article',

				//numeric position for the backend view, sorted ascending
				'position'         => 10,
			] );

			$metaDataCache = Shopware()->Models()->getConfiguration()->getMetadataCacheImpl();
			$metaDataCache->deleteAll();
			Shopware()->Models()->generateAttributeModels( [ 's_articles_attributes' ] );
		}

		// check if the `tariff` already exists
		if ( ! $this->isColumnExistsInTable( 's_order_details', 'tariff' ) ) {
			Shopware()->Db()->query( "ALTER TABLE `s_order_details` ADD COLUMN `tariff` CHAR(11) NOT NULL DEFAULT ''" );
		}

		// check if the `tara` already exists
		if ( ! $this->isColumnExistsInTable( 's_order_details', 'tara' ) ) {
			Shopware()->Db()->query( "ALTER TABLE `s_order_details` ADD COLUMN `tara` DOUBLE NOT NULL DEFAULT 0.0" );
		}

		// check if the `origin` already exists
		if ( ! $this->isColumnExistsInTable( 's_order_details', 'origin' ) ) {
			Shopware()->Db()->query( "ALTER TABLE `s_order_details` ADD COLUMN `origin` CHAR(10) NOT NULL" );
		}
	}

	/**
	 * Checks if the column exists in the table mentioned
	 *
	 * @param string $sTable Table name where to check?
	 * @param string $sColumn Table name what to check?
	 *
	 * @return true
	 */
	protected function isColumnExistsInTable( $sTable, $sColumn ) {
		$sResult = Shopware()->Db()->fetchAll( "SHOW COLUMNS FROM `" . $sTable . "` LIKE '" . $sColumn . "'" );

		return ! empty( $sResult ) ? true : false;
	}

	/**
	 * Creates Product table based on schema tool
	 *
	 * @param string $sSchema Name
	 *
	 * @return null
	 */
	private function createYellowcubeTable( $sSchema ) {
		$sFinalSchema = "\\" . $sSchema . "\\" . $sSchema;
		$em           = $this->Application()->Models();
		$tool         = new \Doctrine\ORM\Tools\SchemaTool( $em );

		$classes = [
			$em->getClassMetadata( "Shopware\CustomModels\AsignModels" . $sFinalSchema ),
		];

		try {
			$tool->createSchema( $classes );
		} catch ( \Doctrine\ORM\Tools\ToolsException $e ) {
			// ignore
		}
	}

	/**
	 * Update snippet definitions for the custom field
	 *
	 * @return null
	 */
	public function ycubeUpdateSnippets() {
		$aSnippets = [
			"UPDATE `s_core_snippets` SET `value` = 'EORI No.' WHERE `name` = 'billing/text_1_label'",
			"UPDATE `s_core_snippets` SET `value` = 'EORI Number for customer' WHERE `name` = 'billing/text_1_support'",
			"UPDATE `s_core_snippets` SET `value` = 'This field is also visible during Registration and Account Edit form on Shop Front-end.' WHERE `name` = 'billing/text_1_help_text'",
			"UPDATE `s_core_snippets` SET `value` = 'Specify EORI No.' WHERE `name` = 'billing/text_1_help_title'",
		];

		//run the query
		foreach ( $aSnippets as $query ) {
			Shopware()->Db()->query( $query );
		}
	}

	/**
	 * Make Yellocube template entry
	 *
	 * @return null
	 */
	public function ycubeTemplateEntry() {
		Shopware()->Db()->query( "INSERT IGNORE INTO `s_core_documents` SET `id` = 5, `name` = 'Yellowcube', `template` = 'index_yc.tpl', `numbers` = 'ycdoc', `left` = '25', `right` = '10', `top` = '20', `bottom` = '20', `pagebreak` = '10'" );
	}

	private function ycubeCreateCron() {
		/** CRONJOB Setup **/
		$this->subscribeEvent( 'Shopware_CronJob_InactArtCron', 'onRunInactArtCron' );
		$this->subscribeEvent( 'Shopware_CronJob_ActArtCron', 'onRunActArtCron' );
		$this->subscribeEvent( 'Shopware_CronJob_OrdCron', 'onRunOrdCron' );
		$this->subscribeEvent( 'Shopware_CronJob_InventCron', 'onRunInventCron' );

		$connection = $this->get( 'dbal_connection' );

		//Cronjobname, Controllername, Interval, [active]
		foreach ( $this->aCronDefaultEntries as $action => $name ) {
			if ( ! $connection->executeQuery( "SELECT 1 FROM s_crontab WHERE `action` = '{$action}'" )->fetchColumn() ) {
				$this->createCronJob( $name, $action, $this->iDefaultCronInterval, true );
			}
		}

		//Create cron for inventory (run once a day)
		if ( ! $connection->executeQuery( "SELECT 1 FROM s_crontab WHERE `action` = 'InventCron'" )->fetchColumn() ) {
			$oDate = new \DateTime( 'tomorrow' );
			$sNext = $oDate->format( 'Y-m-d 06:00:00' );

			$connection->insert( 's_crontab',
				[
					'name'             => 'A-SIGN YC Inventory',
					'action'           => 'InventCron',
					'next'             => $sNext,
					'start'            => null,
					'`interval`'       => 60 * 60 * 24, // once a day
					'active'           => 1,
					'disable_on_error' => 1,
					'end'              => $sNext,
					'pluginID'         => $this->getId(),
				], [ 'next' => 'datetime', 'end' => 'datetime' ]
			);
		}
	}

	/**
	 * Hook event to make sure the stock stays same after order is confirmed from the shop
	 *
	 * @param Enlight_Hook_HookArgs $args
	 */
	public function afterSaveOrder( Enlight_Hook_HookArgs $args ) {

		// get the argument which was returned by the method
		$orderNumber = $args->getReturn();
		$ordersRepo  = Shopware()->Models()->getRepository( \Shopware\Models\Order\Detail::class );

		$orderDetails = $ordersRepo->findBy( [ 'number' => $orderNumber ] );

		foreach ( $orderDetails as $detail ) {

			// do not anything for surcharge entries
			if ( ! $detail->getArticleId() ) {
				continue;
			}
			// get regular quantity which was subtracted
			$iQty = (int) $detail->getQuantity();

			// article number in order detail
			$articleNumber = $detail->getArticleNumber();

			$queryBuilder = Shopware()->Container()->get( 'dbal_connection' )->createQueryBuilder();

			// get current stock for the article number
			$iStock = $queryBuilder
				->select( 'details.instock' )
				->from( 's_articles_details', 'details' )
				->where( 'details.ordernumber = :id' )
				->setParameter( ':id', $articleNumber )
				->execute()->fetch( \PDO::FETCH_COLUMN );

			// add the purchased quantity back in stock
			$iStock += $iQty;

			// update the stock
			$builder = Shopware()->Models()->createQueryBuilder();
			$builder->update( 'Shopware\Models\Article\Detail', 'articleDetail' )
			        ->set( 'articleDetail.inStock', $iStock )
			        ->where( 'articleDetail.number = :id' )
			        ->setParameter( 'id', $articleNumber );
			$builder->getQuery()->execute();

		}

		$args->setReturn( $orderNumber );
	}

	/**
	 * Defines path for the backend controller
	 *
	 * @param Enlight_Event_EventArgs $arguments
	 *
	 * @return path
	 */
	public function getBackendController( Enlight_Event_EventArgs $args ) {
		$this->Application()->Template()->addTemplateDir(
			$this->Path() . 'Views/'
		);

		$this->Application()->Snippets()->addConfigDir(
			$this->Path() . 'Snippets/'
		);

		$this->registerCustomModels();

		return $this->Path() . '/Controllers/Backend/AsignYellowcube.php';
	}

	/**
	 * Defines path for the widget controller
	 *
	 * @param Enlight_Event_EventArgs $arguments
	 *
	 * @return path
	 */
	public function onGetWidgetController( Enlight_Event_EventArgs $arguments ) {
		$this->Application()->Template()->addTemplateDir(
			$this->Path() . 'Views/'
		);

		return $this->Path() . 'Controllers/Widgets/AsignWidgetCube.php';
	}

	/**
	 * Event listener function called via the Enlight_Controller_Action_PostDispatch_Frontend_Checkout event.
	 * The event is triggered when the reaches the thankyou page.
	 *
	 * @param Enlight_Event_EventArgs $arguments
	 */
	public function onPostDispatchCheckout( Enlight_Event_EventArgs $arguments ) {
		/**@var $controller Shopware_Controllers_Frontend_Checkout */
		$controller = $arguments->getSubject();

		// defines the default shopware VIEWS directory.
		$view = $controller->View();
		$view->addTemplateDir(
			__DIR__ . '/Views'
		);

		$view->extendsTemplate( 'frontend/plugins/asign_yellowcube/checkout/index.tpl' );
	}

	/**
	 * Event listener function called via the Enlight_Controller_Action_PostDispatch_Frontend_Register event.
	 *
	 * @param Enlight_Event_EventArgs $arguments
	 */
	public function onPostDispatchRegister( Enlight_Event_EventArgs $arguments ) {
		/**@var $controller Shopware_Controllers_Frontend_Register */
		$controller = $arguments->getSubject();

		// defines the default shopware VIEWS directory.
		$view = $controller->View();
		$view->addTemplateDir(
			__DIR__ . '/Views'
		);

		$view->extendsTemplate( 'frontend/plugins/asign_yellowcube/register/billing_fieldset.tpl' );
	}

	/**
	 * Event listener function called via the Enlight_Controller_Action_PostDispatch_Frontend_Account event.
	 *
	 * @param Enlight_Event_EventArgs $arguments
	 */
	public function onPostDispatchAccount( Enlight_Event_EventArgs $arguments ) {
		/**@var $controller Shopware_Controllers_Frontend_Account */
		$controller = $arguments->getSubject();

		// defines the default shopware VIEWS directory.
		$view = $controller->View();
		$view->addTemplateDir(
			__DIR__ . '/Views'
		);

		// get EORI number
		$sUserId           = $_SESSION['Shopware']['sUserId'];
		$view->sEoriNumber = Shopware()->Models()->getRepository( "Shopware\CustomModels\AsignModels\Orders\Orders" )->getOrderEoriNumber( $sUserId );

		$view->extendsTemplate( 'frontend/plugins/asign_yellowcube/account/billing.tpl' );
	}

	/**
	 * Hook listener function called via the Shopware_Components_Document event.
	 * The event is triggered when the reaches the thankyou page.
	 *
	 * @param Enlight_Event_EventArgs $arguments
	 */
	public function beforeDocumentRender( Enlight_Event_EventArgs $arguments ) {
		$aRenderer = $arguments->get( "_renderer" );

		// if renderer is empty then?
		// call is made from BE
		if ( empty( $aRenderer ) && $_REQUEST['temp'] ) {
			$aRenderer = [
				'render'  => 'pdf',
				'preview' => $_REQUEST['preview'],
				'orderid' => $_REQUEST['orderId'],
			];
		}

		try {
			/** @extends Shopware_Components_Document * */
			require_once( Shopware()->AppPath( "Plugins/Local/Backend/AsignYellowcube/Components/" ) . "PdfaDocument.php" );

			$pdfaDoc = Enlight_Class::Instance( 'PdfaDocument' );
			$pdfaDoc->pdfaRender( $aRenderer );
		} catch ( \Exception $e ) {
			$oLogs = Shopware()->Models()->getRepository( "Shopware\CustomModels\AsignModels\Errorlogs\Errorlogs" );
			$oLogs->saveLogsData( 'beforeDocumentRender', $e );
		}
	}

	/**
	 * called if the active articles cronjob is triggered
	 *
	 * @param Shopware_Components_Cron_CronJob $job
	 *
	 * @return void
	 */
	public function onRunActArtCron( Shopware_Components_Cron_CronJob $job ) {
		$this->doExecuteCron( 'art', 'ax' );
	}

	/**
	 * Run the article cron as per requirement...
	 * Types: articles, orders and inventory
	 *
	 * @param string $sType - Type of CRON request
	 * @param string $sMode - Mode of article cron
	 *
	 * @return void
	 */
	private function doExecuteCron( $sType, $sMode = null ) {
		$sFlag        = Shopware()->Plugins()->Backend()->AsignYellowcube()->Config()->sCronArtFlag;
		$cronResource = new \Shopware\AsignYellowcube\Components\Api\AsignYellowcubeCron();

		// trigger based on conditions?
		switch ( $sType ) {
			case 'art':
				$cronResource->autoInsertArticles( $sMode, $sFlag, true );
				break;
			case 'ord':
				$cronResource->autoSendYCOrders( true );
				break;
			case 'inv':
				$cronResource->autoFetchInventory( true );
				break;
			default:
				$cronResource->autoInsertArticles( 'xx', $sFlag, true ); // send all articles
				$cronResource->autoSendYCOrders( true ); // send all orders
				break;
		}
	}

	/**
	 * called if the in-active articles cronjob is triggered
	 *
	 * @param Shopware_Components_Cron_CronJob $job
	 *
	 * @return void
	 */
	public function onRunInactArtCron( Shopware_Components_Cron_CronJob $job ) {
		$this->doExecuteCron( 'art', 'ix' );
	}

	/**
	 * called if the orders cronjob is triggered
	 *
	 * @param Shopware_Components_Cron_CronJob $job
	 *
	 * @return void
	 */
	public function onRunOrdCron( Shopware_Components_Cron_CronJob $job ) {
		$this->doExecuteCron( 'ord' );
	}

	/**
	 * called if the inventory cronjob is triggered
	 *
	 * @param Shopware_Components_Cron_CronJob $job
	 *
	 * @return void
	 */
	public function onRunInventCron( Shopware_Components_Cron_CronJob $job ) {
		$this->doExecuteCron( 'inv' );
	}

}
