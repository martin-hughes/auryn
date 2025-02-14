<?php

namespace Auryn\Test;

use Auryn\Injector;
use PHPUnit\Framework\TestCase;

require_once __DIR__."/fixtures.php";
require_once __DIR__."/fixtures_5_6.php";
require_once __DIR__."/fixtures_8_0.php";

class InjectorTest extends TestCase
{
    public function testArrayTypehintDoesNotEvaluatesAsClass()
    {
        $injector = new Injector;
        $injector->defineParam('parameter', []);
        $injector->execute('Auryn\Test\hasArrayDependency');
        $this->expectNotToPerformAssertions();
    }

    public function testMakeInstanceInjectsSimpleConcreteDependency()
    {
        $injector = new Injector;
        $this->assertEquals(new TestNeedsDep(new TestDependency),
            $injector->make('Auryn\Test\TestNeedsDep')
        );
    }

    public function testMakeInstanceWithParamDefaultBeingExplicitlySetToNullWithAutoloading()
    {
      $injector = new Injector();
      /* @var \InjectorTestNullableParams $obj */
      $obj = $injector->make(\InjectorTestNullableParams::class);

      $this->assertInstanceOf(\TestInstance::class, $obj->instance);
    }

    public function testMakeInstanceWithParamDefaultBeingExplicitlySetToNullWithShareAvailable()
    {
      $injector = new Injector();
      $injector->share(\TestInstance::class);
      /* @var \InjectorTestNullableParams $obj */
      $obj = $injector->make(\InjectorTestNullableParams::class);

      $this->assertInstanceOf(\TestInstance::class, $obj->instance);
    }

    public function testMakeInstanceReturnsNewInstanceIfClassHasNoConstructor()
    {
        $injector = new Injector;
        $this->assertEquals(new TestNoConstructor, $injector->make('Auryn\Test\TestNoConstructor'));
    }

    public function testMakeInstanceReturnsAliasInstanceOnNonConcreteTypehint()
    {
        $injector = new Injector;
        $injector->alias('Auryn\Test\DepInterface', 'Auryn\Test\DepImplementation');
        $this->assertEquals(new DepImplementation, $injector->make('Auryn\Test\DepInterface'));
    }

    public function testMakeInstanceThrowsExceptionOnInterfaceWithoutAlias()
    {
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionCode(\Auryn\Injector::E_NEEDS_DEFINITION);
        $this->expectExceptionMessage("Injection definition required for interface Auryn\Test\DepInterface");
        $injector = new Injector;
        $injector->make('Auryn\Test\DepInterface');
    }

    public function testMakeInstanceThrowsExceptionOnNonConcreteCtorParamWithoutImplementation()
    {
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionCode(\Auryn\Injector::E_NEEDS_DEFINITION);
        $this->expectExceptionMessage("Injection definition required for interface Auryn\Test\DepInterface");
        $injector = new Injector;
        $injector->make('Auryn\Test\RequiresInterface');
    }

    public function testMakeInstanceBuildsNonConcreteCtorParamWithAlias()
    {
        $injector = new Injector;
        $injector->alias('Auryn\Test\DepInterface', 'Auryn\Test\DepImplementation');
        $obj = $injector->make('Auryn\Test\RequiresInterface');
        $this->assertInstanceOf('Auryn\Test\RequiresInterface', $obj);
    }

    public function testMakeInstancePassesNullCtorParameterIfNoTypehintOrDefaultCanBeDetermined()
    {
        $injector = new Injector;
        $nullCtorParamObj = $injector->make('Auryn\Test\ProvTestNoDefinitionNullDefaultClass');
        $this->assertEquals(new ProvTestNoDefinitionNullDefaultClass, $nullCtorParamObj);
        $this->assertNull($nullCtorParamObj->arg);
    }

    public function testMakeInstanceReturnsSharedInstanceIfAvailable()
    {
        $injector = new Injector;
        $injector->define('Auryn\Test\RequiresInterface', array('dep' => 'Auryn\Test\DepImplementation'));
        $injector->share('Auryn\Test\RequiresInterface');
        $injected = $injector->make('Auryn\Test\RequiresInterface');

        $this->assertEquals('something', $injected->testDep->testProp);
        $injected->testDep->testProp = 'something else';

        $injected2 = $injector->make('Auryn\Test\RequiresInterface');
        $this->assertEquals('something else', $injected2->testDep->testProp);
    }

    public function testMakeInstanceThrowsExceptionOnClassLoadFailure()
    {
        $this->expectExceptionMessageMatches('/^Could not make ClassThatDoesntExist: Class "?ClassThatDoesntExist"? does not exist$/');
        $this->expectException(\Auryn\InjectorException::class);
        $injector = new Injector;
        $injector->make('ClassThatDoesntExist');
    }

    public function testMakeInstanceUsesCustomDefinitionIfSpecified()
    {
        $injector = new Injector;
        $injector->define('Auryn\Test\TestNeedsDep', array('testDep'=>'Auryn\Test\TestDependency'));
        $injected = $injector->make('Auryn\Test\TestNeedsDep', array('testDep'=>'Auryn\Test\TestDependency2'));
        $this->assertEquals('testVal2', $injected->testDep->testProp);
    }

    public function testMakeInstanceCustomDefinitionOverridesExistingDefinitions()
    {
        $injector = new Injector;
        $injector->define('Auryn\Test\InjectorTestChildClass', array(':arg1'=>'First argument', ':arg2'=>'Second argument'));
        $injected = $injector->make('Auryn\Test\InjectorTestChildClass', array(':arg1'=>'Override'));
        $this->assertEquals('Override', $injected->arg1);
        $this->assertEquals('Second argument', $injected->arg2);
    }

