<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Catalog\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Ui\Component\Form;
use Magento\Framework\Stdlib\ArrayManager;

/**
 * Data provider for main panel of product page
 */
class General extends AbstractModifier
{
    /**
     * @var LocatorInterface
     */
    protected $locator;

    /**
     * @var ArrayManager
     */
    protected $arrayManager;

    /**
     * @param LocatorInterface $locator
     * @param ArrayManager $arrayManager
     */
    public function __construct(
        LocatorInterface $locator,
        ArrayManager $arrayManager
    ) {
        $this->locator = $locator;
        $this->arrayManager = $arrayManager;
    }

    /**
     * {@inheritdoc}
     */
    public function modifyData(array $data)
    {
        $data = $this->customizeNumberFormat($data);
        $data = $this->customizeAdvancedPriceFormat($data);
        $modelId = $this->locator->getProduct()->getId();

        if (!isset($data[$modelId][static::DATA_SOURCE_DEFAULT][ProductAttributeInterface::CODE_STATUS])) {
            $data[$modelId][static::DATA_SOURCE_DEFAULT][ProductAttributeInterface::CODE_STATUS] = '1';
        }

        return $data;
    }

    /**
     * Customizing number fields
     *
     * @param array $data
     * @return array
     */
    protected function customizeNumberFormat(array $data)
    {
        $model = $this->locator->getProduct();
        $modelId = $model->getId();
        $numberFields = [
            ProductAttributeInterface::CODE_PRICE,
            ProductAttributeInterface::CODE_WEIGHT,
            ProductAttributeInterface::CODE_SPECIAL_PRICE,
            ProductAttributeInterface::CODE_COST,
        ];

        foreach ($numberFields as $fieldCode) {
            $path = $modelId . '/' . self::DATA_SOURCE_DEFAULT . '/' . $fieldCode;
            $number = (float)$this->arrayManager->get($path, $data);
            $data = $this->arrayManager->replace(
                $path,
                $data,
                $this->formatNumber($number)
            );
        }

        return $data;
    }

    /**
     * Formatting numeric field
     *
     * @param float $number
     * @param int $decimals
     * @return string
     */
    protected function formatNumber($number, $decimals = 2)
    {
        return number_format($number, $decimals);
    }

