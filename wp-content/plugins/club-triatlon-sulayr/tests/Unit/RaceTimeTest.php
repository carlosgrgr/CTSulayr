<?php
use ClubTriatlonSulayr\Modules\Ranking\Calc\RaceTime;
use PHPUnit\Framework\TestCase;

/**
 * T24 de SPEC-RANKING-INTERNO §10.1.
 */
class RaceTimeTest extends TestCase {

    public function test_t24_lectura(): void {
        $this->assertSame( 3723, RaceTime::parse( '1:02:03' ) );
        $this->assertSame( 3723, RaceTime::parse( '01:02:03' ) );
        $this->assertSame( 3723, RaceTime::parse( '62:03' ) );
        $this->assertSame( 1198, RaceTime::parse( '0:19:58' ) );
        $this->assertNull( RaceTime::parse( '1:60:00' ) );
        $this->assertNull( RaceTime::parse( '1:02:60' ) );
        $this->assertNull( RaceTime::parse( '0:00:00' ) );
        $this->assertNull( RaceTime::parse( '1:02:03.4' ) );
        $this->assertNull( RaceTime::parse( 'abc' ) );
        $this->assertNull( RaceTime::parse( '24:00:00' ) );
    }

    public function test_t24_formato(): void {
        $this->assertSame( '1:02:03', RaceTime::format( 3723 ) );
        $this->assertSame( '0:19:58', RaceTime::format( 1198 ) );
        $this->assertSame( '', RaceTime::format( null ) );
    }
}
