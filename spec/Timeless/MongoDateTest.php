<?php
namespace Timeless;

use Eris;
use Eris\Generator;
use PHPUnit\Framework\TestCase;

class MongoDateTest extends TestCase
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