    /**
     * Customizing number fields for advanced price
     *
     * @param array $data
     * @return array
     */
    protected function customizeAdvancedPriceFormat(array $data)
    {
        $modelId = $this->locator->getProduct()->getId();
        $fieldCode = ProductAttributeInterface::CODE_TIER_PRICE;

        if (isset($data[$modelId][self::DATA_SOURCE_DEFAULT][$fieldCode])) {
            foreach ($data[$modelId][self::DATA_SOURCE_DEFAULT][$fieldCode] as &$value) {
                $value[ProductAttributeInterface::CODE_TIER_PRICE_FIELD_PRICE] =
                    $this->formatNumber($value[ProductAttributeInterface::CODE_TIER_PRICE_FIELD_PRICE]);
                $value[ProductAttributeInterface::CODE_TIER_PRICE_FIELD_PRICE_QTY] =
                    (int)$value[ProductAttributeInterface::CODE_TIER_PRICE_FIELD_PRICE_QTY];
            }
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function modifyMeta(array $meta)
    {
        $meta = $this->prepareFirstPanel($meta);
        $meta = $this->customizeStatusField($meta);
        $meta = $this->customizeWeightField($meta);
        $meta = $this->customizeNewDateRangeField($meta);
        $meta = $this->customizeNameListeners($meta);

        return $meta;
    }

    /**
     * Disable collapsible and set empty label
     *
     * @param array $meta
     * @return array
     */
    protected function prepareFirstPanel(array $meta)
    {
        $generalPanelName = $this->getGeneralPanelName($meta);

        $meta[$generalPanelName]['arguments']['data']['config']['label'] = '';
        $meta[$generalPanelName]['arguments']['data']['config']['collapsible'] = false;

        return $meta;
    }

    /**
     * Customize Status field
     *
     * @param array $meta
     * @return array
     */
    protected function customizeStatusField(array $meta)
    {
        $switcherConfig = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'dataType' => Form\Element\DataType\Number::NAME,
                        'formElement' => Form\Element\Checkbox::NAME,
                        'componentType' => Form\Field::NAME,
                        'prefer' => 'toggle',
                        'valueMap' => [
                            'true' => '1',
                            'false' => '2'
                        ],
                    ],
                ],
            ],
        ];

        $path = $this->getElementArrayPath($meta, ProductAttributeInterface::CODE_STATUS);
        $meta = $this->arrayManager->merge($path, $meta, $switcherConfig);

        return $meta;
    }

    /**
     * Customize Weight filed
     *
     * @param array $meta
     * @return array
     */
    protected function customizeWeightField(array $meta)
    {
        if ($weightPath = $this->getElementArrayPath($meta, ProductAttributeInterface::CODE_WEIGHT)) {
            if ($this->locator->getProduct()->getTypeId() !== \Magento\Catalog\Model\Product\Type::TYPE_VIRTUAL) {
                $weightPath = $this->getElementArrayPath($meta, ProductAttributeInterface::CODE_WEIGHT);
                $meta = $this->arrayManager->merge(
                    $weightPath,
                    $meta,
                    [
                        'arguments' => [
                            'data' => [
                                'config' => [
                                    'dataScope' => ProductAttributeInterface::CODE_WEIGHT,
                                    'validation' => [
                                        'validate-number' => true,
                                    ],
                                    'additionalClasses' => 'admin__field-small',
                                    'addafter' => $this->locator->getStore()->getConfig('general/locale/weight_unit'),
                                    'imports' => [
                                        'disabled' => '!${$.provider}:' . self::DATA_SCOPE_PRODUCT
                                            . '.product_has_weight:value'
                                    ]
                                ],
                            ],
                        ],
                    ]
                );

                $containerPath = $this->getElementArrayPath(
                    $meta,
                    static::CONTAINER_PREFIX . ProductAttributeInterface::CODE_WEIGHT
                );
                $meta = $this->arrayManager->merge($containerPath, $meta, [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'component' => 'Magento_Ui/js/form/components/group',
                            ],
                        ],
                    ],
                ]);

                $hasWeightPath = $this->arrayManager->slicePath($weightPath, 0, -1) . '/'
                    . ProductAttributeInterface::CODE_HAS_WEIGHT;
                $meta = $this->arrayManager->set(
                    $hasWeightPath,
                    $meta,
                    [
                        'arguments' => [
                            'data' => [
                                'config' => [
                                    'dataType' => 'boolean',
                                    'formElement' => Form\Element\Select::NAME,
                                    'componentType' => Form\Field::NAME,
                                    'dataScope' => 'product_has_weight',
                                    'label' => '',
                                    'options' => [
                                        [
                                            'label' => __('This item has weight'),
                                            'value' => 1
                                        ],
                                        [
                                            'label' => __('This item has no weight'),
                                            'value' => 0
                                        ],
                                    ],
                                    'value' => (int)$this->locator->getProduct()->getTypeInstance()->hasWeight(),
                                ],
                            ],
                        ]
                    ]
                );
            }
        }

        return $meta;
    }

    /**
     * Customize "Set Product as New" date fields
     *
     * @param array $meta
     * @return array
     */
    protected function customizeNewDateRangeField(array $meta)
    {
        $fromField = 'news_from_date';
        $toField = 'news_to_date';

        $fromFieldPath = $this->getElementArrayPath($meta, $fromField);
        $toFieldPath = $this->getElementArrayPath($meta, $toField);

        if ($fromFieldPath && $toFieldPath) {
            $fromContainerPath = $this->arrayManager->slicePath($fromFieldPath, 0, -2);
            $toContainerPath = $this->arrayManager->slicePath($toFieldPath, 0, -2);
            $scopeLabel = $this->arrayManager->get($fromFieldPath . self::META_CONFIG_PATH . '/scopeLabel', $meta);

            $meta = $this->arrayManager->merge(
                $fromFieldPath . self::META_CONFIG_PATH,
                $meta,
                [
                    'label' => __('Set Product as New From'),
                    'scopeLabel' => null,
                    'additionalClasses' => 'admin__field-date',
                ]
            );
            $meta = $this->arrayManager->merge(
                $toFieldPath . self::META_CONFIG_PATH,
                $meta,
                [
                    'label' => __('To'),
                    'scopeLabel' => null,
                    'additionalClasses' => 'admin__field-date',
                ]
            );
            $meta = $this->arrayManager->merge(
                $fromContainerPath . self::META_CONFIG_PATH,
                $meta,
                [
                    'label' => __('Set Product as New From'),
                    'additionalClasses' => 'admin__control-grouped-date',
                    'breakLine' => false,
                    'component' => 'Magento_Ui/js/form/components/group',
                    'scopeLabel' => $scopeLabel,
                ]
            );
            $meta = $this->arrayManager->set(
                $fromContainerPath . '/children/' . $toField,
                $meta,
                $this->arrayManager->get($toFieldPath, $meta)
            );

            $meta =  $this->arrayManager->remove($toContainerPath, $meta);
        }

        return $meta;
    }

    /**
     * Add links for fields depends of product name
     *
     * @param array $meta
     * @return array
     */
    protected function customizeNameListeners(array $meta)
    {
        $listeners = [
            ProductAttributeInterface::CODE_SKU,
            ProductAttributeInterface::CODE_SEO_FIELD_META_TITLE,
            ProductAttributeInterface::CODE_SEO_FIELD_META_KEYWORD,
            ProductAttributeInterface::CODE_SEO_FIELD_META_DESCRIPTION,
        ];
        foreach ($listeners as $listener) {
            $listenerPath = $this->getElementArrayPath($meta, $listener);
            $importsConfig = [
                'arguments' => [
                    'data' => [
                        'config' => [
                            'component' => 'Magento_Catalog/js/components/import-handler',
                            'imports' => [
                                'handleChanges' => '${$.provider}:data.product.name',
                            ],
                        ],
                    ],
                ],
            ];

            $meta = $this->arrayManager->merge($listenerPath, $meta, $importsConfig);
        }

        $skuPath = $this->getElementArrayPath($meta, ProductAttributeInterface::CODE_SKU);
        $meta = $this->arrayManager->merge(
            $skuPath,
            $meta,
            [
                'arguments' => [
                    'data' => [
                        'config' => [
                            'autoImportIfEmpty' => true,
                            'allowImport' => $this->locator->getProduct()->getId() ? false : true,
                        ],
                    ],
                ],
            ]
        );

        $namePath = $this->getElementArrayPath($meta, ProductAttributeInterface::CODE_NAME);

        return $this->arrayManager->merge(
            $namePath,
            $meta,
            [
                'arguments' => [
                    'data' => [
                        'config' => [
                            'valueUpdate' => 'keyup'
                        ],
                    ],
                ],
            ]
        );
    }
}
