<?php
/**
 * This file handles CRON related functions
 *
 * @category  asign
 * @package   AsignYellowcube
 * @author    entwicklung@a-sign.ch
 * @copyright asign
 * @license   https://www.a-sign.ch/
 * @version   2.1.3
 * @link      https://www.a-sign.ch/
 * @see       AsignYellowcubeCron
 * @since     File available since Release 1.0
 */

namespace Shopware\AsignYellowcube\Components\Api;

use Exception;

/**
 * Handles CRON related function
 *
 * @category Asign
 * @package  AsignYellowcube
 * @author   entwicklung@a-sign.ch
 * @link     https://www.a-sign.ch
 */
class AsignYellowcubeCron {
	/** @var constants * */
	const YCRESPONSE = 'ycResponse';
	const YCWABRESPONSE = 'ycWabResponse';
	const YCWARRESPONSE = 'ycWarResponse';

	/** @var object * */
	protected $objErrorLog = null;

	/** @var object * */
	protected $objProduct = null;

	/** @var object * */
	protected $objOrders = null;

	/** @var object * */
	protected $objInventory = null;

	/** @var object * */
	protected $objYcubeCore = null;

	/**
	 * Class constructor
	 *
	 * @return null
	 */
	public function __construct() {
		$this->objYcubeCore = new AsignYellowcubeCore();
		$this->objErrorLog  = Shopware()->Models()->getRepository( "Shopware\CustomModels\AsignModels\Errorlogs\Errorlogs" );
		$this->objProduct   = Shopware()->Models()->getRepository( "Shopware\CustomModels\AsignModels\Product\Product" );
		$this->objOrders    = Shopware()->Models()->getRepository( "Shopware\CustomModels\AsignModels\Orders\Orders" );
		$this->objInventory = Shopware()->Models()->getRepository( "Shopware\CustomModels\AsignModels\Inventory\Inventory" );
	}

	/**
	 * Creates New customer Order in Yellowcube
	 *
	 * @param bool $isCron Called via cron
	 *
	 * @return array
	 */
	public function autoSendYCOrders( $isCron = false ) {
		$iCount = 0;

		// check order status
		$sWhere = " and `status` != " . \Shopware\Models\Order\Status::ORDER_STATE_READY_FOR_DELIVERY .
		          " and `status` != " . \Shopware\Models\Order\Status::ORDER_STATE_CANCELLED .
		          " and `status` != " . \Shopware\Models\Order\Status::ORDER_STATE_CLARIFICATION_REQUIRED .
		          " and `status` != " . \Shopware\Models\Order\Status::ORDER_STATE_COMPLETELY_DELIVERED;

		// check payment status
		$sWhere .= " and `paymentID` != 5 or (`paymentID` = 5 and `cleared` = 12)";

		$aOrders = Shopware()->Db()->fetchAll( "select `id`, `paymentid`, cleared from `s_order` where `ordernumber` > 0" . $sWhere );

		if ( count( $aOrders ) > 0 ) {
			foreach ( $aOrders as $order ) {
				try {
					$iOrdid = $order['id'];

					// check if the Status in the Order table
					$sRequestField = $this->getOrderRequestField( $iOrdid );
					$iStatusCode   = $this->getRecordedStatus( $iOrdid, 'asign_yellowcube_orders', $sRequestField );
					$aResponse     = [ 'success' => false ];

					// get YC response
					if ( ( $iStatusCode == null || $iStatusCode == 101 ) && $this->objOrders->getFieldData( $iOrdid, $sRequestField ) == '' ) {
						// execute the order object
						if ( $isCron ) {
							echo "Submitting Order for OrderID: " . $iOrdid . "\n";
						}
						$oDetails  = $this->objOrders->getOrderDetails( $iOrdid );
						$aResponse = $this->objYcubeCore->createYCCustomerOrder( $oDetails );
					} elseif ( $iStatusCode < 100 ) {
						// get the status
						if ( $isCron ) {
							echo "Requesting WAB status for OrderID: " . $iOrdid . "\n";
						}
						$aResponse = $this->objYcubeCore->getYCGeneralDataStatus( $iOrdid, "WAB" );
					} elseif ( $iStatusCode == 100 ) {
						// get the WAR status
						if ( $isCron ) {
							echo "Requesting WAR status for OrderID: " . $iOrdid . "\n";
						}
						$aResponse = $this->objYcubeCore->getYCGeneralDataStatus( $iOrdid, "WAR" );
					}

					// increment the counter
					if ( $aResponse['success'] ) {
						$this->objOrders->saveOrderResponseData( $aResponse, $iOrdid );
						$iCount ++;
					}

				} catch ( Exception $e ) {
					$this->objErrorLog->saveLogsData( 'Orders-CRON', $e );
				}
			}
		}

		// if cron then log in database too..
		if ( $isCron ) {
			$this->objErrorLog->saveLogsData( 'Orders-CRON', "Total Yellowcube Orders created: " . $iCount, true );
		} else {
			return $iCount;
		}
	}

