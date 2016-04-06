<?php

namespace Oro\Bundle\ActionBundle\Tests\Unit\Model;

use Doctrine\Common\Collections\ArrayCollection;

use Oro\Bundle\ActionBundle\Model\Action;
use Oro\Bundle\ActionBundle\Model\ActionData;
use Oro\Bundle\ActionBundle\Model\ActionDefinition;
use Oro\Bundle\ActionBundle\Model\AttributeAssembler;
use Oro\Bundle\ActionBundle\Model\FormOptionsAssembler;

use Oro\Bundle\WorkflowBundle\Model\Action\ActionFactory as FunctionFactory;
use Oro\Bundle\WorkflowBundle\Model\Action\ActionInterface as FunctionInterface;
use Oro\Bundle\WorkflowBundle\Model\Attribute;
use Oro\Bundle\WorkflowBundle\Model\Condition\Configurable as ConfigurableCondition;

use Oro\Component\ConfigExpression\ExpressionFactory;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 */
class ActionTest extends \PHPUnit_Framework_TestCase
{
    /** @var \PHPUnit_Framework_MockObject_MockObject|ActionDefinition */
    protected $definition;

    /** @var \PHPUnit_Framework_MockObject_MockObject|FunctionFactory */
    protected $functionFactory;

    /** @var \PHPUnit_Framework_MockObject_MockObject|ExpressionFactory */
    protected $conditionFactory;

    /** @var \PHPUnit_Framework_MockObject_MockObject|AttributeAssembler */
    protected $attributeAssembler;

    /** @var \PHPUnit_Framework_MockObject_MockObject|FormOptionsAssembler */
    protected $formOptionsAssembler;

    /** @var Action */
    protected $action;

    /** @var ActionData */
    protected $data;

    protected function setUp()
    {
        $this->definition = $this->getMockBuilder('Oro\Bundle\ActionBundle\Model\ActionDefinition')
            ->disableOriginalConstructor()
            ->getMock();

        $this->functionFactory = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Model\Action\ActionFactory')
            ->disableOriginalConstructor()
            ->getMock();

        $this->conditionFactory = $this->getMockBuilder('Oro\Component\ConfigExpression\ExpressionFactory')
            ->disableOriginalConstructor()
            ->getMock();

        $this->attributeAssembler = $this->getMockBuilder('Oro\Bundle\ActionBundle\Model\AttributeAssembler')
            ->disableOriginalConstructor()
            ->getMock();

        $this->formOptionsAssembler = $this->getMockBuilder('Oro\Bundle\ActionBundle\Model\FormOptionsAssembler')
            ->disableOriginalConstructor()
            ->getMock();

        $this->action = new Action(
            $this->functionFactory,
            $this->conditionFactory,
            $this->attributeAssembler,
            $this->formOptionsAssembler,
            $this->definition
        );

        $this->data = new ActionData();
    }

    public function testGetName()
    {
        $this->definition->expects($this->once())
            ->method('getName')
            ->willReturn('test name');

        $this->assertEquals('test name', $this->action->getName());
    }

    public function testIsEnabled()
    {
        $this->definition->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->assertEquals(true, $this->action->isEnabled());
    }

    public function testGetDefinition()
    {
        $this->assertInstanceOf('Oro\Bundle\ActionBundle\Model\ActionDefinition', $this->action->getDefinition());
    }

    public function testInit()
    {
        $config = [
            ['form_init', ['form_init']],
        ];

        $functions = [
            'form_init' => $this->createFunction($this->once(), $this->data),
        ];

        $this->definition->expects($this->any())
            ->method('getFunctions')
            ->willReturnMap($config);

        $this->functionFactory->expects($this->any())
            ->method('create')
            ->willReturnCallback(function ($type, $config) use ($functions) {
                return $functions[$config[0]];
            });

        $this->action->init($this->data);
    }

    /**
     * @param ActionData $data
     * @param array $config
     * @param array $functions
     * @param array $conditions
     * @param string $actionName
     * @param string $exceptionMessage
     *
     * @dataProvider executeProvider
     */
    public function testExecute(
        ActionData $data,
        array $config,
        array $functions,
        array $conditions,
        $actionName,
        $exceptionMessage = ''
    ) {
        $this->definition->expects($this->any())
            ->method('getName')
            ->willReturn($actionName);

        $this->definition->expects($this->any())
            ->method('getFunctions')
            ->will($this->returnValueMap($config));

        $this->definition->expects($this->any())
            ->method('getConditions')
            ->will($this->returnValueMap($config));

        $this->functionFactory->expects($this->any())
            ->method('create')
            ->willReturnCallback(function ($type, $config) use ($functions) {
                return $functions[$config[0]];
            });

        $this->conditionFactory->expects($this->any())
            ->method('create')
            ->willReturnCallback(function ($type, $config) use ($conditions) {
                return $conditions[$config[0]];
            });

        $errors = new ArrayCollection();

        if ($exceptionMessage) {
            $this->setExpectedException(
                'Oro\Bundle\ActionBundle\Exception\ForbiddenActionException',
                $exceptionMessage
            );
        }

        $this->action->execute($data, $errors);

        $this->assertEmpty($errors->toArray());
    }

