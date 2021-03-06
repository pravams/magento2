<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Magento
 * @package     Magento_Sales
 * @copyright   Copyright (c) 2013 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Abstract items renderer
 */
namespace Magento\Sales\Block\Adminhtml\Items;

class AbstractItems extends \Magento\Backend\Block\Template
{
    /**
     * Block alias fallback
     */
    const DEFAULT_TYPE = 'default';

    /**
     * Renderers for other column with column name key
     * block    => the block name
     * template => the template file
     * renderer => the block object
     *
     * @var array
     */
    protected $_columnRenders = array();

    /**
     * Flag - if it is set method canEditQty will return value of it
     *
     * @var boolean | null
     */
    protected $_canEditQty = null;

    /**
     * Core registry
     *
     * @var \Magento\Core\Model\Registry
     */
    protected $_coreRegistry = null;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $_productFactory;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \Magento\Core\Model\Registry $registry
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Core\Model\Registry $registry,
        array $data = array()
    ) {
        $this->_productFactory = $productFactory;
        $this->_coreRegistry = $registry;
        parent::__construct($context, $data);
    }

    /**
     * Add column renderers
     *
     * @param array $blocks
     * @return $this
     */
    public function setColumnRenders(array $blocks)
    {
        foreach ($blocks as $blockName) {
            $block = $this->getLayout()->getBlock($blockName);
            if ($block->getRenderedBlock() === null) {
                $block->setRenderedBlock($this);
            }
            $this->_columnRenders[$blockName] = $block;
        }
        return $this;
    }

    /**
     * Retrieve item renderer block
     *
     * @param string $type
     * @return \Magento\View\Element\AbstractBlock
     * @throws \RuntimeException
     */
    public function getItemRenderer($type)
    {
        /** @var $renderer \Magento\Sales\Block\Adminhtml\Items\AbstractItems */
        $renderer = $this->getChildBlock($type) ?: $this->getChildBlock(self::DEFAULT_TYPE);
        if (!$renderer instanceof \Magento\View\Element\BlockInterface) {
            throw new \RuntimeException('Renderer for type "' . $type . '" does not exist.');
        }
        $renderer->setColumnRenders($this->getLayout()->getGroupChildNames($this->getNameInLayout(), 'column'));

        return $renderer;
    }

    /**
     * Retrieve column renderer block
     *
     * @param string $column
     * @param string $compositePart
     * @return \Magento\View\Element\AbstractBlock
     */
    public function getColumnRenderer($column, $compositePart = '')
    {
        $column = 'column_' . $column;
        if (isset($this->_columnRenders[$column . '_' . $compositePart])) {
            $column .= '_' . $compositePart;
        }
        if (!isset($this->_columnRenders[$column])) {
            return false;
        }
        return $this->_columnRenders[$column];
    }

    /**
     * Retrieve rendered item html content
     *
     * @param \Magento\Object $item
     * @return string
     */
    public function getItemHtml(\Magento\Object $item)
    {
        if ($item->getOrderItem()) {
            $type = $item->getOrderItem()->getProductType();
        } else {
            $type = $item->getProductType();
        }

        return $this->getItemRenderer($type)
            ->setItem($item)
            ->setCanEditQty($this->canEditQty())
            ->toHtml();
    }

    /**
     * Retrieve rendered item extra info html content
     *
     * @param \Magento\Object $item
     * @return string
     */
    public function getItemExtraInfoHtml(\Magento\Object $item)
    {
        $extraInfoBlock = $this->getChildBlock('order_item_extra_info');
        if ($extraInfoBlock) {
            return $extraInfoBlock
                ->setItem($item)
                ->toHtml();
        }
        return '';
    }

    /**
     * Retrieve rendered column html content
     *
     * @param \Magento\Object $item
     * @param string $column the column key
     * @param string $field the custom item field
     * @return string
     */
    public function getColumnHtml(\Magento\Object $item, $column, $field = null)
    {
        if ($item->getOrderItem()) {
            $block = $this->getColumnRenderer($column, $item->getOrderItem()->getProductType());
        } else {
            $block = $this->getColumnRenderer($column, $item->getProductType());
        }

        if ($block) {
            $block->setItem($item);
            if (!is_null($field)) {
                $block->setField($field);
            }
            return $block->toHtml();
        }
        return '&nbsp;';
    }

    public function getCreditmemo()
    {
        return $this->_coreRegistry->registry('current_creditmemo');
    }

    /**
     * ######################### SALES ##################################
     */

    /**
     * Retrieve available order
     *
     * @throws \Magento\Core\Exception
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        if ($this->hasOrder()) {
            return $this->getData('order');
        }
        if ($this->_coreRegistry->registry('current_order')) {
            return $this->_coreRegistry->registry('current_order');
        }
        if ($this->_coreRegistry->registry('order')) {
            return $this->_coreRegistry->registry('order');
        }
        if ($this->getInvoice()) {
            return $this->getInvoice()->getOrder();
        }
        if ($this->getCreditmemo()) {
            return $this->getCreditmemo()->getOrder();
        }
        if ($this->getItem()->getOrder()) {
            return $this->getItem()->getOrder();
        }

        throw new \Magento\Core\Exception(__('We cannot get the order instance.'));
    }

    /**
     * Retrieve price data object
     *
     * @return \Magento\Sales\Model\Order
     */
    public function getPriceDataObject()
    {
        $obj = $this->getData('price_data_object');
        if (is_null($obj)) {
            return $this->getOrder();
        }
        return $obj;
    }

    /**
     * Retrieve price attribute html content
     *
     * @param string $code
     * @param bool $strong
     * @param string $separator
     * @return string
     */
    public function displayPriceAttribute($code, $strong = false, $separator = '<br />')
    {
        if ($code == 'tax_amount' && $this->getOrder()->getRowTaxDisplayPrecision()) {
            return $this->displayRoundedPrices(
                $this->getPriceDataObject()->getData('base_' . $code),
                $this->getPriceDataObject()->getData($code),
                $this->getOrder()->getRowTaxDisplayPrecision(),
                $strong,
                $separator
            );
        } else {
            return $this->displayPrices(
                $this->getPriceDataObject()->getData('base_' . $code),
                $this->getPriceDataObject()->getData($code),
                $strong,
                $separator
            );
        }
    }

    /**
     * Retrieve price formatted html content
     *
     * @param float $basePrice
     * @param float $price
     * @param bool $strong
     * @param string $separator
     * @return string
     */
    public function displayPrices($basePrice, $price, $strong = false, $separator = '<br />')
    {
        return $this->displayRoundedPrices($basePrice, $price, 2, $strong, $separator);
    }

    /**
     * Display base and regular prices with specified rounding precision
     *
     * @param   float $basePrice
     * @param   float $price
     * @param   int $precision
     * @param   bool $strong
     * @param   string $separator
     * @return  string
     */
    public function displayRoundedPrices($basePrice, $price, $precision = 2, $strong = false, $separator = '<br />')
    {
        if ($this->getOrder()->isCurrencyDifferent()) {
            $res = '';
            $res .= $this->getOrder()->formatBasePricePrecision($basePrice, $precision);
            $res .= $separator;
            $res .= $this->getOrder()->formatPricePrecision($price, $precision, true);
        } else {
            $res = $this->getOrder()->formatPricePrecision($price, $precision);
            if ($strong) {
                $res = '<strong>' . $res . '</strong>';
            }
        }
        return $res;
    }

    /**
     * Retrieve include tax html formatted content
     *
     * @param \Magento\Object $item
     * @return string
     */
    public function displayPriceInclTax(\Magento\Object $item)
    {
        $qty = ($item->getQtyOrdered() ? $item->getQtyOrdered() : ($item->getQty() ? $item->getQty() : 1));
        $baseTax = $item->getTaxBeforeDiscount()
            ? $item->getTaxBeforeDiscount()
            : ($item->getTaxAmount() ? $item->getTaxAmount() : 0);
        $tax = $item->getBaseTaxBeforeDiscount()
            ? $item->getBaseTaxBeforeDiscount()
            : ($item->getBaseTaxAmount() ? $item->getBaseTaxAmount() : 0);

        $basePriceTax = 0;
        $priceTax = 0;

        if (floatval($qty)) {
            $basePriceTax = $item->getBasePrice() + $baseTax / $qty;
            $priceTax = $item->getPrice() + $tax / $qty;
        }

        return $this->displayPrices(
            $this->getOrder()->getStore()->roundPrice($basePriceTax),
            $this->getOrder()->getStore()->roundPrice($priceTax)
        );
    }

    /**
     * Retrieve subtotal price include tax html formated content
     *
     * @param \Magento\Object $item
     * @return string
     */
    public function displaySubtotalInclTax($item)
    {
        $baseTax = $item->getTaxBeforeDiscount()
            ? $item->getTaxBeforeDiscount()
            : ($item->getTaxAmount() ? $item->getTaxAmount() : 0);
        $tax = $item->getBaseTaxBeforeDiscount()
            ? $item->getBaseTaxBeforeDiscount()
            : ($item->getBaseTaxAmount() ? $item->getBaseTaxAmount() : 0);

        return $this->displayPrices(
            $item->getBaseRowTotal() + $baseTax,
            $item->getRowTotal() + $tax
        );
    }

    /**
     * Retrieve tax calculation html content
     *
     * @param \Magento\Object $item
     * @return string
     */
    public function displayTaxCalculation(\Magento\Object $item)
    {
        if ($item->getTaxPercent() && $item->getTaxString() == '') {
            $percents = array($item->getTaxPercent());
        } elseif ($item->getTaxString()) {
            $percents = explode(\Magento\Tax\Model\Config::CALCULATION_STRING_SEPARATOR, $item->getTaxString());
        } else {
            return '0%';
        }

        foreach ($percents as &$percent) {
            $percent = sprintf('%.2f%%', $percent);
        }
        return implode(' + ', $percents);
    }

    /**
     * Retrieve tax with persent html content
     *
     * @param \Magento\Object $item
     * @return string
     */
    public function displayTaxPercent(\Magento\Object $item)
    {
        if ($item->getTaxPercent()) {
            return sprintf('%s%%', $item->getTaxPercent() + 0);
        } else {
            return '0%';
        }
    }

    /**
     *  INVOICES
     */

    /**
     * Check shipment availability for current invoice
     *
     * @return bool
     */
    public function canCreateShipment()
    {
        foreach ($this->getInvoice()->getAllItems() as $item) {
            if ($item->getOrderItem()->getQtyToShip()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Setter for flag _canEditQty
     *
     * @param bool $value
     * @return \Magento\Sales\Block\Adminhtml\Items\AbstractItems
     * @see self::_canEditQty
     * @see self::canEditQty
     */
    public function setCanEditQty($value)
    {
        $this->_canEditQty = $value;
        return $this;
    }

    /**
     * Check availability to edit quantity of item
     *
     * @return boolean
     */
    public function canEditQty()
    {
        /**
         * If parent block has set
         */
        if (!is_null($this->_canEditQty)) {
            return $this->_canEditQty;
        }

        /**
         * Disable editing of quantity of item if creating of shipment forced
         * and ship partially disabled for order
         */
        if ($this->getOrder()->getForcedShipmentWithInvoice()
            && ($this->canShipPartially($this->getOrder()) || $this->canShipPartiallyItem($this->getOrder()))
        ) {
            return false;
        }
        if ($this->getOrder()->getPayment()->canCapture()) {
            return $this->getOrder()->getPayment()->canCapturePartial();
        }
        return true;
    }

    public function canCapture()
    {
        if ($this->_authorization->isAllowed('Magento_Sales::capture')) {
            return $this->getInvoice()->canCapture();
        }
        return false;
    }

    public function formatPrice($price)
    {
        return $this->getOrder()->formatPrice($price);
    }

    /**
     * Retrieve source
     *
     * @return \Magento\Sales\Model\Order\Invoice
     */
    public function getSource()
    {
        return $this->getInvoice();
    }

    /**
     * Retrieve invoice model instance
     *
     * @return \Magento\Sales\Model\Order\Invoice
     */
    public function getInvoice()
    {
        return $this->_coreRegistry->registry('current_invoice');
    }

    /**
     * CREDITMEMO
     */

    public function canReturnToStock()
    {
        if ($this->_storeConfig->getConfig(\Magento\CatalogInventory\Model\Stock\Item::XML_PATH_CAN_SUBTRACT)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Whether to show 'Return to stock' checkbox for item
     * @param \Magento\Sales\Model\Order\Creditmemo\Item $item
     * @return bool
     */
    public function canReturnItemToStock($item = null)
    {
        $canReturnToStock = $this->_storeConfig->getConfig(
            \Magento\CatalogInventory\Model\Stock\Item::XML_PATH_CAN_SUBTRACT
        );
        if (!is_null($item)) {
            if (!$item->hasCanReturnToStock()) {
                $product = $this->_productFactory->create()->load($item->getOrderItem()->getProductId());
                if ($product->getId() && $product->getStockItem()->getManageStock()) {
                    $item->setCanReturnToStock(true);
                } else {
                    $item->setCanReturnToStock(false);
                }
            }
            $canReturnToStock = $item->getCanReturnToStock();
        }
        return $canReturnToStock;
    }

    /**
     * Whether to show 'Return to stock' column for item parent
     * @param \Magento\Sales\Model\Order\Creditmemo\Item $item
     * @return bool
     */
    public function canParentReturnToStock($item = null)
    {
        $canReturnToStock = $this->_storeConfig->getConfig(
            \Magento\CatalogInventory\Model\Stock\Item::XML_PATH_CAN_SUBTRACT
        );
        if (!is_null($item)) {
            if ($item->getCreditmemo()->getOrder()->hasCanReturnToStock()) {
                $canReturnToStock = $item->getCreditmemo()->getOrder()->getCanReturnToStock();
            }
        } elseif ($this->getOrder()->hasCanReturnToStock()) {
            $canReturnToStock = $this->getOrder()->getCanReturnToStock();
        }
        return $canReturnToStock;
    }

    /**
     * Return true if can ship partially
     *
     * @param \Magento\Sales\Model\Order|null $order
     * @return boolean
     */
    public function canShipPartially($order = null)
    {
        if (is_null($order) || !$order instanceof \Magento\Sales\Model\Order) {
            $order = $this->_coreRegistry->registry('current_shipment')->getOrder();
        }
        $value = $order->getCanShipPartially();
        if (!is_null($value) && !$value) {
            return false;
        }
        return true;
    }

    /**
     * Return true if can ship items partially
     *
     * @param \Magento\Sales\Model\Order|null $order
     * @return boolean
     */
    public function canShipPartiallyItem($order = null)
    {
        if (is_null($order) || !$order instanceof \Magento\Sales\Model\Order) {
            $order = $this->_coreRegistry->registry('current_shipment')->getOrder();
        }
        $value = $order->getCanShipPartiallyItem();
        if (!is_null($value) && !$value) {
            return false;
        }
        return true;
    }

    public function isShipmentRegular()
    {
        if (!$this->canShipPartiallyItem() || !$this->canShipPartially()) {
            return false;
        }
        return true;
    }
}
