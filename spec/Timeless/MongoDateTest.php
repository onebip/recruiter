<?php
namespace Timeless;

use Eris;
use Eris\Generator;

class MongoDateTest extends \PHPUnit_Framework_TestCase
{
    use Eris\TestTrait;

    public function testConvertsBackAndForthMongoDatesWithoutLosingMillisecondPrecision()
    {
        $this
            ->forAll(
                Generator\choose(0, 1500 * 1000 * 1000)
            )
            ->then(function($milliseconds) {
                $moment = new Moment($milliseconds);
                $this->assertEquals(
                    $moment,
                    MongoDate::toMoment(MongoDate::from($moment))
                );
            });
    }
}
