<?php

namespace Oro\Bundle\ProductBundle\Tests\Functional\ImportExport\Strategy;

use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamily;
use Oro\Bundle\EntityExtendBundle\Entity\AbstractEnumValue;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use Oro\Bundle\ImportExportBundle\Context\Context;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\ImportExport\Normalizer\LocalizationCodeFormatter;
use Oro\Bundle\LocaleBundle\ImportExport\Strategy\LocalizedFallbackValueAwareStrategy;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Component\Testing\Unit\EntityTrait;

class LocalizedFallbackValueAwareStrategyTest extends WebTestCase
{
    use EntityTrait;

    /** @var LocalizedFallbackValueAwareStrategy */
    protected $strategy;

    protected function setUp()
    {
        $this->initClient();
        $this->client->useHashNavigation(true);

        $container = $this->getContainer();

        if (!$container->hasParameter('oro_product.entity.product.class')) {
            $this->markTestSkipped('ProductBundle is missing');
        }
        $this->loadFixtures(
            ['Oro\Bundle\ProductBundle\Tests\Functional\DataFixtures\LoadProductData']
        );

        $container->get('oro_importexport.field.database_helper')->onClear();

        $this->strategy = new LocalizedFallbackValueAwareStrategy(
            $container->get('event_dispatcher'),
            $container->get('oro_importexport.strategy.import.helper'),
            $container->get('oro_entity.helper.field_helper'),
            $container->get('oro_importexport.field.database_helper'),
            $container->get('oro_entity.entity_class_name_provider'),
            $container->get('translator'),
            $container->get('oro_importexport.strategy.new_entities_helper'),
            $container->get('oro_entity.doctrine_helper'),
            $container->get('oro_security.owner.checker')
        );
        $this->strategy->setLocalizedFallbackValueClass(
            $container->getParameter('oro_locale.entity.localized_fallback_value.class')
        );
    }

    /**
     * @param array $entityData
     * @param array $expectedNames
     * @param array $itemData
     *
     * @dataProvider processDataProvider
     */
    public function testProcess(array $entityData = [], array $expectedNames = [], array $itemData = [])
    {
        $entityData = $this->convertArrayToEntities($entityData);

        $productClass = $this->getContainer()->getParameter('oro_product.entity.product.class');

        $context = new Context([]);
        $context->setValue('itemData', $itemData);
        $this->strategy->setImportExportContext($context);
        $this->strategy->setEntityName($productClass);

        $inventoryStatusClassName = ExtendHelper::buildEnumValueClassName('prod_inventory_status');
        /** @var AbstractEnumValue $inventoryStatus */
        $inventoryStatus = $this->getContainer()->get('doctrine')->getRepository($inventoryStatusClassName)
            ->find('in_stock');

        /** @var \Oro\Bundle\ProductBundle\Entity\Product $entity */
        $entity = $this->getEntity($productClass, $entityData);
        $entity->setInventoryStatus($inventoryStatus);

        /** @var AttributeFamily $attributeFamily */
        $attributeFamily = $this->getEntity(AttributeFamily::class, ['code' => 'default_family']);
        $entity->setAttributeFamily($attributeFamily);
        /** @var \Oro\Bundle\ProductBundle\Entity\Product $result */
        $result = $this->strategy->process($entity);

        foreach ($result->getNames() as $localizedFallbackValue) {
            $localizationCode = LocalizationCodeFormatter::formatName($localizedFallbackValue->getLocalization());
            $this->assertArrayHasKey($localizationCode, $expectedNames);

            $expectedName = $expectedNames[$localizationCode];
            if (!empty($expectedName['reference'])) {
                /**
                 * Validate that id matched from existing collection and does not affect other entities
                 * @var LocalizedFallbackValue $reference
                 */
                $reference = $this->getReference($expectedName['reference']);
                $this->assertEquals($reference->getId(), $localizedFallbackValue->getId());
            } else {
                $this->assertNull($localizedFallbackValue->getId());
            }

            $this->assertEquals($expectedName['text'], $localizedFallbackValue->getText());
            $this->assertEquals($expectedName['string'], $localizedFallbackValue->getString());
            $this->assertEquals($expectedName['fallback'], $localizedFallbackValue->getFallback());
        }
    }

