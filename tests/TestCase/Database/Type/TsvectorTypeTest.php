<?php
declare(strict_types=1);

namespace Autopage\PgSearch\Test\TestCase\Database\Type;

use Autopage\PgSearch\Database\Type\Tsvector;
use Cake\Database\TypeFactory;
use Cake\TestSuite\TestCase;
use PDO;

/**
 * Test for the Tsvector type.
 */
class TsvectorTypeTest extends TestCase
{
    /**
     * @var \Cake\Database\Type\TsvectorType
     */
    protected $type;

    /**
     * @var \Cake\Database\Driver
     */
    protected $driver;

    /**
     * Setup
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->type = TypeFactory::build('tsvector');
        $this->driver = $this->getMockBuilder('Cake\Database\Driver')->getMock();
    }

    /**
     * Test toPHP
     *
     * @return void
     */
    public function testToPHP()
    {

        // 'adipisc':7,34 'aliqua':19 'aliquet':28 'amet':5,31 'auctor':22 'consectetur':6 'cras':33 'cursus':26 'dolor':3,17 'dui':47 'eget':29 'eiusmod':11 'elit':8 'enim':35 'et':16 'eu':36 'faucibus':48 'id':25 'incididunt':13 'ipsum':2 'justo':46 'labor':15
        $this->assertNull($this->type->toPHP(null, $this->driver));

        $result = $this->type->toPHP("'aliqua':19 'aliquet':28", $this->driver);
        $expected = ['aliqua' => [19], 'aliquet' => [28]];
        $this->assertEquals($expected, $result);

        $result = $this->type->toPHP("'aliqua':19", $this->driver);
        $expected = ['aliqua' => [19]];
        $this->assertEquals($expected, $result);

        $result = $this->type->toPHP("'adipisc':7,34", $this->driver);
        $expected = ['adipisc' => [7, 34]];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test converting tsvector to PHP values.
     *
     * @return void
     */
    public function testManyToPHP()
    {
        $values = [
            'a' => null,
            'b' => "'adipisc':7 'amet':5 'consectetur':6 'dolor':3,9 'elit':8 'ipsum':2 'lorem':1 'sit':4",
            'c' => "'hous':1",
            'd' => "'1':1",
            'e' => '',
        ];
        $expected = [
            'a' => null,
            'b' => ['adipisc' => [7], 'amet' => [5], 'consectetur' => [6], 'dolor' => [3, 9], 'elit' => [8], 'ipsum' => [2], 'lorem' => [1], 'sit' => [4]],
            'c' => ['hous' => [1]],
            'd' => ['1' => [1]],
            'e' => null,
        ];
        $this->assertEquals(
            $expected,
            $this->type->manyToPHP($values, array_keys($values), $this->driver)
        );
    }

    /**
     * Test to make sure the method throws an exception for invalid tsvector values.
     *
     * @return void
     */
    public function testInvalidManyToPHP()
    {
        $this->expectException(\InvalidArgumentException::class);
        $values = [
            'a' => null,
            'b' => "'adipisc':7 'amet':5 'consectetur':6 'dolor':3,9 'elit':8 'ipsum':2 'lorem':1 'sit':4",
            'c' => "'hous':1",
            'd' => "'1':1",
            'e' => '',
            'f' => 'abc',
        ];
        $expected = 'nothing expected';
        $this->assertEquals(
            $expected,
            $this->type->manyToPHP($values, array_keys($values), $this->driver)
        );
    }

    /**
     * Test converting to database format
     *
     * @return void
     */
    public function testToDatabase()
    {
        $this->assertNull($this->type->toDatabase(null, $this->driver));

        $result = $this->type->toDatabase('house', $this->driver);
        $this->assertSame('house', $result);

        $result = $this->type->toDatabase(2, $this->driver);
        $this->assertSame('2', $result);

        $result = $this->type->toDatabase(['Lorem', 'ipsum'], $this->driver);
        $this->assertSame('Lorem ipsum', $result);
    }

    /**
     * Test marshalling
     *
     * @return void
     */
    public function testMarshal()
    {
        $result = $this->type->marshal(null);
        $this->assertNull($result);

        $result = $this->type->marshal('');
        $this->assertNull($result);

        $result = $this->type->marshal('0');
        $this->assertSame('0', $result);

        $result = $this->type->marshal('house');
        $this->assertSame('house', $result);

        $result = $this->type->marshal('Lorem ipsum');
        $this->assertSame('Lorem ipsum', $result);

        $result = $this->type->marshal(['Lorem', 'ipsum']);
        $this->assertSame('Lorem ipsum', $result);
    }

    /**
     * Test that the PDO binding type is correct.
     *
     * @return void
     */
    public function testToStatement()
    {
        $this->assertSame(PDO::PARAM_STR, $this->type->toStatement('', $this->driver));
    }
}
