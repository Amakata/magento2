<?php
/**
 * Import entity of grouped product type
 *
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\GroupedImportExport\Model\Import\Product\Type;

class Grouped extends \Magento\CatalogImportExport\Model\Import\Product\Type\AbstractType
{
    /**
     * Column names that holds values with particular meaning.
     *
     * @var array
     */
    protected $_specialAttributes = ['_associated_sku', '_associated_default_qty', '_associated_position'];

    /**
     * @var Grouped\Links
     */
    protected $links;

    /**
     * @param \Magento\Eav\Model\Resource\Entity\Attribute\Set\CollectionFactory $attrSetColFac
     * @param \Magento\Catalog\Model\Resource\Product\Attribute\CollectionFactory $prodAttrColFac
     * @param array $params
     * @param Grouped\Links $links
     */
    public function __construct(
        \Magento\Eav\Model\Resource\Entity\Attribute\Set\CollectionFactory $attrSetColFac,
        \Magento\Catalog\Model\Resource\Product\Attribute\CollectionFactory $prodAttrColFac,
        array $params,
        Grouped\Links $links
    ) {
        $this->links = $links;
        parent::__construct($attrSetColFac, $prodAttrColFac, $params);
    }

    /**
     * Save product type specific data.
     *
     * @return \Magento\CatalogImportExport\Model\Import\Product\Type\AbstractType
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function saveData()
    {
        $newSku = $this->_entityModel->getNewSku();
        $oldSku = $this->_entityModel->getOldSku();
        $attributes = $this->links->getAttributes();
        $productData = [];
        while ($bunch = $this->_entityModel->getNextBunch()) {
            $linksData = [
                'product_ids' => [],
                'attr_product_ids' => [],
                'position' => [],
                'qty' => [],
                'relation' => []
            ];
            foreach ($bunch as $rowNum => $rowData) {
                $associatedSku = isset($rowData['_associated_sku']) ? $rowData['_associated_sku'] : null;
                if (!$this->_entityModel->isRowAllowedToImport($rowData, $rowNum) || empty($associatedSku)) {
                    continue;
                }
                if (isset($newSku[$associatedSku])) {
                    $linkedProductId = $newSku[$associatedSku]['entity_id'];
                } elseif (isset($oldSku[$associatedSku])) {
                    $linkedProductId = $oldSku[$associatedSku]['entity_id'];
                } else {
                    continue;
                }
                $scope = $this->_entityModel->getRowScope($rowData);
                if (\Magento\CatalogImportExport\Model\Import\Product::SCOPE_DEFAULT == $scope) {
                    $productData = $newSku[$rowData[\Magento\CatalogImportExport\Model\Import\Product::COL_SKU]];
                } else {
                    $colAttrSet = \Magento\CatalogImportExport\Model\Import\Product::COL_ATTR_SET;
                    $rowData[$colAttrSet] = $productData['attr_set_code'];
                    $rowData[\Magento\CatalogImportExport\Model\Import\Product::COL_TYPE] = $productData['type_id'];
                }
                $productId = $productData['entity_id'];

                if ($this->_type != $rowData[\Magento\CatalogImportExport\Model\Import\Product::COL_TYPE]) {
                    continue;
                }
                $linksData['product_ids'][$productId] = true;
                $linksData['relation'][] = ['parent_id' => $productId, 'child_id' => $linkedProductId];
                $qty = empty($rowData['_associated_default_qty']) ? 0 : $rowData['_associated_default_qty'];
                $pos = empty($rowData['_associated_position']) ? 0 : $rowData['_associated_position'];

                if ($pos) {
                    $linksData['attr_product_ids'][$productId] = true;
                    $linksData['position']["{$productId} {$linkedProductId}"] = [
                        'product_link_attribute_id' => $attributes['position']['id'],
                        'value' => $pos
                    ];
                }
                if ($qty) {
                    $linksData['attr_product_ids'][$productId] = true;
                    $linksData['qty']["{$productId} {$linkedProductId}"] = [
                        'product_link_attribute_id' => $attributes['qty']['id'],
                        'value' => $qty
                    ];
                }
            }
            $this->links->saveLinksData($linksData);
        }
        return $this;
    }
}