    /**
     * @param array $inputData
     * @param array $expectedData
     *
     * @dataProvider isAvailableProvider
     */
    public function testIsAvailable(array $inputData, array $expectedData)
    {
        $this->definition->expects($this->any())
            ->method('getConditions')
            ->will($this->returnValueMap($inputData['config']['conditions']));

        $this->definition->expects($this->any())
            ->method('getFormOptions')
            ->willReturn($inputData['config']['form_options']);

        $this->conditionFactory->expects($expectedData['conditionFactory'])
            ->method('create')
            ->willReturnCallback(function ($type, $config) use ($inputData) {
                return $inputData['conditions'][$config[0]];
            });

        $this->assertEquals($expectedData['available'], $this->action->isAvailable($inputData['data']));
    }

    /**
     * @return array
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function isAvailableProvider()
    {
        $data = new ActionData();

        return [
            'no conditions' => [
                'input' => [
                    'data' => $data,
                    'config' => [
                        'conditions' => [],
                        'form_options' => [],
                    ],
                ],
                'expected' => [
                    'conditionFactory' => $this->never(),
                    'available' => true,
                    'errors' => [],
                ],
            ],
            '!isPreConditionAllowed' => [
                'input' => [
                    'data' => $data,
                    'config' => [
                        'conditions' => [
                            ['preconditions', ['preconditions']],
                            ['conditions', ['conditions']],
                        ],
                        'form_options' => [],
                    ],
                    'conditions' => [
                        'preconditions' => $this->createCondition($this->once(), $data, false),
                        'conditions' => $this->createCondition($this->never(), $data, true),
                    ],
                ],
                'expected' => [
                    'conditionFactory' => $this->exactly(1),
                    'available' => false,
                ],
            ],
            '!isConditionAllowed' => [
                'input' => [
                    'data' => $data,
                    'config' => [
                        'conditions' => [
                            ['preconditions', ['preconditions']],
                            ['conditions', ['conditions']],
                        ],
                        'form_options' => [],
                    ],
                    'conditions' => [
                        'preconditions' => $this->createCondition($this->once(), $data, true),
                        'conditions' => $this->createCondition($this->once(), $data, false),
                    ],
                ],
                'expected' => [
                    'conditionFactory' => $this->exactly(2),
                    'available' => false,
                    'errors' => ['error3', 'error4'],
                ],
            ],
            'allowed' => [
                'input' => [
                    'data' => $data,
                    'config' => [
                        'conditions' => [
                            ['preconditions', ['preconditions']],
                            ['conditions', ['conditions']],
                        ],
                        'form_options' => [],
                    ],
                    'conditions' => [
                        'preconditions' => $this->createCondition($this->once(), $data, true),
                        'conditions' => $this->createCondition($this->once(), $data, true),
                    ],
                ],
                'expected' => [
                    'conditionFactory' => $this->exactly(2),
                    'available' => true,
                    'errors' => [],
                ],
            ],
            'hasForm and no conditions' => [
                'input' => [
                    'data' => $data,
                    'config' => [
                        'conditions' => [],
                        'form_options' => [
                            'attribute_fields' => [
                                'attribute1' => [],
                            ],
                        ],
                    ],
                ],
                'expected' => [
                    'conditionFactory' => $this->never(),
                    'available' => true,
                    'errors' => [],
                ],
            ],
            'hasForm and !isPreConditionAllowed' => [
                'input' => [
                    'data' => $data,
                    'config' => [
                        'conditions' => [
                            ['preconditions', ['preconditions']],
                            ['conditions', ['conditions']],
                        ],
                        'form_options' => [
                            'attribute_fields' => [
                                'attribute2' => [],
                            ],
                        ],
                    ],
                    'conditions' => [
                        'preconditions' => $this->createCondition($this->once(), $data, false),
                        'conditions' => $this->createCondition($this->never(), $data, true),
                    ],
                ],
                'expected' => [
                    'conditionFactory' => $this->exactly(1),
                    'available' => false,
                ],
            ],
            'hasForm and allowed' => [
                'input' => [
                    'data' => $data,
                    'config' => [
                        'conditions' => [
                            ['preconditions', ['preconditions']],
                            ['conditions', ['conditions']],
                        ],
                        'form_options' => [
                            'attribute_fields' => [
                                'attribute3' => [],
                            ],
                        ],
                    ],
                    'conditions' => [
                        'preconditions' => $this->createCondition($this->once(), $data, true),
                        'conditions' => $this->createCondition($this->never(), $data, true),
                    ],
                ],
                'expected' => [
                    'conditionFactory' => $this->exactly(1),
                    'available' => true,
                    'errors' => [],
                ],
            ],
        ];
    }

    /**
     * @param array $inputData
     * @param array $expectedData
     *
     * @dataProvider isAllowedProvider
     */
    public function testIsAllowed(array $inputData, array $expectedData)
    {
        $this->definition->expects($this->any())
            ->method('getConditions')
            ->will($this->returnValueMap($inputData['config']['conditions']));

        $this->conditionFactory->expects($expectedData['conditionFactory'])
            ->method('create')
            ->willReturnCallback(function ($type, $config) use ($inputData) {
                return $inputData['conditions'][$config[0]];
            });

        $this->assertEquals($expectedData['allowed'], $this->action->isAllowed($inputData['data']));
    }