	/**
	 * Checks which step is the present step the order is in based on the filled database fields
	 *
	 * @param   $orderId
	 *
	 * @return  string
	 */
	protected function getOrderRequestField( $orderId ) {
		$sResponseField = '';

		if ( $this->objOrders->getFieldData( $orderId, self::YCRESPONSE ) == '' ) {
			$sResponseField = self::YCRESPONSE;
		} elseif ( $this->objOrders->getFieldData( $orderId, self::YCWABRESPONSE ) == '' ) {
			$sResponseField = self::YCRESPONSE;
		} elseif ( $this->objOrders->getFieldData( $orderId, self::YCWARRESPONSE ) == '' ) {
			$sResponseField = self::YCWABRESPONSE;
		}

		return $sResponseField;
	}

	/**
	 * Returns status list from Yellowcube
	 *
	 * @param string $itemid item id
	 * @param string $sTable Table name
	 * @param string null $sResponseType
	 *
	 * @return int|null
	 */
	protected function getRecordedStatus( $itemid, $sTable, $sResponseField = null ) {
		$oModel = $this->objProduct;
		if ( $sTable == 'asign_yellowcube_orders' ) {
			$oModel = $this->objOrders;
		}
		$aParams = $oModel->getYellowcubeReport( $itemid, $sTable, $sResponseField );

		return isset( $aParams["StatusCode"] ) ? $aParams["StatusCode"] : null;
	}

	/**
	 * Inserts Article Master data to Yellowcube
	 *
	 * @param string $sMode - Mode of handling
	 *                        ax - Only active ones
	 *                        ia - Only Inactive ones
	 *                        xx - All articles
	 * @param string $sFlag - Type of action
	 *                        Insert(I),
	 *                        Update(U),
	 *                        Deactivate/Delete(D)
	 *
	 * @return integer|void
	 */
	public function autoInsertArticles( $sMode, $sFlag, $isCron = false ) {
		$iCount = 0;

		// get all the articles based on above condition...
		$sSql = "SELECT art.id FROM s_articles AS art";
		$sSql .= " JOIN s_articles_attributes AS attr ON art.id = attr.articleID";
		$sSql .= " WHERE attr.yc_export = 1";

		// form where condition based on options...
		switch ( $sMode ) {
			case "ax":
				$sSql .= ' AND art.active = 1';
				break;
			case "ix":
				$sSql .= ' AND art.active = 0';
				break;
			case "xx":
				break;
		}

		$aArticles = Shopware()->Db()->fetchAll( $sSql );

		if ( count( $aArticles ) > 0 ) {
			foreach ( $aArticles as $article ) {

				$oRequestData = new \stdClass();

				try {
					$artid    = $article['id'];
					$aDetails = $this->objProduct->getArticleDetails( $article['id'], $isCron );

					$iStatusCode = $this->getRecordedStatus( $artid, 'asign_yellowcube_product' );
					$aResponse   = [ 'success' => false ];

					// if not 100 then insert the article
					// execute the article object
					if ( $iStatusCode === null ) {
						if ( $isCron ) {
							echo "Submitting Article for Article-ID: " . $artid . "\n";
						}
						// get the formatted article data
						$oRequestData = $this->objYcubeCore->getYCFormattedArticleData( $aDetails, $sFlag );
						$aResponse    = $this->objYcubeCore->insertArticleMasterData( $oRequestData );
					} elseif ( $iStatusCode == 10 ) {
//					} elseif ( $iStatusCode == 100 ) { //TODO veriy if the status here should be 100 and not 10
						// get the status
						if ( $isCron ) {
							echo "Getting Article status for Article-ID: " . $artid . "\n";
						}
						$aResponse = $this->objYcubeCore->getYCGeneralDataStatus( $artid, "ART" );
					}

					// increment the counter
					if ( $aResponse['success'] ) {
						$this->objProduct->saveArticleResponseData( $aResponse['data'], $artid );
						$iCount ++;
					}
				} catch ( Exception $oEx ) {
					$sMessage = 'ArticleNr.: ' . $aDetails['ordernumber'] . ' - ';
					$sMessage .= $oEx->getMessage();
					if ( isset( $oRequestData->ArticleList->Article ) ) {
						$oRequestData = $oRequestData->ArticleList->Article;
					}
					ob_start();
					var_dump( $oRequestData );
					$sDevlog = ob_get_clean();
					$this->objErrorLog->saveCustomLogsData( 'autoInsertArticles', $sMessage, $sDevlog );
				}
			}
		}

		// if cron then log in database too..
		if ( $isCron ) {
			$this->objErrorLog->saveLogsData( 'Articles-CRON', "Total articles sent to Yellowcube: " . $iCount, true );
		} else {
			return $iCount;
		}
	}

	/**
	 * Returns inventory list from Yellowcube
	 *
	 * @return array
	 * @internal param Object $oObject Active object
	 *
	 */
	public function autoFetchInventory( $isCron = false ) {
		try {
			$aResponse = $this->objYcubeCore->getInventory();

			// update
			if ( $aResponse['success'] ) {
				$iCount = $this->objInventory->saveInventoryData( $aResponse["data"] );
			}
		} catch ( Exception $e ) {
			$this->objErrorLog->saveLogsData( 'Inventory-CRON', $e );
		}

		// if cron then log in database too..
		if ( $isCron ) {
			$this->objErrorLog->saveLogsData( 'Inventory-CRON', "Total updated items: " . $iCount, true );
		} else {
			return $iCount;
		}
	}
}