    /**
     * @return array
     */
    public function processDataProvider()
    {
        return [
            [
                [
                    'sku' => 'product-1',
                    'primaryUnitPrecision' => [
                        'testEntity' => 'Oro\Bundle\ProductBundle\Entity\ProductUnitPrecision',
                        'testProperties' => [
                            'unit' => $this->getEntity(
                                'Oro\Bundle\ProductBundle\Entity\ProductUnit',
                                ['code' => 'kg']
                            ),
                            'precision' => 3,
                        ]
                    ],
                    'names' => [
                        [
                            'testEntity' => 'Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue',
                            'testProperties' => [
                                'string' => 'product-1 Default Name'
                            ],
                        ],
                        [
                            'testEntity' => 'Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue',
                            'testProperties' => [
                                'string' => 'product-1 en_US Name',
                                'fallback' => 'parent_localization',
                                'localization' => [
                                    'testEntity' => Localization::class,
                                    'testProperties' => [
                                        'name' => 'English (United States)',
                                    ],
                                ],
                            ]
                        ],
                        [
                            'testEntity' => 'Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue',
                            'testProperties' => [
                                'string' => 'product-1 en_CA Name',
                                'localization' => [
                                    'testEntity' => Localization::class,
                                    'testProperties' => [
                                        'name' => 'English (Canada)',
                                    ],
                                ],
                            ]
                        ],
                    ],
                ],
                [
                    'default' => [
                        'reference' => 'product-1.names.default',
                        'string' => 'product-1 Default Name',
                        'text' => null,
                        'fallback' => null,
                    ],
                    'English (United States)' => [
                        'reference' => 'product-1.names.en_US',
                        'string' => 'product-1 en_US Name',
                        'text' => null,
                        'fallback' => 'system',
                    ],
                    'English (Canada)' => [
                        'reference' => null,
                        'string' => 'product-1 en_CA Name',
                        'text' => null,
                        'fallback' => null,
                    ],
                ],
                [
                    'sku' => 'product-1',
                    'names' => [
                        'English (United States)' => [
                            'string' => 'product-1 en_US Name',
                            'fallback' => 'parent_localization',
                        ],
                        'English (Canada)' => [
                            'string' => 'product-1 en_CA Name',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array $entityData
     * @param callable $resultCallback
     * @dataProvider skippedDataProvider
     */
    public function testProcessSkipped(array $entityData, callable $resultCallback)
    {
        $entityData = $this->convertArrayToEntities($entityData);

        $entityData['attributeFamily'] = $this
            ->getContainer()
            ->get('doctrine')
            ->getRepository(AttributeFamily::class)
            ->findOneBy(['code' => $entityData['attributeFamily']]);

        $productClass = $this->getContainer()->getParameter('oro_product.entity.product.class');

        $this->strategy->setImportExportContext(new Context([]));
        $this->strategy->setEntityName($productClass);

        $inventoryStatusClassName = ExtendHelper::buildEnumValueClassName('prod_inventory_status');
        /** @var AbstractEnumValue $inventoryStatus */
        $inventoryStatus = $this->getContainer()->get('doctrine')->getRepository($inventoryStatusClassName)
            ->find('in_stock');

        /** @var \Oro\Bundle\ProductBundle\Entity\Product $entity */
        $entity = $this->getEntity($productClass, $entityData);
        $entity->setInventoryStatus($inventoryStatus);
        $entity->setOwner(
            $this->getContainer()->get('doctrine')->getRepository('OroOrganizationBundle:BusinessUnit')->findOneBy([])
        );

        $resultCallback($this->strategy->process($entity));
    }

    /**
     * @return array
     */
    public function skippedDataProvider()
    {
        return [
            'new product, no fallback from another entity' => [
                [
                    'sku' => 'new_sku',
                    'attributeFamily' => 'default_family',
                    'primaryUnitPrecision' => [
                        'testEntity' => 'Oro\Bundle\ProductBundle\Entity\ProductUnitPrecision',
                        'testProperties' => [
                            'unit' => $this->getEntity(
                                'Oro\Bundle\ProductBundle\Entity\ProductUnit',
                                ['code' => 'kg']
                            ),
                            'precision' => 3,
                        ]
                    ],
                ],
                function ($product) {
                    $this->assertInstanceOf('Oro\Bundle\ProductBundle\Entity\Product', $product);

                    /** @var \Oro\Bundle\ProductBundle\Entity\Product $product */
                    $this->assertNull($product->getId());
                    $this->assertEmpty($product->getNames()->toArray());
                },
            ],
            'existing product with, id not mapped for new fallback' => [
                [
                    'sku' => 'product-4',
                    'attributeFamily' => 'default_family',
                    'primaryUnitPrecision' => [
                        'testEntity' => 'Oro\Bundle\ProductBundle\Entity\ProductUnitPrecision',
                        'testProperties' => [
                            'unit' => $this->getEntity(
                                'Oro\Bundle\ProductBundle\Entity\ProductUnit',
                                ['code' => 'each']
                            ),
                            'precision' => 0,
                        ]
                    ],
                    'names' => [
                        [
                            'testEntity' => 'Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue',
                            'testProperties' => ['string' => 'product-4 Default Name']
                        ],
                        [
                            'testEntity' => 'Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue',
                            'testProperties' => [
                                'string' => 'product-4 en_US Name',
                                'localization' => [
                                    'testEntity' => Localization::class,
                                    'testProperties' => [
                                        'name' => 'English (United States)',
                                    ],
                                ],
                            ],
                        ],
                    ]
                ],
                function ($product) {
                    $this->assertInstanceOf('Oro\Bundle\ProductBundle\Entity\Product', $product);

                    /** @var \Oro\Bundle\ProductBundle\Entity\Product $product */
                    $this->assertNotNull($product->getId());
                    $this->assertNotEmpty($product->getNames()->toArray());
                    $this->assertNull($product->getNames()->last()->getId());
                },
            ],
        ];
    }
}