    /**
     * @param array $input
     * @param array $expected
     *
     * @dataProvider getFormOptionsDataProvider
     */
    public function testGetFormOptions(array $input, array $expected)
    {
        $this->definition->expects($this->once())
            ->method('getFormOptions')
            ->willReturn($input);

        if ($input) {
            $attributes = ['attribute' => ['label' => 'attr_label']];

            $this->definition->expects($this->once())
                ->method('getAttributes')
                ->willReturn($attributes);

            $attribute = new Attribute();
            $attribute->setName('test_attr');

            $this->attributeAssembler->expects($this->once())
                ->method('assemble')
                ->with($this->data, $attributes)
                ->willReturn(new ArrayCollection(['test_attr' => $attribute]));

            $this->formOptionsAssembler->expects($this->once())
                ->method('assemble')
                ->with($input, new ArrayCollection(['test_attr' => $attribute]))
                ->willReturn($expected);
        }

        $this->assertEquals($expected, $this->action->getFormOptions($this->data));
    }

    /**
     * @param array $input
     * @param bool $expected
     *
     * @dataProvider hasFormProvider
     */
    public function testHasForm(array $input, $expected)
    {
        $this->definition->expects($this->once())
            ->method('getFormOptions')
            ->willReturn($input);
        $this->assertEquals($expected, $this->action->hasForm());
    }

    /**
     * @return array
     */
    public function executeProvider()
    {
        $data = new ActionData();

        $config = [
            ['prefunctions', ['prefunctions']],
            ['functions', ['functions']],
            ['preconditions', ['preconditions']],
            ['conditions', ['conditions']],
        ];

        return [
            '!isPreConditionAllowed' => [
                'data' => $data,
                'config' => $config,
                'functions' => [
                    'prefunctions' => $this->createFunction($this->once(), $data),
                    'functions' => $this->createFunction($this->never(), $data),
                ],
                'conditions' => [
                    'preconditions' => $this->createCondition($this->once(), $data, false),
                    'conditions' => $this->createCondition($this->never(), $data, true),
                ],
                'actionName' => 'TestName1',
                'exception' => 'Action "TestName1" is not allowed.'
            ],
            '!isConditionAllowed' => [
                'data' => $data,
                'config' => $config,
                'functions' => [
                    'prefunctions' => $this->createFunction($this->once(), $data),
                    'functions' => $this->createFunction($this->never(), $data),
                ],
                'conditions' => [
                    'preconditions' => $this->createCondition($this->once(), $data, true),
                    'conditions' => $this->createCondition($this->once(), $data, false),
                ],
                'actionName' => 'TestName2',
                'exception' => 'Action "TestName2" is not allowed.'
            ],
            'isAllowed' => [
                'data' => $data,
                'config' => $config,
                'functions' => [
                    'prefunctions' => $this->createFunction($this->once(), $data),
                    'functions' => $this->createFunction($this->once(), $data),
                ],
                'conditions' => [
                    'preconditions' => $this->createCondition($this->once(), $data, true),
                    'conditions' => $this->createCondition($this->once(), $data, true),
                ],
                'actionName' => 'TestName3',
            ],
        ];
    }