    public function testMakeInstanceStoresShareIfMarkedWithNullInstance()
    {
        $injector = new Injector;
        $injector->share('Auryn\Test\TestDependency');
        $injector->make('Auryn\Test\TestDependency');
        $this->expectNotToPerformAssertions();
    }

    public function testMakeInstanceUsesReflectionForUnknownParamsInMultiBuildWithDeps()
    {
        $injector = new Injector;
        $obj = $injector->make('Auryn\Test\TestMultiDepsWithCtor', array('val1'=>'Auryn\Test\TestDependency'));
        $this->assertInstanceOf('Auryn\Test\TestMultiDepsWithCtor', $obj);

        $obj = $injector->make('Auryn\Test\NoTypehintNoDefaultConstructorClass',
            array('val1'=>'Auryn\Test\TestDependency')
        );
        $this->assertInstanceOf('Auryn\Test\NoTypehintNoDefaultConstructorClass', $obj);
        $this->assertNull($obj->testParam);
    }

    /**
     * @requires PHP 5.6
     */
    public function testMakeInstanceUsesReflectionForUnknownParamsInMultiBuildWithDepsAndVariadics()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped("HHVM doesn't support variadics with type declarations.");
        }

        $injector = new Injector;
        $obj = $injector->make('Auryn\Test\NoTypehintNoDefaultConstructorVariadicClass',
            array('val1'=>'Auryn\Test\TestDependency')
        );
        $this->assertInstanceOf('Auryn\Test\NoTypehintNoDefaultConstructorVariadicClass', $obj);
        $this->assertEquals(array(), $obj->testParam);
    }

    /**
     * @requires PHP 5.6
     */
    public function testMakeInstanceUsesReflectionForUnknownParamsWithDepsAndVariadicsWithTypeHint()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped("HHVM doesn't support variadics with type declarations.");
        }

        $injector = new Injector;
        $obj = $injector->make('Auryn\Test\TypehintNoDefaultConstructorVariadicClass',
            array('arg'=>'Auryn\Test\TestDependency')
        );
        $this->assertInstanceOf('Auryn\Test\TypehintNoDefaultConstructorVariadicClass', $obj);
        $this->assertIsArray($obj->testParam);
        $this->assertInstanceOf('Auryn\Test\TestDependency', $obj->testParam[0]);
    }

    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithoutDefinitionOrDefault()
    {
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionCode(\Auryn\Injector::E_UNDEFINED_PARAM);
        $this->expectExceptionMessage('No definition available to provision typeless parameter $val at position 0 in Auryn\Test\InjectorTestCtorParamWithNoTypehintOrDefault::__construct() declared in Auryn\Test\InjectorTestCtorParamWithNoTypehintOrDefault::');
        $injector = new Injector;
        $injector->make('Auryn\Test\InjectorTestCtorParamWithNoTypehintOrDefault');
    }

    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithoutDefinitionOrDefaultThroughAliasedTypehint()
    {
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionCode(\Auryn\Injector::E_UNDEFINED_PARAM);
        $this->expectExceptionMessage('No definition available to provision typeless parameter $val at position 0 in Auryn\Test\InjectorTestCtorParamWithNoTypehintOrDefault::__construct() declared in Auryn\Test\InjectorTestCtorParamWithNoTypehintOrDefault::');
        $injector = new Injector;
        $injector->alias('Auryn\Test\TestNoExplicitDefine', 'Auryn\Test\InjectorTestCtorParamWithNoTypehintOrDefault');
        $injector->make('Auryn\Test\InjectorTestCtorParamWithNoTypehintOrDefaultDependent');
    }

    /**
     * @TODO
     */
    public function testMakeInstanceThrowsExceptionOnUninstantiableTypehintWithoutDefinition()
    {
        $this->expectException(\Auryn\InjectorException::class);
        $this->expectExceptionMessage("Injection definition required for interface Auryn\Test\DepInterface");
        $injector = new Injector;
        $injector->make('Auryn\Test\RequiresInterface');
    }

    public function testTypelessDefineForDependency()
    {
        $thumbnailSize = 128;
        $injector = new Injector;
        $injector->defineParam('thumbnailSize', $thumbnailSize);
        $testClass = $injector->make('Auryn\Test\RequiresDependencyWithTypelessParameters');
        $this->assertEquals($thumbnailSize, $testClass->getThumbnailSize(), 'Typeless define was not injected correctly.');
    }

    public function testTypelessDefineForAliasedDependency()
    {
        $injector = new Injector;
        $injector->defineParam('val', 42);

        $injector->alias('Auryn\Test\TestNoExplicitDefine', 'Auryn\Test\ProviderTestCtorParamWithNoTypehintOrDefault');
        $obj = $injector->make('Auryn\Test\ProviderTestCtorParamWithNoTypehintOrDefaultDependent');
        $this->expectNotToPerformAssertions();
    }

    public function testMakeInstanceInjectsRawParametersDirectly()
    {
        $injector = new Injector;
        $injector->define('Auryn\Test\InjectorTestRawCtorParams', array(
            ':string' => 'string',
            ':obj' => new \StdClass,
            ':int' => 42,
            ':array' => array(),
            ':float' => 9.3,
            ':bool' => true,
            ':null' => null,
        ));

        $obj = $injector->make('Auryn\Test\InjectorTestRawCtorParams');
        $this->assertIsString($obj->string);
        $this->assertInstanceOf(\StdClass::class, $obj->obj);
        $this->assertIsInt($obj->int);
        $this->assertIsArray($obj->array);
        $this->assertIsFloat($obj->float);
        $this->assertIsBool($obj->bool);
        $this->assertNull($obj->null);
    }

    /**
     * @TODO
     */
    public function testMakeInstanceThrowsExceptionWhenDelegateDoes()
    {
        $this->expectExceptionMessage("");
        $this->expectException(\Exception::class);

        $injector= new Injector;

        $callable = $this->createMock(
          \Auryn\Test\CallableMock::class,
            array('__invoke')
        );

        $injector->delegate('TestDependency', $callable);

        $callable->expects($this->once())
            ->method('__invoke')
            ->will($this->throwException(new \Exception()));

        $injector->make('TestDependency');
    }

    public function testMakeInstanceHandlesNamespacedClasses()
    {
        $injector = new Injector;
        $injector->make('Auryn\Test\SomeClassName');
        $this->expectNotToPerformAssertions();
    }

    public function testMakeInstanceDelegate()
    {
        $injector= new Injector;

        $callable = $this->createMock(
          \Auryn\Test\CallableMock::class,
            array('__invoke')
        );
        $callable->expects($this->once())
            ->method('__invoke')
            ->will($this->returnValue(new TestDependency()));

        $injector->delegate('Auryn\Test\TestDependency', $callable);

        $obj = $injector->make('Auryn\Test\TestDependency');

        $this->assertInstanceOf('Auryn\Test\TestDependency', $obj);
    }

    public function testMakeInstanceWithStringDelegate()
    {
        $injector= new Injector;
        $injector->delegate('StdClass', 'Auryn\Test\StringStdClassDelegateMock');
        $obj = $injector->make('StdClass');
        $this->assertEquals(42, $obj->test);
    }

    public function testMakeInstanceThrowsExceptionIfStringDelegateClassHasNoInvokeMethod()
    {
        $this->expectException(\Auryn\ConfigException::class);
        $this->expectExceptionMessage("Auryn\Injector::delegate expects a valid callable or executable class::method string at Argument 2 but received 'StringDelegateWithNoInvokeMethod'");
        $injector= new Injector;
        $injector->delegate('StdClass', 'StringDelegateWithNoInvokeMethod');
    }

    public function testMakeInstanceThrowsExceptionIfStringDelegateClassInstantiationFails()
    {
        $this->expectException(\Auryn\ConfigException::class);
        $this->expectExceptionMessage("Auryn\Injector::delegate expects a valid callable or executable class::method string at Argument 2 but received 'SomeClassThatDefinitelyDoesNotExistForReal'");
        $injector= new Injector;
        $injector->delegate('StdClass', 'SomeClassThatDefinitelyDoesNotExistForReal');
    }

    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithNoDefinition()
    {
        $this->expectExceptionMessage("Injection definition required for interface Auryn\Test\DepInterface");
        $this->expectException(\Auryn\InjectionException::class);
        $injector = new Injector;
        $injector->make('Auryn\Test\RequiresInterface');
    }

    public function testDefineAssignsPassedDefinition()
    {
        $injector = new Injector;
        $definition = array('dep' => 'Auryn\Test\DepImplementation');
        $injector->define('Auryn\Test\RequiresInterface', $definition);
        $this->assertInstanceOf('Auryn\Test\RequiresInterface', $injector->make('Auryn\Test\RequiresInterface'));
    }

    public function testShareStoresSharedInstanceAndReturnsCurrentInstance()
    {
        $injector = new Injector;
        $testShare = new \StdClass;
        $testShare->test = 42;

        $this->assertInstanceOf('Auryn\Injector', $injector->share($testShare));
        $testShare->test = 'test';
        $this->assertEquals('test', $injector->make('stdclass')->test);
    }

    public function testShareMarksClassSharedOnNullObjectParameter()
    {
        $injector = new Injector;
        $this->assertInstanceOf('Auryn\Injector', $injector->share('SomeClass'));
    }

    public function testShareThrowsExceptionOnInvalidArgument()
    {
        $this->expectException(\Auryn\ConfigException::class);
        $this->expectExceptionMessage("Auryn\Injector::share() requires a string class name or object instance at Argument 1; integer specified");
        $injector = new Injector;
        $injector->share(42);
    }

    public function testAliasAssignsValueAndReturnsCurrentInstance()
    {
        $injector = new Injector;
        $this->assertInstanceOf('Auryn\Injector', $injector->alias('DepInterface', 'Auryn\Test\DepImplementation'));
    }

    public function provideInvalidDelegates()
    {
        return array(
            array(new \StdClass),
            array(42),
            array(true)
        );
    }

    /**
     * @dataProvider provideInvalidDelegates
     */
    public function testDelegateThrowsExceptionIfDelegateIsNotCallableOrString($badDelegate)
    {
        $this->expectException(\Auryn\ConfigException::class);
        $this->expectExceptionMessage("Auryn\Injector::delegate expects a valid callable or executable class::method string at Argument 2");
        $injector = new Injector;
        $injector->delegate('Auryn\Test\TestDependency', $badDelegate);
    }

    public function testDelegateInstantiatesCallableClassString()
    {
        $injector = new Injector;
        $injector->delegate('Auryn\Test\MadeByDelegate', 'Auryn\Test\CallableDelegateClassTest');
        $this->assertInstanceof('Auryn\Test\MadeByDelegate', $injector->make('Auryn\Test\MadeByDelegate'));
    }

    public function testDelegateInstantiatesCallableClassArray()
    {
        $injector = new Injector;
        $injector->delegate('Auryn\Test\MadeByDelegate', array('Auryn\Test\CallableDelegateClassTest', '__invoke'));
        $this->assertInstanceof('Auryn\Test\MadeByDelegate', $injector->make('Auryn\Test\MadeByDelegate'));
    }

    public function testUnknownDelegationFunction()
    {
        $injector = new Injector;
        try {
            $injector->delegate('Auryn\Test\DelegatableInterface', 'FunctionWhichDoesNotExist');
            $this->fail("Delegation was supposed to fail.");
        } catch (\Auryn\InjectorException $ie) {
            $this->assertStringContainsString('FunctionWhichDoesNotExist', $ie->getMessage());
            $this->assertEquals(\Auryn\Injector::E_DELEGATE_ARGUMENT, $ie->getCode());
        }
    }

    public function testUnknownDelegationMethod()
    {
        $injector = new Injector;
        try {
            $injector->delegate('Auryn\Test\DelegatableInterface', array('stdClass', 'methodWhichDoesNotExist'));
            $this->fail("Delegation was supposed to fail.");
        } catch (\Auryn\InjectorException $ie) {
            $this->assertStringContainsString('stdClass', $ie->getMessage());
            $this->assertStringContainsString('methodWhichDoesNotExist', $ie->getMessage());
            $this->assertEquals(\Auryn\Injector::E_DELEGATE_ARGUMENT, $ie->getCode());
        }
    }

    /**
     * @dataProvider provideExecutionExpectations
     */
    public function testProvisionedInvokables($toInvoke, $definition, $expectedResult)
    {
        $injector = new Injector;
        $this->assertEquals($expectedResult, $injector->execute($toInvoke, $definition));
    }

    public function provideExecutionExpectations()
    {
        $return = array();

        // 0 -------------------------------------------------------------------------------------->

        $toInvoke = array('Auryn\Test\ExecuteClassNoDeps', 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 1 -------------------------------------------------------------------------------------->

        $toInvoke = array(new ExecuteClassNoDeps, 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 2 -------------------------------------------------------------------------------------->

        $toInvoke = array('Auryn\Test\ExecuteClassDeps', 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 3 -------------------------------------------------------------------------------------->

        $toInvoke = array(new ExecuteClassDeps(new TestDependency), 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 4 -------------------------------------------------------------------------------------->

        $toInvoke = array('Auryn\Test\ExecuteClassDepsWithMethodDeps', 'execute');
        $args = array(':arg' => 9382);
        $expectedResult = 9382;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 5 -------------------------------------------------------------------------------------->

        $toInvoke = array('Auryn\Test\ExecuteClassStaticMethod', 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 6 -------------------------------------------------------------------------------------->

        $toInvoke = array(new ExecuteClassStaticMethod, 'execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 7 -------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\Test\ExecuteClassStaticMethod::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 8 -------------------------------------------------------------------------------------->

        $toInvoke = array('Auryn\Test\ExecuteClassRelativeStaticMethod', 'parent::execute');
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 9 -------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\Test\testExecuteFunction';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 10 ------------------------------------------------------------------------------------->

        $toInvoke = function () { return 42; };
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 11 ------------------------------------------------------------------------------------->

        $toInvoke = new ExecuteClassInvokable;
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 12 ------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\Test\ExecuteClassInvokable';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 13 ------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\Test\ExecuteClassNoDeps::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 14 ------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\Test\ExecuteClassDeps::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 15 ------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\Test\ExecuteClassStaticMethod::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 16 ------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\Test\ExecuteClassRelativeStaticMethod::parent::execute';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 17 ------------------------------------------------------------------------------------->

        $toInvoke = 'Auryn\Test\testExecuteFunctionWithArg';
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);

        // 18 ------------------------------------------------------------------------------------->

        $toInvoke = function () {
            return 42;
        };
        $args = array();
        $expectedResult = 42;
        $return[] = array($toInvoke, $args, $expectedResult);


        if (PHP_VERSION_ID > 50400) {
            // 19 ------------------------------------------------------------------------------------->

            $object = new \Auryn\Test\ReturnsCallable('new value');
            $args = array();
            $toInvoke = $object->getCallable();
            $expectedResult = 'new value';
            $return[] = array($toInvoke, $args, $expectedResult);
        }
        // x -------------------------------------------------------------------------------------->

        return $return;
    }

    public function testStaticStringInvokableWithArgument()
    {
        $injector = new \Auryn\Injector;
        $invokable = $injector->buildExecutable('Auryn\Test\ClassWithStaticMethodThatTakesArg::doSomething');
        $this->assertEquals(42, $invokable(41));
    }

    public function testInterfaceFactoryDelegation()
    {
        $injector = new Injector;
        $injector->delegate('Auryn\Test\DelegatableInterface', 'Auryn\Test\ImplementsInterfaceFactory');
        $requiresDelegatedInterface = $injector->make('Auryn\Test\RequiresDelegatedInterface');
        $requiresDelegatedInterface->foo();
        $this->expectNotToPerformAssertions();
    }

    public function testMissingAlias()
    {
        $this->expectException(\Auryn\InjectorException::class);
        // There's a difference in reported class between PHP 7.4 and 8.0:
        // In PHP 7.4, the missing word is "TestMissingDependency"
        // In PHP 8.0+ it's "TypoInTypehint"
        //
        // Also, use a dot rather than trying to faff around with many backslashes.
        $this->expectExceptionMessageMatches('/^Could not make Auryn.Test.[A-Za-z]+: Class "?Auryn.Test.TypoInTypehint"? does not exist$/');
        $injector = new Injector;
        $testClass = $injector->make('Auryn\Test\TestMissingDependency');
    }

    public function testAliasingConcreteClasses()
    {
        $injector = new Injector;
        $injector->alias('Auryn\Test\ConcreteClass1', 'Auryn\Test\ConcreteClass2');
        $obj = $injector->make('Auryn\Test\ConcreteClass1');
        $this->assertInstanceOf('Auryn\Test\ConcreteClass2', $obj);
    }

    public function testSharedByAliasedInterfaceName()
    {
        $injector = new Injector;
        $injector->alias('Auryn\Test\SharedAliasedInterface', 'Auryn\Test\SharedClass');
        $injector->share('Auryn\Test\SharedAliasedInterface');
        $class = $injector->make('Auryn\Test\SharedAliasedInterface');
        $class2 = $injector->make('Auryn\Test\SharedAliasedInterface');
        $this->assertSame($class, $class2);
    }

    public function testNotSharedByAliasedInterfaceName()
    {
        $injector = new Injector;
        $injector->alias('Auryn\Test\SharedAliasedInterface', 'Auryn\Test\SharedClass');
        $injector->alias('Auryn\Test\SharedAliasedInterface', 'Auryn\Test\NotSharedClass');
        $injector->share('Auryn\Test\SharedClass');
        $class = $injector->make('Auryn\Test\SharedAliasedInterface');
        $class2 = $injector->make('Auryn\Test\SharedAliasedInterface');

        $this->assertNotSame($class, $class2);
    }

    public function testSharedByAliasedInterfaceNameReversedOrder()
    {
        $injector = new Injector;
        $injector->share('Auryn\Test\SharedAliasedInterface');
        $injector->alias('Auryn\Test\SharedAliasedInterface', 'Auryn\Test\SharedClass');
        $class = $injector->make('Auryn\Test\SharedAliasedInterface');
        $class2 = $injector->make('Auryn\Test\SharedAliasedInterface');
        $this->assertSame($class, $class2);
    }

    public function testSharedByAliasedInterfaceNameWithParameter()
    {
        $injector = new Injector;
        $injector->alias('Auryn\Test\SharedAliasedInterface', 'Auryn\Test\SharedClass');
        $injector->share('Auryn\Test\SharedAliasedInterface');
        $sharedClass = $injector->make('Auryn\Test\SharedAliasedInterface');
        $childClass = $injector->make('Auryn\Test\ClassWithAliasAsParameter');
        $this->assertSame($sharedClass, $childClass->sharedClass);
    }

    public function testSharedByAliasedInstance()
    {
        $injector = new Injector;
        $injector->alias('Auryn\Test\SharedAliasedInterface', 'Auryn\Test\SharedClass');
        $sharedClass = $injector->make('Auryn\Test\SharedAliasedInterface');
        $injector->share($sharedClass);
        $childClass = $injector->make('Auryn\Test\ClassWithAliasAsParameter');
        $this->assertSame($sharedClass, $childClass->sharedClass);
    }

    public function testMultipleShareCallsDontOverrideTheOriginalSharedInstance()
    {
        $injector = new Injector;
        $injector->share('StdClass');
        $stdClass1 = $injector->make('StdClass');
        $injector->share('StdClass');
        $stdClass2 = $injector->make('StdClass');
        $this->assertSame($stdClass1, $stdClass2);
    }

    public function testDependencyWhereSharedWithProtectedConstructor()
    {
        $injector = new Injector;

        $inner = TestDependencyWithProtectedConstructor::create();
        $injector->share($inner);

        $outer = $injector->make('Auryn\Test\TestNeedsDepWithProtCons');

        $this->assertSame($inner, $outer->dep);
    }

    public function testDependencyWhereShared()
    {
        $injector = new Injector;
        $injector->share('Auryn\Test\ClassInnerB');
        $innerDep = $injector->make('Auryn\Test\ClassInnerB');
        $inner = $injector->make('Auryn\Test\ClassInnerA');
        $this->assertSame($innerDep, $inner->dep);
        $outer = $injector->make('Auryn\Test\ClassOuter');
        $this->assertSame($innerDep, $outer->dep->dep);
    }

    public function testBugWithReflectionPoolIncorrectlyReturningBadInfo()
    {
        $injector = new Injector;
        $obj = $injector->make('Auryn\Test\ClassOuter');
        $this->assertInstanceOf('Auryn\Test\ClassOuter', $obj);
        $this->assertInstanceOf('Auryn\Test\ClassInnerA', $obj->dep);
        $this->assertInstanceOf('Auryn\Test\ClassInnerB', $obj->dep->dep);
    }

    public function provideCyclicDependencies()
    {
        return array(
            'Auryn\Test\RecursiveClassA' => array('Auryn\Test\RecursiveClassA'),
            'Auryn\Test\RecursiveClassB' => array('Auryn\Test\RecursiveClassB'),
            'Auryn\Test\RecursiveClassC' => array('Auryn\Test\RecursiveClassC'),
            'Auryn\Test\RecursiveClass1' => array('Auryn\Test\RecursiveClass1'),
            'Auryn\Test\RecursiveClass2' => array('Auryn\Test\RecursiveClass2'),
            'Auryn\Test\DependsOnCyclic' => array('Auryn\Test\DependsOnCyclic'),
        );
    }

     /**
      * @dataProvider provideCyclicDependencies
      */
    public function testCyclicDependencies($class)
    {
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionCode(\Auryn\Injector::E_CYCLIC_DEPENDENCY);
        $injector = new Injector;
        $injector->make($class);
    }

    public function testNonConcreteDependencyWithDefault()
    {
        $injector = new Injector;
        $class = $injector->make('Auryn\Test\NonConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf('Auryn\Test\NonConcreteDependencyWithDefaultValue', $class);
        $this->assertNull($class->interface);
    }

    public function testNonConcreteDependencyWithDefaultValueThroughAlias()
    {
        $injector = new Injector;
        $injector->alias(
            'Auryn\Test\DelegatableInterface',
            'Auryn\Test\ImplementsInterface'
        );
        $class = $injector->make('Auryn\Test\NonConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf('Auryn\Test\NonConcreteDependencyWithDefaultValue', $class);
        $this->assertInstanceOf('Auryn\Test\ImplementsInterface', $class->interface);
    }

    public function testNonConcreteDependencyWithDefaultValueThroughDelegation()
    {
        $injector = new Injector;
        $injector->delegate('Auryn\Test\DelegatableInterface', 'Auryn\Test\ImplementsInterfaceFactory');
        $class = $injector->make('Auryn\Test\NonConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf('Auryn\Test\NonConcreteDependencyWithDefaultValue', $class);
        $this->assertInstanceOf('Auryn\Test\ImplementsInterface', $class->interface);
    }

    public function testDependencyWithDefaultValueThroughShare()
    {
        // Remember that if there's a type hint, it is constructed even if there's a default value:
        //
        // From the documentation ("Dependency resolution"):
        // ... others ...
        // 5:  If a dependency is type-hinted, the Injector will recursively instantiate it subject to any
        // implementations or definitions
        // 6: If no type-hint exists and the parameter has a default value, the default value is injected
        /// ... more ...
        $injector = new Injector;
        //Instance is not shared, null default is used for dependency
        $instance = $injector->make('Auryn\Test\ConcreteDependencyWithDefaultValue');
        $this->assertInstanceOf('StdClass', $instance->dependency);

        //Instance is explicitly shared, $instance is used for dependency
        $dependency = new \StdClass();
        $injector->share($dependency);
        $instance = $injector->make('Auryn\Test\ConcreteDependencyWithDefaultValue');
        $this->assertSame($dependency, $instance->dependency);
    }

    public function testShareAfterAliasException()
    {
        $this->expectException(\Auryn\ConfigException::class);
        $this->expectExceptionCode(\Auryn\Injector::E_ALIASED_CANNOT_SHARE);
        $this->expectExceptionMessage("Cannot share class stdclass because it is currently aliased to Auryn\Test\SomeOtherClass");
        $injector = new Injector();
        $testClass = new \StdClass();
        $injector->alias('StdClass', 'Auryn\Test\SomeOtherClass');
        $injector->share($testClass);
    }

    public function testShareAfterAliasAliasedClassAllowed()
    {
        $injector = new Injector();
        $testClass = new DepImplementation();
        $injector->alias('Auryn\Test\DepInterface', 'Auryn\Test\DepImplementation');
        $injector->share($testClass);
        $obj = $injector->make('Auryn\Test\DepInterface');
        $this->assertInstanceOf('Auryn\Test\DepImplementation', $obj);
    }

    public function testAliasAfterShareByStringAllowed()
    {
        $injector = new Injector();
        $injector->share('Auryn\Test\DepInterface');
        $injector->alias('Auryn\Test\DepInterface', 'Auryn\Test\DepImplementation');
        $obj = $injector->make('Auryn\Test\DepInterface');
        $obj2 = $injector->make('Auryn\Test\DepInterface');
        $this->assertInstanceOf('Auryn\Test\DepImplementation', $obj);
        $this->assertEquals($obj, $obj2);
    }

    public function testAliasAfterShareBySharingAliasAllowed()
    {
        $injector = new Injector();
        $injector->share('Auryn\Test\DepImplementation');
        $injector->alias('Auryn\Test\DepInterface', 'Auryn\Test\DepImplementation');
        $obj = $injector->make('Auryn\Test\DepInterface');
        $obj2 = $injector->make('Auryn\Test\DepInterface');
        $this->assertInstanceOf('Auryn\Test\DepImplementation', $obj);
        $this->assertEquals($obj, $obj2);
    }

    public function testAliasAfterShareException()
    {
        $this->expectException(\Auryn\ConfigException::class);
        $this->expectExceptionCode(\Auryn\Injector::E_SHARED_CANNOT_ALIAS);
        $this->expectExceptionMessage("Cannot alias class stdclass to Auryn\Test\SomeOtherClass because it is currently shared");
        $injector = new Injector();
        $testClass = new \StdClass();
        $injector->share($testClass);
        $injector->alias('StdClass', 'Auryn\Test\SomeOtherClass');
    }

    public function testAppropriateExceptionThrownOnNonPublicConstructor()
    {
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionCode(\Auryn\Injector::E_NON_PUBLIC_CONSTRUCTOR);
        $this->expectExceptionMessage("Cannot instantiate protected/private constructor in class Auryn\Test\HasNonPublicConstructor");
        $injector = new Injector();
        $injector->make('Auryn\Test\HasNonPublicConstructor');
    }

    public function testAppropriateExceptionThrownOnNonPublicConstructorWithArgs()
    {
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionCode(\Auryn\Injector::E_NON_PUBLIC_CONSTRUCTOR);
        $this->expectExceptionMessage("Cannot instantiate protected/private constructor in class Auryn\Test\HasNonPublicConstructorWithArgs");
        $injector = new Injector();
        $injector->make('Auryn\Test\HasNonPublicConstructorWithArgs');
    }

    public function testMakeExecutableFailsOnNonExistentFunction()
    {
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionCode(\Auryn\Injector::E_INVOKABLE);
        $this->expectExceptionMessage('nonExistentFunction');
        $injector = new Injector();
        $injector->buildExecutable('nonExistentFunction');
    }

    public function testMakeExecutableFailsOnNonExistentInstanceMethod()
    {
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionCode(\Auryn\Injector::E_INVOKABLE);
        $this->expectExceptionMessage('nonExistentMethod');
        $object = new \StdClass();
        $injector = new Injector();
        $injector->buildExecutable(array($object, 'nonExistentMethod'));
    }

    public function testMakeExecutableFailsOnNonExistentStaticMethod()
    {
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionCode(\Auryn\Injector::E_INVOKABLE);
        $this->expectExceptionMessage('nonExistentMethod');
        $injector = new Injector();
        $injector->buildExecutable(array('StdClass', 'nonExistentMethod'));
    }

    public function testMakeExecutableFailsOnClassWithoutInvoke()
    {
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionCode(\Auryn\Injector::E_INVOKABLE);
        $this->expectExceptionMessage("Invalid invokable: callable or provisional string required");
        $injector = new Injector();
        $object = new \StdClass();
        $injector->buildExecutable($object);
    }

    public function testBadAlias()
    {
        $this->expectException(\Auryn\ConfigException::class);
        $this->expectExceptionCode(\Auryn\Injector::E_NON_EMPTY_STRING_ALIAS);
        $this->expectExceptionMessage("Invalid alias: non-empty string required at arguments 1 and 2");
        $injector = new Injector();
        $injector->share('Auryn\Test\DepInterface');
        $injector->alias('Auryn\Test\DepInterface', '');
    }

    public function testShareNewAlias()
    {
        $injector = new Injector();
        $injector->share('Auryn\Test\DepImplementation');
        $injector->alias('Auryn\Test\DepInterface', 'Auryn\Test\DepImplementation');
        $this->expectNotToPerformAssertions();
    }

    public function testDefineWithBackslashAndMakeWithoutBackslash()
    {
        $injector = new Injector();
        $injector->define('Auryn\Test\SimpleNoTypehintClass', array(':arg' => 'tested'));
        $testClass = $injector->make('Auryn\Test\SimpleNoTypehintClass');
        $this->assertEquals('tested', $testClass->testParam);
    }

    public function testShareWithBackslashAndMakeWithoutBackslash()
    {
        $injector = new Injector();
        $injector->share('\StdClass');
        $classA = $injector->make('StdClass');
        $classA->tested = false;
        $classB = $injector->make('\StdClass');
        $classB->tested = true;

        $this->assertEquals($classA->tested, $classB->tested);
    }

    public function testInstanceMutate()
    {
        $injector = new Injector();
        $injector->prepare('\StdClass', function ($obj, $injector) {
            $obj->testval = 42;
        });
        $obj = $injector->make('StdClass');

        $this->assertSame(42, $obj->testval);
    }

    public function testInterfaceMutate()
    {
        $injector = new Injector();
        $injector->prepare('Auryn\Test\SomeInterface', function ($obj, $injector) {
            $obj->testProp = 42;
        });
        $obj = $injector->make('Auryn\Test\PreparesImplementationTest');

        $this->assertSame(42, $obj->testProp);
    }

    /**
     * Test that custom definitions are not passed through to dependencies.
     * Surprising things would happen if this did occur.
     */
    public function testCustomDefinitionNotPassedThrough()
    {
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionCode(\Auryn\Injector::E_UNDEFINED_PARAM);
        $this->expectExceptionMessage('No definition available to provision typeless parameter $foo at position 0 in Auryn\Test\DependencyWithDefinedParam::__construct() declared in Auryn\Test\DependencyWithDefinedParam::');
        $injector = new Injector();
        $injector->share('Auryn\Test\DependencyWithDefinedParam');
        $injector->make('Auryn\Test\RequiresDependencyWithDefinedParam', array(':foo' => 5));
    }

    public function testDelegationFunction()
    {
        $injector = new Injector();
        $injector->delegate('Auryn\Test\TestDelegationSimple', 'Auryn\Test\createTestDelegationSimple');
        $obj = $injector->make('Auryn\Test\TestDelegationSimple');
        $this->assertInstanceOf('Auryn\Test\TestDelegationSimple', $obj);
        $this->assertTrue($obj->delegateCalled);
    }

    public function testDelegationDependency()
    {
        $injector = new Injector();
        $injector->delegate(
            'Auryn\Test\TestDelegationDependency',
            'Auryn\Test\createTestDelegationDependency'
        );
        $obj = $injector->make('Auryn\Test\TestDelegationDependency');
        $this->assertInstanceOf('Auryn\Test\TestDelegationDependency', $obj);
        $this->assertTrue($obj->delegateCalled);
    }

    public function testExecutableAliasing()
    {
        $injector = new Injector();
        $injector->alias('Auryn\Test\BaseExecutableClass', 'Auryn\Test\ExtendsExecutableClass');
        $result = $injector->execute(array('Auryn\Test\BaseExecutableClass', 'foo'));
        $this->assertEquals('This is the ExtendsExecutableClass', $result);
    }

    public function testExecutableAliasingStatic()
    {
        $injector = new Injector();
        $injector->alias('Auryn\Test\BaseExecutableClass', 'Auryn\Test\ExtendsExecutableClass');
        $result = $injector->execute(array('Auryn\Test\BaseExecutableClass', 'bar'));
        $this->assertEquals('This is the ExtendsExecutableClass', $result);
    }

    /**
     * Test coverage for delegate closures that are defined outside
     * of a class.ph
     * @throws \Auryn\ConfigException
     */
    public function testDelegateClosure()
    {
        $delegateClosure = \Auryn\Test\getDelegateClosureInGlobalScope();
        $injector = new Injector();
        $injector->delegate('Auryn\Test\DelegateClosureInGlobalScope', $delegateClosure);
        $injector->make('Auryn\Test\DelegateClosureInGlobalScope');
        $this->expectNotToPerformAssertions();
    }

    public function testCloningWithServiceLocator()
    {
        $injector = new Injector();
        $injector->share($injector);
        $instance = $injector->make('Auryn\Test\CloneTest');
        $newInjector = $instance->injector;
        $newInstance = $newInjector->make('Auryn\Test\CloneTest');
        $this->expectNotToPerformAssertions();
    }

    public function testAbstractExecute()
    {
        $injector = new Injector();

        $fn = function () {
            return new \Auryn\Test\ConcreteExexcuteTest();
        };

        $injector->delegate('Auryn\Test\AbstractExecuteTest', $fn);
        $result = $injector->execute(array('Auryn\Test\AbstractExecuteTest', 'process'));

        $this->assertEquals('Concrete', $result);
    }

    public function testDebugMake()
    {
        $injector = new Injector();
        try {
            $injector->make('Auryn\Test\DependencyChainTest');
        } catch (\Auryn\InjectionException $ie) {
            $chain = $ie->getDependencyChain();
            $this->assertCount(2, $chain);

            $this->assertEquals('auryn\test\dependencychaintest', $chain[0]);
            $this->assertEquals('auryn\test\depinterface', $chain[1]);
        }
    }

    public function testInspectShares()
    {
        $injector = new Injector();
        $injector->share('Auryn\Test\SomeClassName');

        $inspection = $injector->inspect('Auryn\Test\SomeClassName', Injector::I_SHARES);
        $this->assertArrayHasKey('auryn\test\someclassname', $inspection[Injector::I_SHARES]);
    }

    public function testInspectAll()
    {
        $injector = new Injector();

        // Injector::I_BINDINGS
        $injector->define('Auryn\Test\DependencyWithDefinedParam', array(':arg' => 42));

        // Injector::I_DELEGATES
        $injector->delegate('Auryn\Test\MadeByDelegate', 'Auryn\Test\CallableDelegateClassTest');

        // Injector::I_PREPARES
        $injector->prepare('Auryn\Test\MadeByDelegate', function ($c) {});

        // Injector::I_ALIASES
        $injector->alias('i', 'Auryn\Injector');

        // Injector::I_SHARES
        $injector->share('Auryn\Injector');

        $all = $injector->inspect();
        $some = $injector->inspect('Auryn\Test\MadeByDelegate');

        $this->assertCount(5, array_filter($all));
        $this->assertCount(2, array_filter($some));
    }

    public function testDelegationDoesntMakeObject()
    {
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionCode(\Auryn\Injector::E_MAKING_FAILED);
        $this->expectExceptionMessage('Making auryn\test\someclassname did not result in an object, instead result is of type \'NULL\'');
        $delegate = function () {
            return null;
        };
        $injector = new Injector();
        $injector->delegate('Auryn\Test\SomeClassName', $delegate);
        $injector->make('Auryn\Test\SomeClassName');
    }

    public function testDelegationDoesntMakeObjectMakesString()
    {
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionCode(\Auryn\Injector::E_MAKING_FAILED);
        $this->expectExceptionMessage('Making auryn\test\someclassname did not result in an object, instead result is of type \'string\'');
        $delegate = function () {
            return 'ThisIsNotAClass';
        };
        $injector = new Injector();
        $injector->delegate('Auryn\Test\SomeClassName', $delegate);
        $injector->make('Auryn\Test\SomeClassName');
    }

    public function testPrepareInvalidCallable()
    {
        $invalidCallable = 'This_does_not_exist';
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionMessage($invalidCallable);
        $injector = new Injector;
        $injector->prepare("StdClass", $invalidCallable);
    }

    public function testPrepareCallableReplacesObjectWithReturnValueOfSameInterfaceType()
    {
        $injector = new Injector;
        $expected = new SomeImplementation; // <-- implements SomeInterface
        $injector->prepare("Auryn\Test\SomeInterface", function ($impl) use ($expected) {
            return $expected;
        });
        $actual = $injector->make("Auryn\Test\SomeImplementation");
        $this->assertSame($expected, $actual);
    }

    public function testPrepareCallableReplacesObjectWithReturnValueOfSameClassType()
    {
        $injector = new Injector;
        $expected = new SomeImplementation; // <-- implements SomeInterface
        $injector->prepare("Auryn\Test\SomeImplementation", function ($impl) use ($expected) {
            return $expected;
        });
        $actual = $injector->make("Auryn\Test\SomeImplementation");
        $this->assertSame($expected, $actual);
    }

    public function testChildWithoutConstructorWorks() {

        $injector = new Injector;
        try {
            $injector->define('Auryn\Test\ParentWithConstructor', array(':foo' => 'parent'));
            $injector->define('Auryn\Test\ChildWithoutConstructor', array(':foo' => 'child'));

            $injector->share('Auryn\Test\ParentWithConstructor');
            $injector->share('Auryn\Test\ChildWithoutConstructor');

            $child = $injector->make('Auryn\Test\ChildWithoutConstructor');
            $this->assertEquals('child', $child->foo);

            $parent = $injector->make('Auryn\Test\ParentWithConstructor');
            $this->assertEquals('parent', $parent->foo);
        }
        catch (\Auryn\InjectionException $ie) {
            echo $ie->getMessage();
            $this->fail("Auryn failed to locate the ");
        }
    }

    public function testChildWithoutConstructorMissingParam()
    {
        $this->expectException(\Auryn\InjectionException::class);
        $this->expectExceptionCode(\Auryn\Injector::E_UNDEFINED_PARAM);
        $this->expectExceptionMessage('No definition available to provision typeless parameter $foo at position 0 in Auryn\Test\ChildWithoutConstructor::__construct() declared in Auryn\Test\ParentWithConstructor');
        $injector = new Injector;
        $injector->define('Auryn\Test\ParentWithConstructor', array(':foo' => 'parent'));
        $injector->make('Auryn\Test\ChildWithoutConstructor');
    }

    public function testInstanceClosureDelegates()
    {
        $injector = new Injector;
        $injector->delegate('Auryn\Test\DelegatingInstanceA', function (DelegateA $d) {
            return new \Auryn\Test\DelegatingInstanceA($d);
        });
        $injector->delegate('Auryn\Test\DelegatingInstanceB', function (DelegateB $d) {
            return new \Auryn\Test\DelegatingInstanceB($d);
        });

        $a = $injector->make('Auryn\Test\DelegatingInstanceA');
        $b = $injector->make('Auryn\Test\DelegatingInstanceB');

        $this->assertInstanceOf('Auryn\Test\DelegateA', $a->a);
        $this->assertInstanceOf('Auryn\Test\DelegateB', $b->b);
    }

    public function testThatExceptionInConstructorDoesntCauseCyclicDependencyException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Exception in constructor");
        $injector = new Injector;

        try {
            $injector->make('Auryn\Test\ThrowsExceptionInConstructor');
        }
        catch (\Exception $e) {
        }

        $injector->make('Auryn\Test\ThrowsExceptionInConstructor');
    }
}