    /**
     * @return array
     */
    public function isAllowedProvider()
    {
        $data = new ActionData();

        return [
            'no conditions' => [
                'input' => [
                    'data' => $data,
                    'config' => [
                        'conditions' => [],
                    ],
                ],
                'expected' => [
                    'conditionFactory' => $this->never(),
                    'allowed' => true,
                    'errors' => [],
                ],
            ],
            '!isPreConditionAllowed' => [
                'input' => [
                    'data' => $data,
                    'config' => [
                        'conditions' => [
                            ['preconditions', ['preconditions']],
                            ['conditions', ['conditions']],
                        ],
                    ],
                    'conditions' => [
                        'preconditions' => $this->createCondition($this->once(), $data, false),
                        'conditions' => $this->createCondition($this->never(), $data, true),
                    ],
                ],
                'expected' => [
                    'conditionFactory' => $this->exactly(1),
                    'allowed' => false,
                ],
            ],
            '!isConditionAllowed' => [
                'input' => [
                    'data' => $data,
                    'config' => [
                        'conditions' => [
                            ['preconditions', ['preconditions']],
                            ['conditions', ['conditions']],
                        ],
                    ],
                    'conditions' => [
                        'preconditions' => $this->createCondition($this->once(), $data, true),
                        'conditions' => $this->createCondition($this->once(), $data, false),
                    ],
                ],
                'expected' => [
                    'conditionFactory' => $this->exactly(2),
                    'allowed' => false,
                    'errors' => ['error3', 'error4'],
                ],
            ],
            'allowed' => [
                'input' => [
                    'data' => $data,
                    'config' => [
                        'conditions' => [
                            ['preconditions', ['preconditions']],
                            ['conditions', ['conditions']],
                        ],
                    ],
                    'conditions' => [
                        'preconditions' => $this->createCondition($this->once(), $data, true),
                        'conditions' => $this->createCondition($this->once(), $data, true),
                    ],
                ],
                'expected' => [
                    'conditionFactory' => $this->exactly(2),
                    'allowed' => true,
                    'errors' => [],
                ],
            ],
        ];
    }

    /**
     * @return array
     */
    public function hasFormProvider()
    {
        return [
            'empty' => [
                'input' => [],
                'expected' => false,
            ],
            'empty attribute_fields' => [
                'input' => ['attribute_fields' => []],
                'expected' => false,
            ],
            'filled' => [
                'input' => ['attribute_fields' => ['attribute' => []]],
                'expected' => true,
            ],
        ];
    }

    /**
     * @return array
     */
    public function getFormOptionsDataProvider()
    {
        return [
            'empty' => [
                'input' => [],
                'expected' => [],
            ],
            'filled' => [
                'input' => ['attribute_fields' => ['attribute' => []]],
                'expected' => ['attribute_fields' => ['attribute' => []]],
            ],
        ];
    }

    /**
     * @param \PHPUnit_Framework_MockObject_Matcher_InvokedCount $expects
     * @param ActionData $data
     * @return FunctionInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createFunction(
        \PHPUnit_Framework_MockObject_Matcher_InvokedCount $expects,
        ActionData $data
    ) {
        /* @var $function FunctionInterface|\PHPUnit_Framework_MockObject_MockObject */
        $function = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Model\Action\ActionInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $function->expects($expects)
            ->method('execute')
            ->with($data);

        return $function;
    }

    /**
     * @param \PHPUnit_Framework_MockObject_Matcher_InvokedCount $expects
     * @param ActionData $data
     * @param bool $returnValue
     * @return ConfigurableCondition|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createCondition(
        \PHPUnit_Framework_MockObject_Matcher_InvokedCount $expects,
        ActionData $data,
        $returnValue
    ) {
        /* @var $condition ConfigurableCondition|\PHPUnit_Framework_MockObject_MockObject */
        $condition = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Model\Condition\Configurable')
            ->disableOriginalConstructor()
            ->getMock();

        $condition->expects($expects)
            ->method('evaluate')
            ->with($data)
            ->willReturn($returnValue);

        return $condition;
    }

    public function testGetAttributeManager()
    {
        $attributes = ['attribute' => ['label' => 'attr_label']];

        $this->definition->expects($this->once())
            ->method('getAttributes')
            ->willReturn($attributes);

        $this->data['data'] = new \stdClass();

        $attribute = new Attribute();
        $attribute->setName('test_attr');

        $this->attributeAssembler->expects($this->once())
            ->method('assemble')
            ->with($this->data, $attributes)
            ->willReturn(new ArrayCollection([$attribute]));

        $attributeManager = $this->action->getAttributeManager($this->data);

        $this->assertInstanceOf('Oro\Bundle\WorkflowBundle\Model\AttributeManager', $attributeManager);
        $this->assertEquals(new ArrayCollection(['test_attr' => $attribute]), $attributeManager->getAttributes());
    }
}